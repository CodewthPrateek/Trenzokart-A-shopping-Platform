<?php
require 'config.php';

$product_id = intval($_GET['id'] ?? 0);
if (!$product_id) { header("Location: index.php"); exit(); }

$product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT p.*, v.shop_name as vendor_shop FROM products p LEFT JOIN vendors v ON p.vendor_id = v.id WHERE p.id='$product_id'"));
if (!$product) { header("Location: index.php"); exit(); }

// Similar products
$similar_result = mysqli_query($conn, "SELECT * FROM products WHERE category='{$product['category']}' AND id != '$product_id' ORDER BY id DESC LIMIT 6");
$similar = [];
while ($row = mysqli_fetch_assoc($similar_result)) { $similar[] = $row; }

$cart_count = 0;
$user_id    = $_SESSION['user_id'] ?? null;
$user_name  = $_SESSION['user_name'] ?? null;

if ($user_id) {
    $cart_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(quantity) as t FROM cart WHERE user_id='$user_id'"))['t'] ?? 0;
} elseif (isset($_SESSION['guest_cart'])) {
    $cart_count = array_sum($_SESSION['guest_cart']);
}

// ── SUBMIT REVIEW ──
$review_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!$user_id) {
        $review_msg = 'error:Login karke review do!';
    } else {
        $rating  = intval($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $media   = ''; // base64 image/video

        if ($rating < 1 || $rating > 5) {
            $review_msg = 'error:Rating 1-5 ke beech honi chahiye!';
        } elseif (empty($comment)) {
            $review_msg = 'error:Review comment daalo!';
        } else {
            // Handle media upload
            if (!empty($_POST['review_media'])) {
                $media_data = $_POST['review_media'];
                // Validate it's a base64 image/video
                if (preg_match('/^data:(image|video)\/(jpeg|jpg|png|webp|mp4|mov);base64,/', $media_data)) {
                    $media = $media_data;
                }
            }

            // Check already reviewed
            $already = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM product_reviews WHERE product_id='$product_id' AND user_id='$user_id'"));
            if ($already) {
                // Update existing
                $comment_esc = mysqli_real_escape_string($conn, $comment);
                $media_esc   = mysqli_real_escape_string($conn, $media);
                mysqli_query($conn, "UPDATE product_reviews SET rating='$rating', comment='$comment_esc', media='$media_esc', updated_at=NOW() WHERE product_id='$product_id' AND user_id='$user_id'");
                $review_msg = 'success:Review update ho gayi!';
            } else {
                $comment_esc = mysqli_real_escape_string($conn, $comment);
                $media_esc   = mysqli_real_escape_string($conn, $media);
                mysqli_query($conn, "INSERT INTO product_reviews (product_id, user_id, rating, comment, media) VALUES ('$product_id','$user_id','$rating','$comment_esc','$media_esc')");
                $review_msg = 'success:Review submit ho gayi! Shukriya 🎉';
            }
        }
    }
}

// Fetch reviews
$reviews_result = mysqli_query($conn, "SELECT pr.*, u.name as user_name FROM product_reviews pr JOIN users u ON pr.user_id = u.id WHERE pr.product_id='$product_id' ORDER BY pr.created_at DESC");
$reviews = [];
while ($row = mysqli_fetch_assoc($reviews_result)) { $reviews[] = $row; }

// Average rating
$avg_rating = 0; $total_reviews = count($reviews);
if ($total_reviews > 0) {
    $avg_rating = array_sum(array_column($reviews, 'rating')) / $total_reviews;
}

// Rating distribution
$dist = [5=>0,4=>0,3=>0,2=>0,1=>0];
foreach ($reviews as $r) { $dist[$r['rating']]++; }

// User's existing review
$my_review = null;
if ($user_id) {
    foreach ($reviews as $r) {
        if ($r['user_id'] == $user_id) { $my_review = $r; break; }
    }
}

// Product images — comma separated
$product_images = array_filter(array_map('trim', explode(',', $product['image'] ?? '')));
if (empty($product_images)) $product_images = []; // will show emoji

$icons = ['Clothes'=>'👕','Electronics'=>'📱','Grocery'=>'🥗','Food & Beverages'=>'🥤'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — <?= htmlspecialchars($product['name']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{--cream:#f5efe6;--warm:#e8d5b7;--brown:#5c3d1e;--accent:#d4622a;--accent2:#e8a045;--text:#2d1a0a;--muted:#8a6a4a;--white:#fffdf8;--dark:#1a0f02;}
    body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--text);}

    /* NAV */
    nav{position:fixed;top:0;left:0;right:0;z-index:100;background:rgba(245,239,230,0.97);backdrop-filter:blur(12px);border-bottom:1px solid var(--warm);display:flex;align-items:center;justify-content:space-between;padding:0 5vw;height:68px;box-shadow:0 2px 20px rgba(92,61,30,0.08);}
    .logo{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:900;color:var(--brown);text-decoration:none;}
    .logo span{color:var(--accent);}
    .nav-right{display:flex;align-items:center;gap:0.8rem;}
    .nav-btn{display:flex;align-items:center;gap:0.4rem;padding:0.5rem 1rem;border-radius:50px;font-family:'DM Sans',sans-serif;font-size:0.85rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all .2s;}
    .btn-cart{background:var(--warm);color:var(--brown);border:none;}
    .btn-cart:hover{background:var(--accent);color:white;}
    .btn-back{background:none;color:var(--muted);border:1.5px solid var(--warm);}
    .btn-back:hover{border-color:var(--accent);color:var(--accent);}

    .main{margin-top:88px;padding:2rem 5vw;max-width:1100px;margin-left:auto;margin-right:auto;}

    /* BREADCRUMB */
    .breadcrumb{display:flex;align-items:center;gap:0.4rem;font-size:0.82rem;color:var(--muted);margin-bottom:2rem;flex-wrap:wrap;}
    .breadcrumb a{color:var(--muted);text-decoration:none;}
    .breadcrumb a:hover{color:var(--accent);}
    .breadcrumb span{color:var(--accent);}

    /* PRODUCT LAYOUT */
    .product-layout{display:grid;grid-template-columns:1fr 1fr;gap:3rem;margin-bottom:3rem;}

    /* ── IMAGE SLIDER ── */
    .slider-wrap{position:relative;border-radius:24px;overflow:hidden;background:var(--white);box-shadow:0 8px 32px rgba(92,61,30,0.1);}
    .slider-main{position:relative;aspect-ratio:1/1;overflow:hidden;}
    .slide{display:none;width:100%;height:100%;}
    .slide.active{display:block;}
    .slide img{width:100%;height:100%;object-fit:cover;}
    .slide-emoji{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:8rem;background:linear-gradient(135deg,var(--warm),var(--cream));}
    .slider-arrow{position:absolute;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.9);border:none;border-radius:50%;width:40px;height:40px;font-size:1.2rem;cursor:pointer;z-index:5;box-shadow:0 2px 10px rgba(0,0,0,0.15);transition:all .2s;display:flex;align-items:center;justify-content:center;}
    .slider-arrow:hover{background:var(--accent);color:white;}
    .slider-prev{left:0.8rem;}
    .slider-next{right:0.8rem;}
    .slider-dots{display:flex;justify-content:center;gap:0.4rem;padding:0.8rem;}
    .slider-dot{width:8px;height:8px;border-radius:50%;background:var(--warm);border:none;cursor:pointer;transition:all .2s;}
    .slider-dot.active{background:var(--accent);width:20px;border-radius:4px;}
    .slider-thumbs{display:flex;gap:0.5rem;padding:0 0.8rem 0.8rem;overflow-x:auto;}
    .slider-thumb{width:64px;height:64px;border-radius:10px;overflow:hidden;cursor:pointer;border:2px solid transparent;flex-shrink:0;transition:all .2s;}
    .slider-thumb.active{border-color:var(--accent);}
    .slider-thumb img{width:100%;height:100%;object-fit:cover;}
    .slider-thumb-emoji{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:1.8rem;background:var(--warm);}
    .stock-badge-big{position:absolute;top:1rem;left:1rem;background:var(--accent);color:white;font-size:0.78rem;font-weight:700;padding:0.3rem 0.8rem;border-radius:50px;text-transform:uppercase;z-index:3;}
    .out-of-stock-overlay{position:absolute;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:4;}
    .out-of-stock-overlay span{background:#dc3545;color:white;font-size:1.2rem;font-weight:700;padding:0.6rem 1.5rem;border-radius:50px;}

    /* RATING STARS DISPLAY */
    .stars-display{display:flex;gap:2px;}
    .star{font-size:1.1rem;color:#ddd;}
    .star.filled{color:#f5a623;}
    .star.half{color:#f5a623;opacity:0.6;}

    /* PRODUCT INFO */
    .product-info-section{display:flex;flex-direction:column;}
    .product-category-tag{font-size:0.78rem;color:var(--accent);font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:0.5rem;}
    .product-name-big{font-family:'Playfair Display',serif;font-size:2rem;font-weight:900;color:var(--brown);line-height:1.2;margin-bottom:0.5rem;}
    .rating-summary{display:flex;align-items:center;gap:0.6rem;margin-bottom:0.8rem;}
    .avg-num{font-size:1.1rem;font-weight:700;color:var(--brown);}
    .review-count{font-size:0.82rem;color:var(--muted);}
    .vendor-tag{font-size:0.82rem;color:var(--muted);margin-bottom:1rem;}
    .vendor-tag span{color:var(--accent);font-weight:600;}
    .product-price-big{font-family:'Playfair Display',serif;font-size:2.2rem;font-weight:900;color:var(--brown);margin-bottom:0.5rem;}
    .stock-info{font-size:0.85rem;margin-bottom:1.5rem;}
    .stock-info.in-stock{color:#28a745;font-weight:600;}
    .stock-info.low-stock{color:#e65100;font-weight:600;}
    .stock-info.out-stock{color:#dc3545;font-weight:600;}
    .product-desc{font-size:0.92rem;color:var(--muted);line-height:1.7;margin-bottom:1.5rem;padding:1rem;background:var(--cream);border-radius:12px;border-left:3px solid var(--accent);}
    .qty-section{margin-bottom:1.5rem;}
    .qty-label{font-size:0.82rem;font-weight:700;color:var(--brown);margin-bottom:0.6rem;display:block;}
    .qty-wrap{display:flex;align-items:center;gap:0;border:1.5px solid var(--warm);border-radius:12px;overflow:hidden;width:fit-content;background:var(--white);}
    .qty-btn{width:44px;height:44px;background:var(--cream);border:none;font-size:1.3rem;font-weight:700;cursor:pointer;color:var(--brown);transition:all .2s;display:flex;align-items:center;justify-content:center;}
    .qty-btn:hover{background:var(--accent);color:white;}
    .qty-input{width:60px;height:44px;border:none;border-left:1.5px solid var(--warm);border-right:1.5px solid var(--warm);text-align:center;font-family:'DM Sans',sans-serif;font-size:1rem;font-weight:700;color:var(--text);background:var(--white);outline:none;}
    .action-btns{display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;margin-bottom:1.5rem;}
    .btn-add-cart-big{padding:0.9rem;background:var(--cream);color:var(--brown);border:2px solid var(--warm);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:0.95rem;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:0.5rem;}
    .btn-add-cart-big:hover{background:var(--brown);color:white;border-color:var(--brown);transform:translateY(-2px);}
    .btn-buy-now{padding:0.9rem;background:var(--accent);color:white;border:none;border-radius:12px;font-family:'DM Sans',sans-serif;font-size:0.95rem;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:0.5rem;}
    .btn-buy-now:hover{background:#c0551f;transform:translateY(-2px);box-shadow:0 8px 24px rgba(212,98,42,0.35);}
    .btn-disabled{opacity:0.5;cursor:not-allowed !important;}
    .features-row{display:grid;grid-template-columns:repeat(3,1fr);gap:0.8rem;}
    .feature-item{background:var(--cream);border-radius:12px;padding:0.8rem;text-align:center;border:1px solid var(--warm);}
    .feature-icon{font-size:1.5rem;display:block;margin-bottom:0.3rem;}
    .feature-text{font-size:0.72rem;color:var(--muted);font-weight:600;}

    /* ── REVIEWS SECTION ── */
    .reviews-section{background:var(--white);border-radius:24px;padding:2rem;margin-bottom:3rem;box-shadow:0 4px 20px rgba(92,61,30,0.06);}
    .reviews-title{font-family:'Playfair Display',serif;font-size:1.5rem;color:var(--brown);margin-bottom:1.5rem;}
    .rating-overview{display:grid;grid-template-columns:auto 1fr;gap:2rem;margin-bottom:2rem;padding-bottom:2rem;border-bottom:1.5px solid var(--warm);}
    .rating-big-num{text-align:center;}
    .rating-number{font-family:'Playfair Display',serif;font-size:4rem;font-weight:900;color:var(--brown);line-height:1;}
    .rating-stars-big{font-size:1.6rem;margin:0.3rem 0;}
    .rating-total{font-size:0.82rem;color:var(--muted);}
    .rating-bars{display:flex;flex-direction:column;gap:0.5rem;justify-content:center;}
    .rating-bar-row{display:flex;align-items:center;gap:0.6rem;font-size:0.82rem;}
    .bar-label{width:16px;text-align:right;color:var(--brown);font-weight:600;}
    .bar-track{flex:1;height:8px;background:var(--warm);border-radius:4px;overflow:hidden;}
    .bar-fill{height:100%;background:var(--accent2);border-radius:4px;transition:width 0.8s ease;}
    .bar-count{width:24px;color:var(--muted);}

    /* REVIEW FORM */
    .review-form-wrap{background:var(--cream);border-radius:16px;padding:1.5rem;margin-bottom:1.5rem;border:1.5px solid var(--warm);}
    .review-form-title{font-weight:700;color:var(--brown);margin-bottom:1rem;font-size:0.95rem;}
    .star-picker{display:flex;gap:0.3rem;margin-bottom:1rem;}
    .star-pick{font-size:2rem;cursor:pointer;color:#ddd;transition:color .15s;background:none;border:none;padding:0;}
    .star-pick:hover,.star-pick.selected{color:#f5a623;}
    .review-textarea{width:100%;padding:0.8rem 1rem;border:1.5px solid var(--warm);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.9rem;color:var(--text);background:var(--white);outline:none;resize:vertical;min-height:90px;transition:border-color .2s;}
    .review-textarea:focus{border-color:var(--accent);background:var(--white);}

    /* MEDIA UPLOAD */
    .media-section{margin:0.8rem 0;}
    .media-btns{display:flex;gap:0.6rem;flex-wrap:wrap;margin-bottom:0.8rem;}
    .media-btn{display:flex;align-items:center;gap:0.4rem;padding:0.5rem 1rem;border:1.5px solid var(--warm);border-radius:50px;font-size:0.82rem;font-weight:600;color:var(--brown);background:var(--white);cursor:pointer;transition:all .2s;}
    .media-btn:hover{border-color:var(--accent);color:var(--accent);}
    .media-preview{display:none;position:relative;margin-top:0.5rem;}
    .media-preview img,.media-preview video{max-width:200px;max-height:160px;border-radius:10px;border:2px solid var(--warm);}
    .media-remove{position:absolute;top:-8px;right:-8px;width:24px;height:24px;background:#dc3545;color:white;border:none;border-radius:50%;font-size:0.8rem;cursor:pointer;display:flex;align-items:center;justify-content:center;}
    #cameraStream{display:none;width:100%;max-width:300px;border-radius:10px;margin-top:0.5rem;}
    .camera-controls{display:none;gap:0.5rem;margin-top:0.5rem;}
    .btn-snap{padding:0.5rem 1rem;background:var(--accent);color:white;border:none;border-radius:8px;font-size:0.82rem;font-weight:600;cursor:pointer;}
    .btn-stop-cam{padding:0.5rem 1rem;background:var(--muted);color:white;border:none;border-radius:8px;font-size:0.82rem;cursor:pointer;}

    .btn-submit-review{width:100%;padding:0.8rem;background:var(--accent);color:white;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.95rem;font-weight:700;cursor:pointer;margin-top:0.5rem;transition:all .2s;}
    .btn-submit-review:hover{background:#c0551f;transform:translateY(-1px);}
    .review-msg{padding:0.7rem 1rem;border-radius:8px;font-size:0.88rem;margin-bottom:0.8rem;}
    .review-msg.success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
    .review-msg.error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
    .login-prompt{text-align:center;padding:1rem;background:var(--cream);border-radius:10px;font-size:0.88rem;color:var(--muted);}
    .login-prompt a{color:var(--accent);font-weight:600;text-decoration:none;}

    /* REVIEW CARDS */
    .review-card{padding:1.2rem 0;border-bottom:1px solid var(--warm);}
    .review-card:last-child{border-bottom:none;}
    .review-header{display:flex;align-items:center;gap:0.8rem;margin-bottom:0.5rem;}
    .review-avatar{width:36px;height:36px;border-radius:50%;background:var(--accent);color:white;font-size:0.9rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .reviewer-name{font-weight:600;font-size:0.9rem;color:var(--text);}
    .review-date{font-size:0.72rem;color:var(--muted);}
    .review-stars{font-size:0.9rem;margin-bottom:0.4rem;}
    .review-comment{font-size:0.88rem;color:var(--text);line-height:1.6;}
    .review-media{margin-top:0.7rem;}
    .review-media img{max-width:180px;max-height:140px;border-radius:10px;object-fit:cover;cursor:pointer;}
    .review-media video{max-width:240px;border-radius:10px;}
    .no-reviews{text-align:center;padding:2rem;color:var(--muted);}

    /* SIMILAR PRODUCTS */
    .section-title{font-family:'Playfair Display',serif;font-size:1.6rem;color:var(--brown);margin-bottom:1.5rem;}
    .products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1.2rem;}
    .product-card{background:var(--white);border-radius:18px;overflow:hidden;transition:transform .2s,box-shadow .2s;border:1.5px solid transparent;cursor:pointer;text-decoration:none;display:block;}
    .product-card:hover{transform:translateY(-5px);box-shadow:0 12px 32px rgba(92,61,30,0.12);border-color:var(--warm);}
    .card-img{width:100%;height:180px;background:linear-gradient(135deg,var(--warm),var(--cream));display:flex;align-items:center;justify-content:center;font-size:3.5rem;overflow:hidden;}
    .card-img img{width:100%;height:100%;object-fit:cover;}
    .card-info{padding:1rem;}
    .card-cat{font-size:0.7rem;color:var(--accent);font-weight:700;text-transform:uppercase;margin-bottom:0.2rem;}
    .card-name{font-weight:600;font-size:0.88rem;color:var(--text);margin-bottom:0.4rem;line-height:1.3;}
    .card-price{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;color:var(--brown);}
    .card-btn{width:100%;margin-top:0.6rem;padding:0.5rem;background:var(--cream);color:var(--brown);border:1.5px solid var(--warm);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:0.82rem;font-weight:600;cursor:pointer;transition:all .2s;}
    .card-btn:hover{background:var(--accent);color:white;border-color:var(--accent);}

    /* IMAGE LIGHTBOX */
    .lightbox{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.92);z-index:999;align-items:center;justify-content:center;}
    .lightbox.open{display:flex;}
    .lightbox img{max-width:90vw;max-height:90vh;border-radius:12px;object-fit:contain;}
    .lightbox-close{position:absolute;top:1rem;right:1.5rem;color:white;font-size:2rem;cursor:pointer;background:none;border:none;}

    .toast{position:fixed;bottom:2rem;right:2rem;background:var(--brown);color:white;padding:0.8rem 1.5rem;border-radius:12px;font-size:0.88rem;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,0.2);transform:translateY(100px);opacity:0;transition:all 0.3s;z-index:999;}
    .toast.show{transform:translateY(0);opacity:1;}

    /* RESPONSIVE */
    @media(max-width:768px){
      .product-layout{grid-template-columns:1fr;gap:1.5rem;}
      .product-name-big{font-size:1.5rem;}
      .product-price-big{font-size:1.8rem;}
      .features-row{grid-template-columns:repeat(3,1fr);}
      .rating-overview{grid-template-columns:1fr;gap:1rem;}
      .rating-big-num{display:flex;align-items:center;gap:1rem;}
      .rating-number{font-size:3rem;}
    }
    @media(max-width:600px){
      nav{height:auto;flex-wrap:wrap;padding:0.6rem 4vw;gap:0.5rem;}
      .logo{font-size:1.4rem;}
      .nav-right{gap:0.5rem;}
      .nav-btn{padding:0.4rem 0.7rem;font-size:0.78rem;}
      .main{margin-top:100px;padding:1rem 4vw;}
      .action-btns{grid-template-columns:1fr;}
      .features-row{grid-template-columns:1fr 1fr 1fr;}
      .products-grid{grid-template-columns:repeat(2,1fr);gap:0.8rem;}
      .reviews-section{padding:1.2rem;}
      .slider-thumb{width:52px;height:52px;}
    }
    @media(max-width:380px){
      .features-row{grid-template-columns:1fr 1fr;}
      .media-btns{flex-direction:column;}
    }
  
    /* HAMBURGER */
    .hamburger { display: none; flex-direction: column; gap: 5px; cursor: pointer; background: none; border: none; padding: 6px; z-index: 201; }
    .hamburger span { display: block; width: 24px; height: 2.5px; background: var(--brown); border-radius: 4px; transition: all 0.3s; }
    .hamburger.open span:nth-child(1) { transform: translateY(7.5px) rotate(45deg); }
    .hamburger.open span:nth-child(2) { opacity: 0; }
    .hamburger.open span:nth-child(3) { transform: translateY(-7.5px) rotate(-45deg); }
    .nav-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 199; }
    .nav-overlay.open { display: block; }

    @media(max-width:768px){
      .hamburger{display:flex;}
      .nav-right{position:fixed;top:0;right:-280px;width:260px;height:100vh;background:var(--white);flex-direction:column;align-items:flex-start;padding:80px 1.5rem 2rem;gap:0.7rem;z-index:200;box-shadow:-8px 0 30px rgba(92,61,30,0.15);transition:right 0.3s ease;overflow-y:auto;}
      .nav-right.open{right:0;}
      .nav-btn{width:100%;justify-content:flex-start;border-radius:12px;padding:0.7rem 1rem;}
      .main{padding:1rem 4vw;}
      .product-layout{flex-direction:column;}
      .slider-thumb{width:52px;height:52px;}
    }
    @media(max-width:480px){
      .main{margin-top:68px;}
      .product-info{padding:1rem;}
    }

</style>
</head>
<body>
<div class="nav-overlay" id="navOverlay" onclick="closeNav()"></div>
<nav>
  <a href="index.php" class="logo">Trenzo<span>Kart</span></a>
  <button class="hamburger" id="hamburger" onclick="toggleNav()" aria-label="Menu"><span></span><span></span><span></span></button>
    <div class="nav-right" id="navRight">
    <a href="index.php" class="nav-btn btn-back">← Home</a>
    <a href="cart.php" class="nav-btn btn-cart">
      🛒 Cart
      <?php if ($cart_count > 0): ?>
        <span style="background:#d4622a;color:white;border-radius:50%;padding:1px 7px;font-size:0.75rem;margin-left:4px;" id="cartCount"><?= $cart_count ?></span>
      <?php else: ?>
        <span style="display:none;background:#d4622a;color:white;border-radius:50%;padding:1px 7px;font-size:0.75rem;margin-left:4px;" id="cartCount">0</span>
      <?php endif; ?>
    </a>
    <?php if ($user_name): ?>
      <a href="my_orders.php" class="nav-btn btn-back">📦 Orders</a>
    <?php else: ?>
      <a href="login.php" class="nav-btn btn-cart">Login</a>
    <?php endif; ?>
  </div>
</nav>

<div class="main">
  <div class="breadcrumb">
    <a href="index.php">🏠 Home</a> ›
    <a href="search_results.php?q=<?= urlencode($product['category']) ?>"><?= htmlspecialchars($product['category']) ?></a> ›
    <span><?= htmlspecialchars($product['name']) ?></span>
  </div>

  <div class="product-layout">
    <!-- ══ IMAGE SLIDER ══ -->
    <div>
      <div class="slider-wrap">
        <div class="slider-main" id="sliderMain">
          <?php if (!empty($product_images)): ?>
            <?php foreach ($product_images as $i => $img): ?>
            <div class="slide <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>">
              <img src="<?= htmlspecialchars(img_url($img)) ?>" alt="<?= htmlspecialchars($product['name']) ?>" onclick="openLightbox(this.src)" onerror="this.style.display='none'"/>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="slide active">
              <div class="slide-emoji"><?= $icons[$product['category']] ?? '📦' ?></div>
            </div>
          <?php endif; ?>

          <?php if ($product['stock'] > 0 && $product['stock'] < 5): ?>
            <span class="stock-badge-big">Only <?= $product['stock'] ?> left!</span>
          <?php endif; ?>
          <?php if ($product['stock'] == 0): ?>
            <div class="out-of-stock-overlay"><span>Out of Stock</span></div>
          <?php endif; ?>

          <?php if (count($product_images) > 1): ?>
            <button class="slider-arrow slider-prev" onclick="slideMove(-1)">‹</button>
            <button class="slider-arrow slider-next" onclick="slideMove(1)">›</button>
          <?php endif; ?>
        </div>

        <?php if (count($product_images) > 1): ?>
        <div class="slider-dots" id="sliderDots">
          <?php foreach ($product_images as $i => $img): ?>
            <button class="slider-dot <?= $i === 0 ? 'active' : '' ?>" onclick="goSlide(<?= $i ?>)"></button>
          <?php endforeach; ?>
        </div>
        <div class="slider-thumbs">
          <?php foreach ($product_images as $i => $img): ?>
            <div class="slider-thumb <?= $i === 0 ? 'active' : '' ?>" onclick="goSlide(<?= $i ?>)">
              <img src="<?= htmlspecialchars(img_url($img)) ?>" alt="" onerror="this.style.display='none'"/>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

    </div>

    <!-- ══ PRODUCT INFO ══ -->
    <div class="product-info-section">
      <div class="product-category-tag">🏷️ <?= htmlspecialchars($product['category']) ?></div>
      <div class="product-name-big"><?= htmlspecialchars($product['name']) ?></div>

      <!-- Rating summary -->
      <div class="rating-summary">
        <div class="stars-display">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <span class="star <?= $i <= round($avg_rating) ? 'filled' : '' ?>">★</span>
          <?php endfor; ?>
        </div>
        <span class="avg-num"><?= $total_reviews > 0 ? number_format($avg_rating, 1) : '0.0' ?></span>
        <span class="review-count">(<?= $total_reviews ?> reviews)</span>
      </div>

      <?php if (!empty($product['vendor_shop'])): ?>
        <div class="vendor-tag">Sold by: <span><?= htmlspecialchars($product['vendor_shop']) ?></span></div>
      <?php endif; ?>

      <div class="product-price-big">₹<?= number_format($product['price'], 2) ?></div>

      <?php if ($product['stock'] == 0): ?>
        <div class="stock-info out-stock">❌ Out of Stock</div>
      <?php elseif ($product['stock'] < 5): ?>
        <div class="stock-info low-stock">⚠️ Only <?= $product['stock'] ?> left!</div>
      <?php else: ?>
        <div class="stock-info in-stock">✅ In Stock (<?= $product['stock'] ?> available)</div>
      <?php endif; ?>

      <?php if (!empty($product['description'])): ?>
        <div class="product-desc"><?= nl2br(htmlspecialchars($product['description'])) ?></div>
      <?php endif; ?>

      <?php if ($product['stock'] > 0): ?>
      <div class="qty-section">
        <span class="qty-label">Quantity:</span>
        <div class="qty-wrap">
          <button class="qty-btn" onclick="changeQty(-1)">−</button>
          <input type="number" class="qty-input" id="qtyInput" value="1" min="1" max="<?= $product['stock'] ?>"/>
          <button class="qty-btn" onclick="changeQty(1)">+</button>
        </div>
      </div>
      <div class="action-btns">
        <button class="btn-add-cart-big" onclick="addToCart()">🛒 Add to Cart</button>
        <button class="btn-buy-now" onclick="buyNow()">⚡ Buy Now</button>
      </div>
      <?php else: ?>
        <button class="btn-add-cart-big btn-disabled" disabled style="width:100%;margin-bottom:1rem;">❌ Out of Stock</button>
      <?php endif; ?>

      <div class="features-row">
        <div class="feature-item"><span class="feature-icon">🚚</span><div class="feature-text">Free Delivery above ₹499</div></div>
        <div class="feature-item"><span class="feature-icon">↩</span><div class="feature-text">7 Days Return</div></div>
        <div class="feature-item"><span class="feature-icon">🔒</span><div class="feature-text">Secure Payment</div></div>
      </div>
    </div>
  </div>

  <!-- ══════════════ REVIEWS SECTION ══════════════ -->
  <div class="reviews-section">
    <div class="reviews-title">⭐ Customer Reviews</div>

    <!-- Rating Overview -->
    <div class="rating-overview">
      <div class="rating-big-num">
        <div class="rating-number"><?= $total_reviews > 0 ? number_format($avg_rating, 1) : '–' ?></div>
        <div>
          <div class="rating-stars-big">
            <?php for ($i = 1; $i <= 5; $i++) echo $i <= round($avg_rating) ? '★' : '☆'; ?>
          </div>
          <div class="rating-total"><?= $total_reviews ?> reviews</div>
        </div>
      </div>
      <div class="rating-bars">
        <?php foreach ([5,4,3,2,1] as $star): ?>
        <div class="rating-bar-row">
          <span class="bar-label"><?= $star ?></span>
          <span style="font-size:0.8rem;">★</span>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?= $total_reviews > 0 ? round($dist[$star]/$total_reviews*100) : 0 ?>%"></div>
          </div>
          <span class="bar-count"><?= $dist[$star] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Review Form -->
    <?php if ($user_id): ?>
    <div class="review-form-wrap">
      <div class="review-form-title"><?= $my_review ? '✏️ Apni Review Update Karo' : '✍️ Review Likho' ?></div>

      <?php if ($review_msg): ?>
        <?php [$type, $msg] = explode(':', $review_msg, 2); ?>
        <div class="review-msg <?= $type ?>"><?= $type === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <form method="POST" id="reviewForm">
        <input type="hidden" name="submit_review" value="1"/>
        <input type="hidden" name="rating" id="ratingInput" value="<?= $my_review['rating'] ?? 0 ?>"/>
        <input type="hidden" name="review_media" id="reviewMediaInput" value=""/>

        <div class="star-picker" id="starPicker">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <button type="button" class="star-pick <?= isset($my_review) && $my_review['rating'] >= $i ? 'selected' : '' ?>" data-val="<?= $i ?>" onclick="setRating(<?= $i ?>)">★</button>
          <?php endfor; ?>
        </div>

        <textarea class="review-textarea" name="comment" placeholder="Apna experience batao — quality, delivery, value for money..." required><?= htmlspecialchars($my_review['comment'] ?? '') ?></textarea>

        <!-- Media Upload -->
        <div class="media-section">
          <div class="media-btns">
            <label class="media-btn" style="cursor:pointer;">
              🖼️ Photo Upload
              <input type="file" accept="image/*" style="display:none;" onchange="handleFileUpload(this)"/>
            </label>
            <button type="button" class="media-btn" onclick="openCamera()">📷 Open Camera</button>
            <label class="media-btn" style="cursor:pointer;">
              🎬 Video Upload
              <input type="file" accept="video/*" style="display:none;" onchange="handleFileUpload(this)"/>
            </label>
          </div>

          <!-- Camera Stream -->
          <video id="cameraStream" autoplay playsinline></video>
          <div class="camera-controls" id="cameraControls">
            <button type="button" class="btn-snap" onclick="snapPhoto()">📸 Snap</button>
            <button type="button" class="btn-stop-cam" onclick="stopCamera()">✕ Cancel</button>
          </div>

          <!-- Preview -->
          <div class="media-preview" id="mediaPreview">
            <img id="previewImg" style="display:none;"/>
            <video id="previewVid" controls style="display:none;max-width:240px;border-radius:10px;"></video>
            <button type="button" class="media-remove" onclick="removeMedia()">✕</button>
          </div>
        </div>

        <button type="submit" class="btn-submit-review">
          <?= $my_review ? '📝 Update Review' : '📤 Submit Review' ?>
        </button>
      </form>
    </div>
    <?php else: ?>
    <div class="login-prompt">
      ✍️ Review dene ke liye <a href="login.php">login karo</a> — share karo apna experience!
    </div>
    <?php endif; ?>

    <!-- Reviews List -->
    <?php if (empty($reviews)): ?>
      <div class="no-reviews">📝 Abhi koi review nahi — pehle review do!</div>
    <?php else: ?>
      <?php foreach ($reviews as $rev): ?>
      <div class="review-card">
        <div class="review-header">
          <div class="review-avatar"><?= strtoupper(substr($rev['user_name'], 0, 1)) ?></div>
          <div>
            <div class="reviewer-name"><?= htmlspecialchars($rev['user_name']) ?> <?= $rev['user_id'] == $user_id ? '<span style="background:var(--accent);color:white;font-size:0.65rem;padding:0.1rem 0.4rem;border-radius:50px;font-weight:700;">You</span>' : '' ?></div>
            <div class="review-date"><?= date('d M Y', strtotime($rev['created_at'])) ?></div>
          </div>
        </div>
        <div class="review-stars">
          <?php for ($i = 1; $i <= 5; $i++) echo $i <= $rev['rating'] ? '<span style="color:#f5a623;">★</span>' : '<span style="color:#ddd;">★</span>'; ?>
        </div>
        <div class="review-comment"><?= nl2br(htmlspecialchars($rev['comment'])) ?></div>
        <?php if (!empty($rev['media'])): ?>
        <div class="review-media">
          <?php if (str_starts_with($rev['media'], 'data:image')): ?>
            <img src="<?= $rev['media'] ?>" alt="Review photo" onclick="openLightbox(this.src)"/>
          <?php elseif (str_starts_with($rev['media'], 'data:video')): ?>
            <video src="<?= $rev['media'] ?>" controls></video>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- SIMILAR PRODUCTS -->
  <?php if (!empty($similar)): ?>
  <div>
    <div class="section-title">🛍️ Similar Products</div>
    <div class="products-grid">
      <?php foreach ($similar as $p): ?>
      <a href="product.php?id=<?= $p['id'] ?>" class="product-card">
        <div class="card-img">
          <?php
          $first_img = !empty($p['image']) ? strtok($p['image'], ',') : '';
          if ($first_img): ?>
            <img src="<?= htmlspecialchars(img_url(trim($first_img))) ?>" alt="<?= htmlspecialchars($p['name']) ?>" onerror="this.style.display='none'"/>
          <?php else: ?>
            <?= $icons[$p['category']] ?? '📦' ?>
          <?php endif; ?>
        </div>
        <div class="card-info">
          <div class="card-cat"><?= htmlspecialchars($p['category']) ?></div>
          <div class="card-name"><?= htmlspecialchars($p['name']) ?></div>
          <div class="card-price">₹<?= number_format($p['price'], 2) ?></div>
          <button class="card-btn" onclick="event.preventDefault(); quickAdd(<?= $p['id'] ?>, '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>')">🛒 Add to Cart</button>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- LIGHTBOX -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
  <button class="lightbox-close" onclick="closeLightbox()">✕</button>
  <img id="lightboxImg" src="" alt=""/>
</div>

<div class="toast" id="toast"></div>

<script>
const productId = <?= $product['id'] ?>;
const maxStock  = <?= $product['stock'] ?>;
let currentSlide = 0;
const totalSlides = <?= max(1, count($product_images)) ?>;
let cameraStream = null;

// ── INLINE STAR RATING ──
function setInlineRating(val) {
  document.getElementById('inlineRatingInput').value = val;
  document.querySelectorAll('.inline-star').forEach((s, i) => s.classList.toggle('sel', i < val));
}

// ── INLINE MEDIA ──
function handleInlineFile(input) {
  const file = input.files[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) { alert('File 5MB se zyada nahi!'); return; }
  const reader = new FileReader();
  reader.onload = e => setInlineMedia(e.target.result, file.type.startsWith('video'));
  reader.readAsDataURL(file);
}
function setInlineMedia(src, isVideo) {
  document.getElementById('inlineMediaInput').value = src;
  const thumb = document.getElementById('inlineMediaThumb');
  const img = document.getElementById('inlineThumbImg');
  thumb.style.display = 'flex';
  if (!isVideo) { img.src = src; img.style.display = 'block'; }
  else { img.src = ''; img.style.display = 'none'; thumb.querySelector('button').title = '🎬 Video'; }
}
function removeInlineMedia() {
  document.getElementById('inlineMediaInput').value = '';
  document.getElementById('inlineMediaThumb').style.display = 'none';
  document.getElementById('inlineThumbImg').src = '';
}

// ── INLINE CAMERA ──
let inlineCamStream = null;
async function openInlineCamera() {
  try {
    inlineCamStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    const vid = document.getElementById('inlineCamStream');
    vid.srcObject = inlineCamStream;
    vid.style.display = 'block';
    document.getElementById('inlineCamControls').style.display = 'flex';
  } catch(e) { alert('Camera access nahi mila!'); }
}
function snapInlinePhoto() {
  const vid = document.getElementById('inlineCamStream');
  const canvas = document.createElement('canvas');
  canvas.width = vid.videoWidth; canvas.height = vid.videoHeight;
  canvas.getContext('2d').drawImage(vid, 0, 0);
  setInlineMedia(canvas.toDataURL('image/jpeg', 0.85), false);
  stopInlineCamera();
}
function stopInlineCamera() {
  if (inlineCamStream) { inlineCamStream.getTracks().forEach(t => t.stop()); inlineCamStream = null; }
  document.getElementById('inlineCamStream').style.display = 'none';
  document.getElementById('inlineCamControls').style.display = 'none';
}

// ── SLIDER ──
function goSlide(n) {
  currentSlide = n;
  document.querySelectorAll('.slide').forEach((s, i) => s.classList.toggle('active', i === n));
  document.querySelectorAll('.slider-dot').forEach((d, i) => d.classList.toggle('active', i === n));
  document.querySelectorAll('.slider-thumb').forEach((t, i) => t.classList.toggle('active', i === n));
}
function slideMove(dir) {
  goSlide((currentSlide + dir + totalSlides) % totalSlides);
}

// Touch/swipe support
let touchStartX = 0;
const sliderEl = document.getElementById('sliderMain');
if (sliderEl) {
  sliderEl.addEventListener('touchstart', e => touchStartX = e.changedTouches[0].screenX, {passive:true});
  sliderEl.addEventListener('touchend', e => {
    const diff = touchStartX - e.changedTouches[0].screenX;
    if (Math.abs(diff) > 50) slideMove(diff > 0 ? 1 : -1);
  });
}

// ── AUTO SCROLL ──
let autoSlideTimer;
function startAutoSlide() {
  if (totalSlides <= 1) return;
  autoSlideTimer = setInterval(() => slideMove(1), 3500);
}
function stopAutoSlide() { clearInterval(autoSlideTimer); }

// Pause on hover
if (sliderEl) {
  sliderEl.addEventListener('mouseenter', stopAutoSlide);
  sliderEl.addEventListener('mouseleave', startAutoSlide);
}
startAutoSlide();
function changeQty(delta) {
  const input = document.getElementById('qtyInput');
  let val = parseInt(input.value) + delta;
  if (val < 1) val = 1;
  if (val > maxStock) val = maxStock;
  input.value = val;
}
function addToCart() {
  const qty = parseInt(document.getElementById('qtyInput').value);
  fetch('add_to_cart.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`product_id=${productId}&quantity=${qty}` })
  .then(r=>r.json()).then(data => {
    if (data.status === 'success') { updateCartBadge(data.cart_count); showToast('✅ Cart mein add ho gaya!'); }
  });
}
function buyNow() {
  const qty = parseInt(document.getElementById('qtyInput').value);
  fetch('add_to_cart.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`product_id=${productId}&quantity=${qty}` })
  .then(r=>r.json()).then(data => { if (data.status === 'success') window.location.href = 'cart.php'; });
}
function quickAdd(id, name) {
  fetch('add_to_cart.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`product_id=${id}&quantity=1` })
  .then(r=>r.json()).then(data => { if (data.status === 'success') { updateCartBadge(data.cart_count); showToast('✅ ' + name + ' added!'); } });
}
function updateCartBadge(count) {
  const b = document.getElementById('cartCount');
  if (b) { b.textContent = count; b.style.display = 'inline'; }
}

// ── STAR RATING ──
function setRating(val) {
  document.getElementById('ratingInput').value = val;
  document.querySelectorAll('.star-pick').forEach((s, i) => s.classList.toggle('selected', i < val));
}

// ── MEDIA UPLOAD ──
function handleFileUpload(input) {
  const file = input.files[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) { alert('File 5MB se zyada nahi honi chahiye!'); return; }
  const reader = new FileReader();
  reader.onload = e => showMediaPreview(e.target.result, file.type.startsWith('video'));
  reader.readAsDataURL(file);
}

function showMediaPreview(src, isVideo) {
  document.getElementById('reviewMediaInput').value = src;
  const preview = document.getElementById('mediaPreview');
  const img = document.getElementById('previewImg');
  const vid = document.getElementById('previewVid');
  preview.style.display = 'block';
  if (isVideo) { img.style.display='none'; vid.src=src; vid.style.display='block'; }
  else { vid.style.display='none'; img.src=src; img.style.display='block'; }
}

function removeMedia() {
  document.getElementById('reviewMediaInput').value = '';
  document.getElementById('mediaPreview').style.display = 'none';
  document.getElementById('previewImg').src = '';
  document.getElementById('previewVid').src = '';
}

// ── CAMERA ──
async function openCamera() {
  try {
    cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    const vid = document.getElementById('cameraStream');
    vid.srcObject = cameraStream;
    vid.style.display = 'block';
    document.getElementById('cameraControls').style.display = 'flex';
  } catch(e) { alert('Camera access nahi mila! Browser settings check karo.'); }
}

function snapPhoto() {
  const vid = document.getElementById('cameraStream');
  const canvas = document.createElement('canvas');
  canvas.width = vid.videoWidth; canvas.height = vid.videoHeight;
  canvas.getContext('2d').drawImage(vid, 0, 0);
  const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
  showMediaPreview(dataUrl, false);
  stopCamera();
}

function stopCamera() {
  if (cameraStream) { cameraStream.getTracks().forEach(t => t.stop()); cameraStream = null; }
  document.getElementById('cameraStream').style.display = 'none';
  document.getElementById('cameraControls').style.display = 'none';
}

// ── LIGHTBOX ──
function openLightbox(src) {
  document.getElementById('lightboxImg').src = src;
  document.getElementById('lightbox').classList.add('open');
}
function closeLightbox() { document.getElementById('lightbox').classList.remove('open'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}
</script>

  <script>
  function toggleNav() {
    document.getElementById('navRight').classList.toggle('open');
    var h = document.getElementById('hamburger');
    if(h) h.classList.toggle('open');
    document.getElementById('navOverlay').classList.toggle('open');
    document.body.style.overflow = document.getElementById('navRight').classList.contains('open') ? 'hidden' : '';
  }
  function closeNav() {
    document.getElementById('navRight').classList.remove('open');
    var h = document.getElementById('hamburger');
    if(h) h.classList.remove('open');
    document.getElementById('navOverlay').classList.remove('open');
    document.body.style.overflow = '';
  }
  </script>
</body>
</html>