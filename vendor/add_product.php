<?php
require '../config.php';
if (!isset($_SESSION['vendor_id'])) { header("Location: login.php"); exit(); }

$vendor_id = $_SESSION['vendor_id'];
$success   = '';
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']);
    $category    = trim($_POST['category']);
    $price       = floatval($_POST['price']);
    $stock       = intval($_POST['stock']);
    $description = trim($_POST['description']);

    $upload_dir = '../uploads/products/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $image_paths = [];
    for ($i = 1; $i <= 4; $i++) {
        if (!empty($_POST['img_' . $i])) {
            $base64     = preg_replace('/^data:image\/\w+;base64,/', '', $_POST['img_' . $i]);
            $base64     = str_replace(' ', '+', $base64);
            $image_data = base64_decode($base64);
            $filename   = 'product_' . time() . '_' . $i . '_' . rand(100,999) . '.jpg';
            if (file_put_contents($upload_dir . $filename, $image_data)) {
                $image_paths[] = 'uploads/products/' . $filename;
            }
        }
    }

    if (empty($image_paths)) {
        $error = "At least 1 image upload karo!";
    } elseif (empty($name) || empty($category) || $price <= 0) {
        $error = "Please fill all required fields!";
    } else {
        $img_str = mysqli_real_escape_string($conn, implode(',', $image_paths));
        mysqli_query($conn, "INSERT INTO products (name,category,price,stock,description,image,vendor_id) VALUES ('".mysqli_real_escape_string($conn,$name)."','".mysqli_real_escape_string($conn,$category)."','$price','$stock','".mysqli_real_escape_string($conn,$description)."','$img_str','$vendor_id')");
        $success = "Product added with " . count($image_paths) . " image(s)!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — Add Product</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{--cream:#f5efe6;--warm:#e8d5b7;--brown:#5c3d1e;--accent:#d4622a;--accent2:#e8a045;--text:#2d1a0a;--muted:#8a6a4a;--white:#fffdf8;--dark:#1a0f02;}
    body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--text);}
    nav{position:fixed;top:0;left:0;right:0;z-index:100;background:var(--dark);display:flex;align-items:center;justify-content:space-between;padding:0 5vw;height:65px;box-shadow:0 4px 20px rgba(0,0,0,0.3);}
    .logo{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:900;color:var(--white);text-decoration:none;}
    .logo span{color:var(--accent2);}
    .nav-right{display:flex;gap:0.8rem;flex-wrap:wrap;}
    .nav-link{padding:0.45rem 1.1rem;border:1.5px solid rgba(255,255,255,0.25);border-radius:50px;color:rgba(255,255,255,0.7);font-size:0.82rem;font-weight:600;text-decoration:none;transition:all .2s;}
    .nav-link:hover{border-color:var(--accent2);color:var(--accent2);}
    .main{margin-top:85px;padding:2.5rem 5vw;max-width:960px;margin-left:auto;margin-right:auto;}
    .page-title{font-family:'Playfair Display',serif;font-size:2rem;color:var(--brown);margin-bottom:0.3rem;}
    .page-sub{color:var(--muted);font-size:0.9rem;margin-bottom:2rem;}
    .msg{padding:0.85rem 1.2rem;border-radius:12px;font-size:0.88rem;margin-bottom:1.5rem;font-weight:500;}
    .msg.success{background:#d4edda;color:#155724;border:1.5px solid #b8dfc4;}
    .msg.error{background:#f8d7da;color:#721c24;border:1.5px solid #f5b8bc;}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:2rem;}

    /* IMAGE SLOTS */
    .img-section{background:var(--white);border-radius:20px;padding:1.5rem;box-shadow:0 4px 24px rgba(92,61,30,0.07);}
    .img-section-title{font-family:'Playfair Display',serif;font-size:1.1rem;color:var(--brown);margin-bottom:0.3rem;}
    .img-section-sub{font-size:0.78rem;color:var(--muted);margin-bottom:1rem;}
    .slots-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:0.8rem;}
    .img-slot{position:relative;aspect-ratio:1/1;border:2px dashed var(--warm);border-radius:14px;overflow:hidden;cursor:pointer;transition:all .2s;background:var(--cream);display:flex;align-items:center;justify-content:center;}
    .img-slot:hover{border-color:var(--accent);background:#fff5f0;}
    .img-slot.filled{border-style:solid;border-color:var(--accent);}
    .img-slot input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
    .slot-preview{width:100%;height:100%;object-fit:cover;object-position:center;display:none;position:absolute;inset:0;}
    .img-slot.filled .slot-preview{display:block;}
    .slot-placeholder{text-align:center;pointer-events:none;z-index:1;}
    .img-slot.filled .slot-placeholder{display:none;}
    .slot-icon{font-size:1.8rem;display:block;margin-bottom:0.3rem;}
    .slot-lbl{font-size:0.7rem;font-weight:600;color:var(--muted);}
    .slot-del{position:absolute;top:0.4rem;right:0.4rem;width:22px;height:22px;background:#dc3545;color:white;border:none;border-radius:50%;font-size:0.7rem;cursor:pointer;display:none;align-items:center;justify-content:center;z-index:10;font-weight:700;}
    .img-slot.filled .slot-del{display:flex;}
    .main-badge{position:absolute;bottom:0.4rem;left:0.4rem;background:var(--accent);color:white;font-size:0.6rem;font-weight:700;padding:0.1rem 0.5rem;border-radius:50px;}
    .hint{font-size:0.72rem;color:var(--muted);text-align:center;margin-top:0.7rem;}
    .tips{margin-top:1rem;}
    .tip{display:flex;align-items:center;gap:0.5rem;font-size:0.78rem;color:var(--muted);padding:0.2rem 0;}

    /* FORM */
    .card{background:var(--white);border-radius:20px;padding:2rem;box-shadow:0 4px 24px rgba(92,61,30,0.07);}
    .card-title{font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--brown);margin-bottom:1.5rem;padding-bottom:0.8rem;border-bottom:1.5px solid var(--warm);}
    .form-group{margin-bottom:1.2rem;}
    .form-group label{display:block;font-size:0.82rem;font-weight:600;color:var(--brown);margin-bottom:0.4rem;}
    .form-group input,.form-group select,.form-group textarea{width:100%;padding:0.75rem 1rem;border:1.5px solid var(--warm);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.92rem;color:var(--text);background:var(--cream);outline:none;transition:all .2s;}
    .form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(212,98,42,0.1);background:var(--white);}
    .form-group textarea{resize:vertical;min-height:90px;}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
    .required{color:var(--accent);}
    .btn-submit{width:100%;padding:0.9rem;background:var(--accent);color:white;border:none;border-radius:12px;font-family:'DM Sans',sans-serif;font-size:1rem;font-weight:700;cursor:pointer;transition:all .2s;margin-top:0.5rem;}
    .btn-submit:hover{background:#c0551f;transform:translateY(-2px);box-shadow:0 8px 24px rgba(212,98,42,0.35);}

    /* CROP MODAL */
    .crop-modal{position:fixed;inset:0;background:rgba(0,0,0,0.88);z-index:500;display:none;align-items:center;justify-content:center;padding:1rem;}
    .crop-modal.open{display:flex;}
    .crop-box{background:var(--dark);border-radius:24px;padding:1.5rem;width:100%;max-width:600px;}
    .crop-title{font-family:'Playfair Display',serif;font-size:1.1rem;color:var(--white);margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center;}
    .crop-indicator{font-size:0.75rem;color:rgba(255,255,255,0.5);font-family:'DM Sans',sans-serif;}
    .crop-container{width:100%;height:340px;overflow:hidden;border-radius:12px;background:#000;}
    .crop-container img{max-width:100%;display:block;}
    .crop-tools{display:flex;align-items:center;justify-content:space-between;margin-top:1rem;flex-wrap:wrap;gap:0.8rem;}
    .crop-btns{display:flex;gap:0.5rem;flex-wrap:wrap;}
    .crop-tool-btn{padding:0.45rem 0.9rem;border-radius:50px;font-size:0.8rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;border:1.5px solid rgba(255,255,255,0.2);background:rgba(255,255,255,0.1);color:white;transition:all .2s;}
    .crop-tool-btn:hover{background:rgba(255,255,255,0.2);}
    .aspect-btns{display:flex;gap:0.4rem;}
    .asp-btn{padding:0.35rem 0.7rem;border-radius:50px;font-size:0.75rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;border:1.5px solid rgba(255,255,255,0.2);background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.7);transition:all .2s;}
    .asp-btn.active,.asp-btn:hover{background:var(--accent);border-color:var(--accent);color:white;}
    .btn-crop-done{padding:0.6rem 1.8rem;background:var(--accent);color:white;border:none;border-radius:50px;font-size:0.9rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;}
    .btn-crop-done:hover{background:#c0551f;}
    .btn-crop-cancel{padding:0.6rem 1.2rem;background:rgba(255,255,255,0.1);color:white;border:1.5px solid rgba(255,255,255,0.2);border-radius:50px;font-size:0.9rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;}

    @media(max-width:768px){.form-grid{grid-template-columns:1fr;}.form-row{grid-template-columns:1fr;}.crop-container{height:260px;}}
    @media(max-width:600px){
      nav{height:auto;flex-wrap:wrap;padding:0.6rem 4vw;gap:0.5rem;}
      .logo{font-size:1.3rem;}.nav-right{gap:0.4rem;}.nav-link{padding:0.35rem 0.7rem;font-size:0.75rem;}
      .main{margin-top:100px;padding:1.5rem 4vw;}
    }
  </style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="logo">Trenzo<span>Kart</span></a>
  <div class="nav-right">
    <a href="products.php" class="nav-link">My Products</a>
    <a href="dashboard.php" class="nav-link">← Dashboard</a>
    <a href="logout.php" class="nav-link">Logout</a>
  </div>
</nav>

<div class="main">
  <h1 class="page-title">➕ Add New Product</h1>
  <p class="page-sub">Upload up to 4 images — auto-slide on product page</p>

  <?php if (!empty($success)): ?>
    <div class="msg success">✅ <?= htmlspecialchars($success) ?> <a href="products.php" style="color:#155724;font-weight:700;">View Products →</a></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="msg error">❌ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" id="productForm">
    <!-- 4 hidden image fields -->
    <?php for ($i = 1; $i <= 4; $i++): ?>
      <input type="hidden" name="img_<?= $i ?>" id="imgData<?= $i ?>"/>
    <?php endfor; ?>

    <div class="form-grid">
      <!-- LEFT: Images -->
      <div>
        <div class="img-section">
          <div class="img-section-title">📸 Product Images</div>
          <div class="img-section-sub">Upload 1–4 images • First = Main photo</div>
          <div class="slots-grid">
            <?php for ($i = 1; $i <= 4; $i++): ?>
            <div class="img-slot" id="slot<?= $i ?>">
              <input type="file" accept="image/*" onchange="onPick(this,<?= $i ?>)" id="file<?= $i ?>"/>
              <img class="slot-preview" id="prev<?= $i ?>" src="" alt=""/>
              <div class="slot-placeholder">
                <span class="slot-icon"><?= $i === 1 ? '📷' : '🖼️' ?></span>
                <div class="slot-lbl"><?= $i === 1 ? 'Main Image *' : "Image $i" ?></div>
              </div>
              <button type="button" class="slot-del" onclick="delSlot(<?= $i ?>)">✕</button>
              <?php if ($i === 1): ?><span class="main-badge">Main</span><?php endif; ?>
            </div>
            <?php endfor; ?>
          </div>
          <div class="hint">💡 Slides automatically on product page</div>
        </div>
        <div class="tips" style="margin-top:1rem;">
          <div class="tip">✅ Different angles se photos lo</div>
          <div class="tip">✅ Good lighting use karo</div>
          <div class="tip">✅ Crop kar sakte ho</div>
          <div class="tip">❌ Blurry images avoid karo</div>
        </div>
      </div>

      <!-- RIGHT: Form -->
      <div class="card">
        <div class="card-title">📦 Product Details</div>
        <div class="form-group">
          <label>Product Name <span class="required">*</span></label>
          <input type="text" name="name" placeholder="e.g. Cotton Shirt" required/>
        </div>
        <div class="form-group">
          <label>Category <span class="required">*</span></label>
          <select name="category" required>
            <option value="">Select Category</option>
            <option value="Electronics">📱 Electronics</option>
            <option value="Clothes">👕 Clothes</option>
            <option value="Grocery">🥗 Grocery</option>
            <option value="Food & Beverages">🥤 Food & Beverages</option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Price (₹) <span class="required">*</span></label>
            <input type="number" name="price" placeholder="e.g. 999" min="1" step="0.01" required/>
          </div>
          <div class="form-group">
            <label>Stock <span class="required">*</span></label>
            <input type="number" name="stock" placeholder="e.g. 50" min="0" required/>
          </div>
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="description" placeholder="Product ke features, material, size describe karo..."></textarea>
        </div>
        <button type="submit" class="btn-submit">🚀 Add Product →</button>
      </div>
    </div>
  </form>
</div>

<!-- Crop Modal -->
<div class="crop-modal" id="cropModal">
  <div class="crop-box">
    <div class="crop-title">
      ✂️ Crop Image
      <span class="crop-indicator" id="cropInd">Image 1 of 4</span>
    </div>
    <div class="crop-container"><img id="cropImg" src=""/></div>
    <div class="crop-tools">
      <div class="aspect-btns">
        <button class="asp-btn active" onclick="setAsp(1,1,this)">1:1</button>
        <button class="asp-btn" onclick="setAsp(4,3,this)">4:3</button>
        <button class="asp-btn" onclick="setAsp(16,9,this)">16:9</button>
        <button class="asp-btn" onclick="setAsp(NaN,NaN,this)">Free</button>
      </div>
      <div class="crop-btns">
        <button class="crop-tool-btn" onclick="cr.rotate(-90)">↺ Rotate</button>
        <button class="crop-tool-btn" onclick="cr.scaleX(-(cr.getData().scaleX||1))">↔ Flip</button>
        <button class="crop-tool-btn" onclick="cr.reset()">↩ Reset</button>
      </div>
      <div style="display:flex;gap:0.6rem;">
        <button class="btn-crop-cancel" onclick="closeCrop()">Cancel</button>
        <button class="btn-crop-done" onclick="applyCrop()">✅ Done</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script>
let cr = null, activeSlot = 1;

function onPick(input, n) {
  const file = input.files[0];
  if (!file) return;
  if (file.size > 5*1024*1024) { alert('Max 5MB!'); return; }
  activeSlot = n;
  document.getElementById('cropInd').textContent = 'Image ' + n + ' of 4';
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('cropImg').src = e.target.result;
    document.getElementById('cropModal').classList.add('open');
    if (cr) { cr.destroy(); cr = null; }
    setTimeout(() => {
      cr = new Cropper(document.getElementById('cropImg'), {
        aspectRatio:1, viewMode:2, dragMode:'move', autoCropArea:0.9,
        restore:false, guides:true, center:true, highlight:false,
        cropBoxMovable:true, cropBoxResizable:true, toggleDragModeOnDblclick:false
      });
    }, 100);
  };
  reader.readAsDataURL(file);
}

function setAsp(w, h, btn) {
  document.querySelectorAll('.asp-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  if (cr) cr.setAspectRatio(isNaN(w) ? NaN : w/h);
}

function applyCrop() {
  if (!cr) return;
  const canvas = cr.getCroppedCanvas({width:900, height:900, imageSmoothingQuality:'high'});
  const url = canvas.toDataURL('image/jpeg', 0.90);
  document.getElementById('prev' + activeSlot).src = url;
  document.getElementById('slot' + activeSlot).classList.add('filled');
  document.getElementById('imgData' + activeSlot).value = url;
  closeCrop();
}

function closeCrop() {
  document.getElementById('cropModal').classList.remove('open');
  if (cr) { cr.destroy(); cr = null; }
  document.getElementById('file' + activeSlot).value = '';
}

function delSlot(n) {
  document.getElementById('prev' + n).src = '';
  document.getElementById('slot' + n).classList.remove('filled');
  document.getElementById('imgData' + n).value = '';
  document.getElementById('file' + n).value = '';
}

document.getElementById('productForm').addEventListener('submit', function(e) {
  if (!document.getElementById('imgData1').value) {
    e.preventDefault();
    alert('Main image (slot 1) zaroori hai!');
  }
});
</script>
</body>
</html>