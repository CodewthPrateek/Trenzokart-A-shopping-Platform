<?php
require 'config.php';
$user_name  = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : null;
$q          = trim($_GET['q'] ?? '');
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $cart_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(quantity) as t FROM cart WHERE user_id='{$_SESSION['user_id']}'"))['t'] ?? 0;
} elseif (isset($_SESSION['guest_cart'])) {
    $cart_count = array_sum($_SESSION['guest_cart']);
}

// SEARCH — exact match pehle, phir similar
$exact = []; $similar = [];
if (!empty($q)) {
    $qe = mysqli_real_escape_string($conn, $q);
    // Exact match — naam mein poora query match ho
    $r1 = mysqli_query($conn, "SELECT * FROM products WHERE name LIKE '%$qe%' ORDER BY id DESC");
    while ($row = mysqli_fetch_assoc($r1)) { $exact[$row['id']] = $row; }

    // Similar — har word se search
    $words = array_filter(explode(' ', $q), fn($w) => strlen(trim($w)) > 1);
    foreach ($words as $word) {
        $w = mysqli_real_escape_string($conn, trim($word));
        $r2 = mysqli_query($conn, "SELECT * FROM products WHERE (name LIKE '%$w%' OR category LIKE '%$w%' OR description LIKE '%$w%') ORDER BY id DESC");
        while ($row = mysqli_fetch_assoc($r2)) {
            if (!isset($exact[$row['id']])) { $similar[$row['id']] = $row; }
        }
    }
}
$all_products = array_values($exact) ;
$similar_products = array_values($similar);
$icons = ['Clothes'=>'👕','Electronics'=>'📱','Grocery'=>'🥗'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — Search: <?= htmlspecialchars($q) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --cream: #f5efe6; --warm: #e8d5b7; --brown: #5c3d1e; --accent: #d4622a; --accent2: #e8a045; --text: #2d1a0a; --muted: #8a6a4a; --white: #fffdf8; --dark: #1a0f02; }
    body { font-family: 'DM Sans', sans-serif; background: var(--cream); color: var(--text); }
    nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; background: rgba(245,239,230,0.96); backdrop-filter: blur(12px); border-bottom: 1px solid var(--warm); display: flex; align-items: center; justify-content: space-between; padding: 0 5vw; height: 68px; }
    .logo { font-family: 'Playfair Display', serif; font-size: 1.8rem; font-weight: 900; color: var(--brown); text-decoration: none; }
    .logo span { color: var(--accent); }
    .search-wrapper { flex: 1; max-width: 480px; margin: 0 2rem; position: relative; }
    .search-wrapper input { width: 100%; padding: 0.65rem 1rem 0.65rem 2.8rem; border: 1.5px solid var(--warm); border-radius: 50px; font-family: 'DM Sans', sans-serif; font-size: 0.9rem; background: var(--white); color: var(--text); outline: none; transition: all .2s; }
    .search-wrapper input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(212,98,42,0.1); }
    .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--muted); }
    .nav-right { display: flex; align-items: center; gap: 1rem; }
    .nav-btn { display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: 50px; font-family: 'DM Sans', sans-serif; font-size: 0.85rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: all .2s; }
    .btn-cart { background: var(--warm); color: var(--brown); border: none; }
    .btn-cart:hover { background: var(--accent); color: white; }
    .btn-outline { background: none; color: var(--muted); border: 1.5px solid var(--warm); }
    .btn-outline:hover { border-color: var(--accent); color: var(--accent); }
    .profile-dropdown { position: relative; }
    .profile-btn { display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.9rem 0.4rem 0.4rem; background: var(--white); border: 1.5px solid var(--warm); border-radius: 50px; cursor: pointer; font-family: 'DM Sans', sans-serif; font-size: 0.85rem; font-weight: 600; color: var(--brown); transition: all .2s; }
    .profile-btn:hover { border-color: var(--accent); }
    .profile-avatar { width: 30px; height: 30px; background: var(--accent); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700; }
    .dropdown-menu { position: absolute; top: calc(100% + 10px); right: 0; background: var(--white); border: 1.5px solid var(--warm); border-radius: 16px; box-shadow: 0 12px 40px rgba(92,61,30,0.15); min-width: 190px; overflow: hidden; display: none; z-index: 300; }
    .dropdown-menu.open { display: block; }
    .dropdown-item { display: flex; align-items: center; gap: 0.6rem; padding: 0.75rem 1.2rem; font-size: 0.88rem; color: var(--text); text-decoration: none; font-weight: 500; transition: background .15s; border-bottom: 1px solid rgba(232,213,183,0.4); }
    .dropdown-item:last-child { border-bottom: none; }
    .dropdown-item:hover { background: var(--cream); color: var(--accent); }
    .logout-item { color: #dc3545 !important; }

    .main { margin-top: 88px; padding: 2.5rem 5vw; }
    .search-header { margin-bottom: 2rem; }
    .search-header h1 { font-family: 'Playfair Display', serif; font-size: 1.8rem; color: var(--brown); margin-bottom: 0.3rem; }
    .search-header p { color: var(--muted); font-size: 0.9rem; }
    .search-query { color: var(--accent); font-weight: 700; }
    .result-count { display: inline-block; background: var(--warm); color: var(--brown); font-size: 0.78rem; font-weight: 700; padding: 0.2rem 0.7rem; border-radius: 50px; margin-left: 0.5rem; }
    .section-label { font-family: 'Playfair Display', serif; font-size: 1.2rem; color: var(--brown); margin-bottom: 1rem; margin-top: 2rem; display: flex; align-items: center; gap: 0.5rem; }
    .section-divider { height: 2px; background: var(--warm); margin-bottom: 1.5rem; border-radius: 2px; }

    .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.5rem; }
    .product-card { background: var(--white); border-radius: 20px; overflow: hidden; transition: transform .2s, box-shadow .2s; border: 1.5px solid transparent; }
    .product-card:hover { transform: translateY(-6px); box-shadow: 0 16px 40px rgba(92,61,30,0.12); border-color: var(--warm); }
    .product-img { width: 100%; height: 220px; background: linear-gradient(135deg, var(--warm), var(--cream)); display: flex; align-items: center; justify-content: center; font-size: 4rem; position: relative; overflow: hidden; cursor: pointer; }
    .product-img img { width: 100%; height: 100%; object-fit: cover; }
    .product-badge { position: absolute; top: 0.8rem; left: 0.8rem; background: var(--accent); color: white; font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 50px; }
    .product-info { padding: 1.2rem; }
    .product-category { font-size: 0.72rem; color: var(--accent); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.3rem; }
    .product-name { font-weight: 600; color: var(--text); font-size: 0.95rem; margin-bottom: 0.5rem; line-height: 1.3; cursor: pointer; }
    .product-name:hover { color: var(--accent); }
    .product-price { font-family: 'Playfair Display', serif; font-size: 1.2rem; font-weight: 700; color: var(--brown); margin-bottom: 0.8rem; }
    .product-btns { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
    .btn-add-cart { padding: 0.6rem; background: var(--cream); color: var(--brown); border: 1.5px solid var(--warm); border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all .2s; }
    .btn-add-cart:hover { background: var(--brown); color: white; border-color: var(--brown); }
    .btn-buy-now { padding: 0.6rem; background: var(--accent); color: white; border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all .2s; }
    .btn-buy-now:hover { background: #c0551f; }

    .no-results { text-align: center; padding: 5rem 2rem; color: var(--muted); background: var(--white); border-radius: 22px; border: 1.5px dashed var(--warm); }
    .no-results span { font-size: 4rem; display: block; margin-bottom: 1rem; }
    .toast { position: fixed; bottom: 2rem; right: 2rem; background: var(--brown); color: white; padding: 0.9rem 1.6rem; border-radius: 14px; font-size: 0.88rem; font-weight: 600; box-shadow: 0 8px 30px rgba(0,0,0,0.25); transform: translateY(120px); opacity: 0; transition: all 0.35s; z-index: 999; }
    .toast.show { transform: translateY(0); opacity: 1; }
  </style>
</head>
<body>
<nav>
  <a href="index.php" class="logo">Trenzo<span>Kart</span></a>
  <div class="search-wrapper">
    <span class="search-icon">🔍</span>
    <input type="text" id="searchInput" placeholder="Search products..." value="<?= htmlspecialchars($q) ?>" onkeydown="if(event.key==='Enter') window.location.href='search_results.php?q='+encodeURIComponent(this.value)"/>
  </div>
  <div class="nav-right">
    <?php if ($user_name): ?>
      <a href="cart.php" class="nav-btn btn-cart">
        🛒 Cart
        <?php if ($cart_count > 0): ?>
          <span style="background:#d4622a;color:white;border-radius:50%;padding:1px 7px;font-size:0.75rem;margin-left:4px;" id="cartCount"><?= $cart_count ?></span>
        <?php else: ?>
          <span style="display:none;" id="cartCount">0</span>
        <?php endif; ?>
      </a>
      <div class="profile-dropdown">
        <button class="profile-btn" onclick="this.nextElementSibling.classList.toggle('open')">
          <div class="profile-avatar"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
          <span><?= htmlspecialchars(explode(' ', $user_name)[0]) ?></span>
          <span style="font-size:0.7rem;">▼</span>
        </button>
        <div class="dropdown-menu">
          <a href="profile.php" class="dropdown-item">👤 My Profile</a>
          <a href="my_orders.php" class="dropdown-item">📦 My Orders</a>
          <a href="cart.php" class="dropdown-item">🛒 My Cart</a>
          <div style="height:1px;background:var(--warm);"></div>
          <a href="logout.php" class="dropdown-item logout-item">🚪 Logout</a>
        </div>
      </div>
    <?php else: ?>
      <a href="cart.php" class="nav-btn btn-cart">
        🛒 Cart
        <span style="<?= $cart_count > 0 ? '' : 'display:none;' ?>background:#d4622a;color:white;border-radius:50%;padding:1px 7px;font-size:0.75rem;margin-left:4px;" id="cartCount"><?= $cart_count ?></span>
      </a>
      <a href="login.php" class="nav-btn btn-outline">Login</a>
      <a href="signup.php" class="nav-btn btn-cart">Sign Up</a>
    <?php endif; ?>
  </div>
</nav>

<div class="main">
  <div class="search-header">
    <h1>Search Results <span class="result-count"><?= count($all_products) + count($similar_products) ?> found</span></h1>
    <p>Showing results for <span class="search-query">"<?= htmlspecialchars($q) ?>"</span></p>
  </div>

  <?php if (empty($all_products) && empty($similar_products)): ?>
    <div class="no-results">
      <span>🔍</span>
      <p>No products found for "<strong><?= htmlspecialchars($q) ?></strong>"</p>
      <br>
      <a href="index.php" style="color:var(--accent);font-weight:700;">← Back to Home</a>
    </div>
  <?php else: ?>

    <!-- EXACT MATCHES -->
    <?php if (!empty($all_products)): ?>
    <div class="section-label">🎯 Best Matches</div>
    <div class="section-divider"></div>
    <div class="products-grid">
      <?php foreach ($all_products as $p): ?>
      <?php include_once '_product_card.inc.php' ?? null; ?>
      <div class="product-card">
        <div class="product-img" onclick="window.location.href='product.php?id=<?= $p['id'] ?>'">
          <?php if (!empty($p['image'])): ?>
            <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>"/>
          <?php else: ?>
            <?= $icons[$p['category']] ?? '📦' ?>
          <?php endif; ?>
          <?php if ($p['stock'] < 5 && $p['stock'] > 0): ?>
            <span class="product-badge">Low Stock</span>
          <?php endif; ?>
        </div>
        <div class="product-info">
          <div class="product-category"><?= htmlspecialchars($p['category']) ?></div>
          <div class="product-name" onclick="window.location.href='product.php?id=<?= $p['id'] ?>'"><?= htmlspecialchars($p['name']) ?></div>
          <div class="product-price">₹<?= number_format($p['price'], 2) ?></div>
          <div class="product-btns">
            <button class="btn-add-cart" onclick="addToCart(<?= $p['id'] ?>, '<?= htmlspecialchars($p['name']) ?>')">🛒 Add to Cart</button>
            <button class="btn-buy-now" onclick="buyNow(<?= $p['id'] ?>)">⚡ Buy Now</button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- SIMILAR PRODUCTS -->
    <?php if (!empty($similar_products)): ?>
    <div class="section-label" style="margin-top:3rem;">🔗 Similar Products</div>
    <div class="section-divider"></div>
    <div class="products-grid">
      <?php foreach ($similar_products as $p): ?>
      <div class="product-card">
        <div class="product-img" onclick="window.location.href='product.php?id=<?= $p['id'] ?>'">
          <?php if (!empty($p['image'])): ?>
            <img src="<?= htmlspecialchars($p['image']) ?>" alt=""/>
          <?php else: ?>
            <?= $icons[$p['category']] ?? '📦' ?>
          <?php endif; ?>
        </div>
        <div class="product-info">
          <div class="product-category"><?= htmlspecialchars($p['category']) ?></div>
          <div class="product-name" onclick="window.location.href='product.php?id=<?= $p['id'] ?>'"><?= htmlspecialchars($p['name']) ?></div>
          <div class="product-price">₹<?= number_format($p['price'], 2) ?></div>
          <div class="product-btns">
            <button class="btn-add-cart" onclick="addToCart(<?= $p['id'] ?>, '<?= htmlspecialchars($p['name']) ?>')">🛒 Add to Cart</button>
            <button class="btn-buy-now" onclick="buyNow(<?= $p['id'] ?>)">⚡ Buy Now</button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  <?php endif; ?>
</div>

<div class="toast" id="toast"></div>
<script>
let cartCount = <?= $cart_count ?>;

function addToCart(id, name) {
  fetch('add_to_cart.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `product_id=${id}&quantity=1`
  })
  .then(r => r.json())
  .then(data => {
    if (data.status === 'success') {
      cartCount = data.cart_count;
      const badge = document.getElementById('cartCount');
      badge.textContent = cartCount;
      badge.style.display = 'inline';
      showToast('✅ ' + name + ' added to cart!');
    }
  });
}

function buyNow(id) {
  fetch('add_to_cart.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `product_id=${id}&quantity=1`
  })
  .then(r => r.json())
  .then(data => {
    if (data.status === 'success') window.location.href = 'cart.php';
  });
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}

document.addEventListener('click', function(e) {
  const dd = document.querySelector('.profile-dropdown');
  if (dd && !dd.contains(e.target)) {
    const menu = dd.querySelector('.dropdown-menu');
    if (menu) menu.classList.remove('open');
  }
});
</script>
</body>
</html>