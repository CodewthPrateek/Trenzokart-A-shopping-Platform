<?php
require '../config.php';
if (!isset($_SESSION['vendor_id'])) { header("Location: login.php"); exit(); }

$vendor_id = $_SESSION['vendor_id'];
$success = $error = '';

// Delete
if (isset($_GET['delete'])) {
    $pid = intval($_GET['delete']);
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM products WHERE id='$pid' AND vendor_id='$vendor_id'"));
    if ($row) {
        foreach (array_filter(array_map('trim', explode(',', $row['image'] ?? ''))) as $img) {
            $fp = '../' . $img;
            if (file_exists($fp)) unlink($fp);
        }
        mysqli_query($conn, "DELETE FROM products WHERE id='$pid' AND vendor_id='$vendor_id'");
        $success = "Product deleted!";
    }
}

// Edit / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $pid         = intval($_POST['edit_id']);
    $name        = mysqli_real_escape_string($conn, trim($_POST['name']));
    $category    = mysqli_real_escape_string($conn, trim($_POST['category']));
    $price       = floatval($_POST['price']);
    $stock       = intval($_POST['stock']);
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));

    $upload_dir = '../uploads/products/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    // Get old images
    $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM products WHERE id='$pid' AND vendor_id='$vendor_id'"));
    $old_imgs = array_filter(array_map('trim', explode(',', $old['image'] ?? '')));

    $new_paths = [];
    $has_new = false;

    for ($i = 1; $i <= 4; $i++) {
        if (!empty($_POST['img_' . $i])) {
            $has_new = true;
            $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $_POST['img_' . $i]);
            $base64 = str_replace(' ', '+', $base64);
            $img_data = base64_decode($base64);
            $fname = 'product_' . time() . '_' . $i . '_' . rand(100,999) . '.jpg';
            if (file_put_contents($upload_dir . $fname, $img_data)) {
                $new_paths[] = 'uploads/products/' . $fname;
            }
        }
    }

    if ($has_new) {
        // Delete old images
        foreach ($old_imgs as $img) {
            $fp = '../' . $img;
            if (file_exists($fp)) unlink($fp);
        }
        $img_sql = ", image='" . mysqli_real_escape_string($conn, implode(',', $new_paths)) . "'";
    } else {
        $img_sql = '';
    }

    mysqli_query($conn, "UPDATE products SET name='$name',category='$category',price='$price',stock='$stock',description='$description'$img_sql WHERE id='$pid' AND vendor_id='$vendor_id'");
    $success = "Product updated!";
}

$result = mysqli_query($conn, "SELECT * FROM products WHERE vendor_id='$vendor_id' ORDER BY id DESC");
$products = [];
while ($row = mysqli_fetch_assoc($result)) { $products[] = $row; }

$icons = ['Clothes'=>'👕','Electronics'=>'📱','Grocery'=>'🥗','Food & Beverages'=>'🥤'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — My Products</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{--cream:#f5efe6;--warm:#e8d5b7;--brown:#5c3d1e;--accent:#d4622a;--accent2:#e8a045;--text:#2d1a0a;--muted:#8a6a4a;--white:#fffdf8;--dark:#1a0f02;}
    body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--text);}
    nav{position:fixed;top:0;left:0;right:0;z-index:100;background:var(--dark);display:flex;align-items:center;justify-content:space-between;padding:0 5vw;height:65px;box-shadow:0 4px 20px rgba(0,0,0,0.3);}
    .logo{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:900;color:var(--white);text-decoration:none;}
    .logo span{color:var(--accent2);}
    .nav-right{display:flex;gap:0.8rem;align-items:center;flex-wrap:wrap;}
    .nav-link{padding:0.45rem 1.1rem;border:1.5px solid rgba(255,255,255,0.25);border-radius:50px;color:rgba(255,255,255,0.7);font-size:0.82rem;font-weight:600;text-decoration:none;transition:all .2s;}
    .nav-link:hover{border-color:var(--accent2);color:var(--accent2);}
    .nav-link.accent{background:var(--accent);border-color:var(--accent);color:white;}
    .main{margin-top:85px;padding:2.5rem 5vw;}
    .page-title{font-family:'Playfair Display',serif;font-size:2rem;color:var(--brown);margin-bottom:0.3rem;}
    .page-sub{color:var(--muted);font-size:0.9rem;margin-bottom:2rem;}
    .msg{padding:0.85rem 1.2rem;border-radius:12px;font-size:0.88rem;margin-bottom:1.5rem;font-weight:500;}
    .msg.success{background:#d4edda;color:#155724;border:1.5px solid #b8dfc4;}
    .msg.error{background:#f8d7da;color:#721c24;border:1.5px solid #f5b8bc;}

    /* PRODUCT GRID */
    .products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1.5rem;}
    .product-card{background:var(--white);border-radius:20px;overflow:hidden;box-shadow:0 4px 20px rgba(92,61,30,0.07);border:1.5px solid transparent;transition:all .25s;}
    .product-card:hover{transform:translateY(-4px);box-shadow:0 12px 32px rgba(92,61,30,0.13);border-color:var(--warm);}

    /* IMAGE AREA — fixed aspect ratio, proper fit */
    .product-img{width:100%;aspect-ratio:1/1;background:linear-gradient(135deg,var(--warm),var(--cream));display:flex;align-items:center;justify-content:center;font-size:4rem;position:relative;overflow:hidden;}
    .product-img img{width:100%;height:100%;object-fit:cover;object-position:center;display:block;}
    .stock-badge{position:absolute;top:0.7rem;left:0.7rem;background:var(--accent);color:white;font-size:0.68rem;font-weight:700;padding:0.2rem 0.6rem;border-radius:50px;text-transform:uppercase;}
    .stock-badge.out{background:#dc3545;}

    /* Image count badge */
    .img-count{position:absolute;top:0.7rem;right:0.7rem;background:rgba(0,0,0,0.55);color:white;font-size:0.68rem;font-weight:700;padding:0.2rem 0.5rem;border-radius:50px;}

    .product-info{padding:1.2rem;}
    .product-cat{font-size:0.7rem;color:var(--accent);font-weight:700;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.3rem;}
    .product-name{font-weight:700;color:var(--text);font-size:0.95rem;margin-bottom:0.3rem;line-height:1.3;}
    .product-price{font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:900;color:var(--brown);margin-bottom:0.3rem;}
    .product-stock{font-size:0.75rem;color:var(--muted);margin-bottom:1rem;}
    .product-actions{display:flex;gap:0.5rem;}
    .btn-edit{flex:1;padding:0.5rem;background:linear-gradient(135deg,#cce5ff,#b3d7ff);color:#004085;border:none;border-radius:8px;font-size:0.8rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s;}
    .btn-edit:hover{background:linear-gradient(135deg,#004085,#002d5e);color:white;transform:translateY(-1px);}
    .btn-delete{flex:1;padding:0.5rem;background:linear-gradient(135deg,#f8d7da,#f5b8bc);color:#721c24;border:none;border-radius:8px;font-size:0.8rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s;text-decoration:none;text-align:center;display:flex;align-items:center;justify-content:center;}
    .btn-delete:hover{background:linear-gradient(135deg,#721c24,#5a1520);color:white;transform:translateY(-1px);}
    .empty-state{text-align:center;padding:5rem 2rem;color:var(--muted);background:var(--white);border-radius:22px;border:1.5px dashed var(--warm);}

    /* EDIT MODAL */
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:200;display:none;align-items:center;justify-content:center;padding:1rem;backdrop-filter:blur(4px);}
    .modal-overlay.open{display:flex;}
    .modal{background:var(--white);border-radius:24px;padding:2rem;width:100%;max-width:580px;max-height:92vh;overflow-y:auto;box-shadow:0 24px 80px rgba(0,0,0,0.3);animation:slideUp .3s ease;}
    @keyframes slideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
    .modal-title{font-family:'Playfair Display',serif;font-size:1.4rem;color:var(--brown);margin-bottom:1.5rem;padding-bottom:0.8rem;border-bottom:1.5px solid var(--warm);display:flex;justify-content:space-between;align-items:center;}
    .modal-close{background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--muted);}
    .modal-close:hover{color:var(--accent);}

    /* Edit image slots */
    .edit-img-label{font-size:0.82rem;font-weight:600;color:var(--brown);margin-bottom:0.5rem;display:block;}
    .edit-slots{display:grid;grid-template-columns:repeat(4,1fr);gap:0.6rem;margin-bottom:1.2rem;}
    .edit-slot{position:relative;aspect-ratio:1/1;border:2px dashed var(--warm);border-radius:10px;overflow:hidden;cursor:pointer;background:var(--cream);display:flex;align-items:center;justify-content:center;font-size:1.2rem;transition:all .2s;}
    .edit-slot:hover{border-color:var(--accent);background:#fff5f0;}
    .edit-slot.filled{border-style:solid;border-color:var(--accent);}
    .edit-slot input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;}
    .edit-slot img.eprev{width:100%;height:100%;object-fit:cover;object-position:center;display:none;position:absolute;inset:0;}
    .edit-slot.filled img.eprev{display:block;}
    .edit-slot.filled .eslot-ph{display:none;}
    .eslot-del{position:absolute;top:2px;right:2px;width:18px;height:18px;background:#dc3545;color:white;border:none;border-radius:50%;font-size:0.6rem;cursor:pointer;display:none;align-items:center;justify-content:center;z-index:5;font-weight:700;}
    .edit-slot.filled .eslot-del{display:flex;}
    .edit-note{font-size:0.72rem;color:var(--muted);margin-bottom:1rem;}

    .form-group{margin-bottom:1.1rem;}
    .form-group label{display:block;font-size:0.82rem;font-weight:600;color:var(--brown);margin-bottom:0.4rem;}
    .form-group input,.form-group select,.form-group textarea{width:100%;padding:0.75rem 1rem;border:1.5px solid var(--warm);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.92rem;color:var(--text);background:var(--cream);outline:none;transition:all .2s;}
    .form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(212,98,42,0.1);background:var(--white);}
    .form-group textarea{resize:vertical;min-height:80px;}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
    .btn-update{width:100%;padding:0.85rem;background:var(--accent);color:white;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.95rem;font-weight:700;cursor:pointer;transition:all .2s;margin-top:0.5rem;}
    .btn-update:hover{background:#c0551f;transform:translateY(-1px);}

    /* CROP */
    .crop-modal{position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:500;display:none;align-items:center;justify-content:center;padding:1rem;}
    .crop-modal.open{display:flex;}
    .crop-box{background:var(--dark);border-radius:24px;padding:1.5rem;width:100%;max-width:600px;}
    .crop-title{font-family:'Playfair Display',serif;font-size:1.1rem;color:var(--white);margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center;}
    .crop-container{width:100%;height:320px;overflow:hidden;border-radius:12px;background:#000;}
    .crop-container img{max-width:100%;display:block;}
    .crop-tools{display:flex;align-items:center;justify-content:space-between;margin-top:1rem;flex-wrap:wrap;gap:0.8rem;}
    .crop-btns{display:flex;gap:0.5rem;}
    .crop-tool-btn{padding:0.4rem 0.8rem;border-radius:50px;font-size:0.78rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;border:1.5px solid rgba(255,255,255,0.2);background:rgba(255,255,255,0.08);color:white;transition:all .2s;}
    .asp-btns{display:flex;gap:0.4rem;}
    .asp-btn{padding:0.32rem 0.65rem;border-radius:50px;font-size:0.72rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;border:1.5px solid rgba(255,255,255,0.2);background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.7);transition:all .2s;}
    .asp-btn.active,.asp-btn:hover{background:var(--accent);border-color:var(--accent);color:white;}
    .btn-crop-done{padding:0.55rem 1.6rem;background:var(--accent);color:white;border:none;border-radius:50px;font-size:0.88rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;}
    .btn-crop-cancel{padding:0.55rem 1rem;background:rgba(255,255,255,0.1);color:white;border:1.5px solid rgba(255,255,255,0.2);border-radius:50px;font-size:0.88rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;}

    .toast{position:fixed;bottom:2rem;right:2rem;background:var(--brown);color:white;padding:0.9rem 1.6rem;border-radius:14px;font-size:0.88rem;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,0.25);transform:translateY(120px);opacity:0;transition:all 0.35s;z-index:999;}
    .toast.show{transform:translateY(0);opacity:1;}

    @media(max-width:600px){
      nav{height:auto;flex-wrap:wrap;padding:0.6rem 4vw;gap:0.5rem;}
      .logo{font-size:1.3rem;}.nav-right{gap:0.4rem;}.nav-link{padding:0.35rem 0.7rem;font-size:0.75rem;}
      .main{margin-top:100px;padding:1.5rem 4vw;}
      .form-row{grid-template-columns:1fr;}.crop-container{height:220px;}
      .products-grid{grid-template-columns:repeat(2,1fr);gap:0.8rem;}
      .edit-slots{grid-template-columns:repeat(4,1fr);}
    }
    @media(max-width:380px){.products-grid{grid-template-columns:1fr;}}
  </style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="logo">Trenzo<span>Kart</span></a>
  <div class="nav-right">
    <a href="add_product.php" class="nav-link accent">+ Add Product</a>
    <a href="dashboard.php" class="nav-link">← Dashboard</a>
    <a href="logout.php" class="nav-link">Logout</a>
  </div>
</nav>

<div class="main">
  <h1 class="page-title">📦 My Products</h1>
  <p class="page-sub">Manage your product listings</p>

  <?php if (!empty($success)): ?><div class="msg success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if (!empty($error)): ?><div class="msg error">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <?php if (empty($products)): ?>
    <div class="empty-state">
      <span style="font-size:4rem;display:block;margin-bottom:1rem;">📦</span>
      <p>No products yet! <a href="add_product.php" style="color:var(--accent);font-weight:700;">Add your first product →</a></p>
    </div>
  <?php else: ?>
  <div class="products-grid">
    <?php foreach ($products as $p):
      $imgs = array_values(array_filter(array_map('trim', explode(',', $p['image'] ?? ''))));
      $first = $imgs[0] ?? '';
      $img_count = count($imgs);
    ?>
    <div class="product-card">
      <div class="product-img">
        <?php if ($first): ?>
          <img src="<?= img_url($first) ?>" alt="<?= htmlspecialchars($p['name']) ?>"
               onerror="this.style.display='none'"/>
        <?php else: ?>
          <?= $icons[$p['category']] ?? '📦' ?>
        <?php endif; ?>
        <?php if ($p['stock'] == 0): ?>
          <span class="stock-badge out">Out of Stock</span>
        <?php elseif ($p['stock'] < 5): ?>
          <span class="stock-badge">Low Stock</span>
        <?php endif; ?>
        <?php if ($img_count > 1): ?>
          <span class="img-count">📷 <?= $img_count ?></span>
        <?php endif; ?>
      </div>
      <div class="product-info">
        <div class="product-cat"><?= htmlspecialchars($p['category']) ?></div>
        <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
        <div class="product-price">₹<?= number_format($p['price'],2) ?></div>
        <div class="product-stock">Stock: <?= $p['stock'] ?> units</div>
        <div class="product-actions">
          <button class="btn-edit" onclick='openEdit(<?= json_encode($p) ?>)'>✏️ Edit</button>
          <a href="products.php?delete=<?= $p['id'] ?>" class="btn-delete" onclick="return confirm('Delete this product?')">🗑️ Delete</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-title">
      ✏️ Edit Product
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <form method="POST" id="editForm">
      <input type="hidden" name="edit_id" id="edit_id"/>
      <?php for ($i = 1; $i <= 4; $i++): ?>
        <input type="hidden" name="img_<?= $i ?>" id="eImgData<?= $i ?>"/>
      <?php endfor; ?>

      <!-- Image slots -->
      <label class="edit-img-label">📸 Product Images (click slot to change)</label>
      <div class="edit-slots">
        <?php for ($i = 1; $i <= 4; $i++): ?>
        <div class="edit-slot" id="eSlot<?= $i ?>">
          <input type="file" accept="image/*" onchange="eOnPick(this,<?= $i ?>)" id="eFile<?= $i ?>"/>
          <img class="eprev" id="ePrev<?= $i ?>" src="" alt=""/>
          <span class="eslot-ph">+</span>
          <button type="button" class="eslot-del" onclick="eDelSlot(<?= $i ?>)">✕</button>
        </div>
        <?php endfor; ?>
      </div>
      <div class="edit-note">💡 Leave empty to keep existing images. Upload new to replace all.</div>

      <div class="form-group">
        <label>Product Name</label>
        <input type="text" name="name" id="edit_name" required/>
      </div>
      <div class="form-group">
        <label>Category</label>
        <select name="category" id="edit_category">
          <option value="Electronics">📱 Electronics</option>
          <option value="Clothes">👕 Clothes</option>
          <option value="Grocery">🥗 Grocery</option>
          <option value="Food & Beverages">🥤 Food & Beverages</option>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Price (₹)</label><input type="number" name="price" id="edit_price" min="1" step="0.01" required/></div>
        <div class="form-group"><label>Stock</label><input type="number" name="stock" id="edit_stock" min="0" required/></div>
      </div>
      <div class="form-group"><label>Description</label><textarea name="description" id="edit_desc"></textarea></div>
      <button type="submit" class="btn-update">💾 Save Changes →</button>
    </form>
  </div>
</div>

<!-- Crop Modal -->
<div class="crop-modal" id="cropModal">
  <div class="crop-box">
    <div class="crop-title">✂️ Crop Image <span style="font-size:0.75rem;color:rgba(255,255,255,0.5);font-family:'DM Sans',sans-serif;" id="cropInd2">Slot 1</span></div>
    <div class="crop-container"><img id="cropImg2" src=""/></div>
    <div class="crop-tools">
      <div class="asp-btns">
        <button class="asp-btn active" onclick="setAsp2(1,1,this)">1:1</button>
        <button class="asp-btn" onclick="setAsp2(4,3,this)">4:3</button>
        <button class="asp-btn" onclick="setAsp2(16,9,this)">16:9</button>
        <button class="asp-btn" onclick="setAsp2(NaN,NaN,this)">Free</button>
      </div>
      <div class="crop-btns">
        <button class="crop-tool-btn" onclick="cr2.rotate(-90)">↺</button>
        <button class="crop-tool-btn" onclick="cr2.scaleX(-(cr2.getData().scaleX||1))">↔</button>
        <button class="crop-tool-btn" onclick="cr2.reset()">↩</button>
      </div>
      <div style="display:flex;gap:0.6rem;">
        <button class="btn-crop-cancel" onclick="closeCrop2()">Cancel</button>
        <button class="btn-crop-done" onclick="applyCrop2()">✅ Done</button>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script>
let cr2 = null, eActiveSlot = 1;

function openEdit(p) {
  document.getElementById('edit_id').value       = p.id;
  document.getElementById('edit_name').value     = p.name;
  document.getElementById('edit_category').value = p.category;
  document.getElementById('edit_price').value    = p.price;
  document.getElementById('edit_stock').value    = p.stock;
  document.getElementById('edit_desc').value     = p.description || '';

  // Clear all slots first
  for (let i = 1; i <= 4; i++) {
    document.getElementById('ePrev'+i).src = '';
    document.getElementById('eSlot'+i).classList.remove('filled');
    document.getElementById('eImgData'+i).value = '';
    document.getElementById('eFile'+i).value = '';
  }

  // Load existing images into slots
  if (p.image) {
    const imgs = p.image.split(',').map(s => s.trim()).filter(Boolean);
    const base = '<?= rtrim((isset($_SERVER["HTTPS"])&&$_SERVER["HTTPS"]==="on"?"https":"http")."://".$_SERVER["HTTP_HOST"]."/", "/") ?>';
    imgs.forEach((img, idx) => {
      if (idx < 4) {
        const slot = idx + 1;
        const cleanImg = img.replace(/^\/+/, '');
        document.getElementById('ePrev'+slot).src = base + '/' + cleanImg;
        document.getElementById('eSlot'+slot).classList.add('filled');
      }
    });
  }

  document.getElementById('editModal').classList.add('open');
}

function closeModal() {
  document.getElementById('editModal').classList.remove('open');
}

document.getElementById('editModal').addEventListener('click', e => { if (e.target === document.getElementById('editModal')) closeModal(); });

// Edit slot file pick
function eOnPick(input, n) {
  const file = input.files[0];
  if (!file) return;
  if (file.size > 5*1024*1024) { alert('Max 5MB!'); return; }
  eActiveSlot = n;
  document.getElementById('cropInd2').textContent = 'Slot ' + n;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('cropImg2').src = e.target.result;
    document.getElementById('cropModal').classList.add('open');
    if (cr2) { cr2.destroy(); cr2 = null; }
    setTimeout(() => {
      cr2 = new Cropper(document.getElementById('cropImg2'), {
        aspectRatio:1, viewMode:2, dragMode:'move', autoCropArea:0.9,
        restore:false, guides:true, center:true, highlight:false,
        cropBoxMovable:true, cropBoxResizable:true
      });
    }, 100);
  };
  reader.readAsDataURL(file);
}

function setAsp2(w, h, btn) {
  document.querySelectorAll('.asp-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  if (cr2) cr2.setAspectRatio(isNaN(w) ? NaN : w/h);
}

function applyCrop2() {
  if (!cr2) return;
  const canvas = cr2.getCroppedCanvas({width:900,height:900,imageSmoothingQuality:'high'});
  const url = canvas.toDataURL('image/jpeg', 0.90);
  document.getElementById('ePrev'+eActiveSlot).src = url;
  document.getElementById('eSlot'+eActiveSlot).classList.add('filled');
  document.getElementById('eImgData'+eActiveSlot).value = url;
  closeCrop2();
}

function closeCrop2() {
  document.getElementById('cropModal').classList.remove('open');
  if (cr2) { cr2.destroy(); cr2 = null; }
  document.getElementById('eFile'+eActiveSlot).value = '';
}

function eDelSlot(n) {
  document.getElementById('ePrev'+n).src = '';
  document.getElementById('eSlot'+n).classList.remove('filled');
  document.getElementById('eImgData'+n).value = '';
  document.getElementById('eFile'+n).value = '';
}

<?php if (!empty($success) || !empty($error)): ?>
window.onload = () => {
  const t = document.getElementById('toast');
  t.textContent = '<?= !empty($success) ? "✅ ".$success : "❌ ".$error ?>';
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
};
<?php endif; ?>
</script>
</body>
</html>