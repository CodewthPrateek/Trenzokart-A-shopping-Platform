<?php
require 'config.php';

$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : null;

$cart_count = 0;
if (isset($_SESSION['user_id'])) {
  $cart_sql = mysqli_query($conn, "SELECT SUM(quantity) as total FROM cart WHERE user_id = '{$_SESSION['user_id']}'");
  $cart_row = mysqli_fetch_assoc($cart_sql);
  $cart_count = $cart_row['total'] ?? 0;
} elseif (!empty($_SESSION['guest_cart'])) {
  $cart_count = array_sum($_SESSION['guest_cart']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TrenzoKart — Home</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --cream: #f5efe6; --warm: #e8d5b7; --brown: #5c3d1e; --accent: #d4622a; --accent2: #e8a045; --text: #2d1a0a; --muted: #8a6a4a; --white: #fffdf8; --dark: #1a0f02; }
    html { scroll-behavior: smooth; }
    body { font-family: "DM Sans", sans-serif; background: var(--cream); color: var(--text); overflow-x: hidden; }
    nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; background: rgba(245,239,230,0.96); backdrop-filter: blur(12px); border-bottom: 1px solid var(--warm); display: flex; align-items: center; justify-content: space-between; padding: 0 5vw; height: 68px; box-shadow: 0 2px 20px rgba(92,61,30,0.08); }
    .logo { font-family: "Playfair Display", serif; font-size: 1.8rem; font-weight: 900; color: var(--brown); letter-spacing: -1px; text-decoration: none; }
    .logo span { color: var(--accent); }
    .nav-home { display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: 50px; font-family: "DM Sans", sans-serif; font-size: 0.85rem; font-weight: 600; text-decoration: none; color: var(--brown); border: 1.5px solid var(--warm); transition: all .2s; margin-right: 0.5rem; }
    .nav-home:hover { border-color: var(--accent); color: var(--accent); background: var(--white); }
    .search-wrapper { flex: 1; max-width: 480px; margin: 0 1.5rem; position: relative; }
    .search-wrapper input { width: 100%; padding: 0.65rem 1rem 0.65rem 2.8rem; border: 1.5px solid var(--warm); border-radius: 50px; font-family: "DM Sans", sans-serif; font-size: 0.9rem; background: var(--white); color: var(--text); outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
    .search-wrapper input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(212,98,42,0.1); }
    .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--muted); }
    .search-results { position: absolute; top: 110%; left: 0; right: 0; background: var(--white); border: 1.5px solid var(--warm); border-radius: 16px; box-shadow: 0 12px 40px rgba(92,61,30,0.15); max-height: 320px; overflow-y: auto; display: none; z-index: 200; }
    .search-results.active { display: block; }
    .search-item { display: flex; align-items: center; gap: 0.8rem; padding: 0.8rem 1rem; cursor: pointer; transition: background 0.15s; border-bottom: 1px solid rgba(232,213,183,0.5); }
    .search-item:last-child { border-bottom: none; }
    .search-item:hover { background: var(--cream); }
    .search-item-img { width: 42px; height: 42px; background: var(--warm); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; overflow: hidden; }
    .search-item-name { font-size: 0.88rem; font-weight: 600; color: var(--text); }
    .search-item-cat { font-size: 0.75rem; color: var(--muted); }
    .search-item-price { font-size: 0.9rem; font-weight: 700; color: var(--accent); margin-left: auto; }
    .search-empty { padding: 1.5rem; text-align: center; color: var(--muted); font-size: 0.88rem; }
    .nav-right { display: flex; align-items: center; gap: 0.8rem; }
    .nav-btn { display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: 50px; font-family: "DM Sans", sans-serif; font-size: 0.85rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.2s; }
    .btn-cart { background: var(--warm); color: var(--brown); border: none; }
    .btn-cart:hover { background: var(--accent); color: white; }
    .btn-outline { background: none; color: var(--muted); border: 1.5px solid var(--warm); }
    .btn-outline:hover { border-color: var(--accent); color: var(--accent); }
    .profile-dropdown { position: relative; }
    .profile-btn { display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.9rem 0.4rem 0.4rem; background: var(--white); border: 1.5px solid var(--warm); border-radius: 50px; cursor: pointer; font-family: "DM Sans", sans-serif; font-size: 0.85rem; font-weight: 600; color: var(--brown); transition: all 0.2s; }
    .profile-btn:hover { border-color: var(--accent); box-shadow: 0 4px 12px rgba(212,98,42,0.15); }
    .profile-avatar { width: 30px; height: 30px; background: var(--accent); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700; }
    .dropdown-menu { position: absolute; top: calc(100% + 10px); right: 0; background: var(--white); border: 1.5px solid var(--warm); border-radius: 16px; box-shadow: 0 12px 40px rgba(92,61,30,0.15); min-width: 200px; overflow: hidden; display: none; z-index: 300; }
    .dropdown-menu.open { display: block; animation: fadeDown 0.2s ease; }
    @keyframes fadeDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
    .dropdown-item { display: flex; align-items: center; gap: 0.6rem; padding: 0.8rem 1.2rem; font-size: 0.88rem; color: var(--text); text-decoration: none; font-weight: 500; transition: background 0.15s; border-bottom: 1px solid rgba(232,213,183,0.3); }
    .dropdown-item:last-child { border-bottom: none; }
    .dropdown-item:hover { background: var(--cream); color: var(--accent); }
    .dropdown-divider { height: 1px; background: var(--warm); }
    .logout-item { color: #dc3545 !important; }
    .logout-item:hover { background: #fff5f5 !important; }
    .hero { margin-top: 68px; background: linear-gradient(135deg, var(--brown) 0%, #3a2010 60%, #1a0f02 100%); padding: 5rem 5vw; display: flex; align-items: center; justify-content: space-between; position: relative; overflow: hidden; min-height: 420px; }
    .hero::before { content: ""; position: absolute; top: -100px; right: -100px; width: 500px; height: 500px; background: radial-gradient(circle, rgba(212,98,42,0.2) 0%, transparent 70%); border-radius: 50%; }
    .hero-content { position: relative; z-index: 1; max-width: 520px; }
    .hero-tag { display: inline-block; background: rgba(212,98,42,0.25); color: var(--accent2); font-size: 0.78rem; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; padding: 0.3rem 0.8rem; border-radius: 50px; margin-bottom: 1.2rem; border: 1px solid rgba(232,160,69,0.3); }
    .hero h1 { font-family: "Playfair Display", serif; font-size: clamp(2rem, 4vw, 3.2rem); color: var(--white); line-height: 1.15; margin-bottom: 1rem; }
    .hero h1 span { color: var(--accent2); }
    .hero p { color: rgba(255,255,255,0.65); font-size: 1rem; line-height: 1.6; margin-bottom: 2rem; }
    .hero-btns { display: flex; gap: 1rem; flex-wrap: wrap; }
    .btn-primary { padding: 0.85rem 2rem; background: var(--accent); color: white; border: none; border-radius: 50px; font-family: "DM Sans", sans-serif; font-size: 0.95rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.2s; }
    .btn-primary:hover { background: #c0551f; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(212,98,42,0.4); }
    .btn-secondary { padding: 0.85rem 2rem; background: rgba(255,255,255,0.1); color: white; border: 1.5px solid rgba(255,255,255,0.25); border-radius: 50px; font-family: "DM Sans", sans-serif; font-size: 0.95rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.2s; }
    .btn-secondary:hover { background: rgba(255,255,255,0.2); transform: translateY(-2px); }
    .hero-stats { position: relative; z-index: 1; display: flex; flex-direction: column; gap: 1rem; }
    .stat-card { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12); border-radius: 16px; padding: 1.2rem 1.8rem; text-align: center; }
    .stat-number { font-family: "Playfair Display", serif; font-size: 2rem; font-weight: 900; color: var(--accent2); }
    .stat-label { font-size: 0.78rem; color: rgba(255,255,255,0.55); margin-top: 0.2rem; }
    .section { padding: 4rem 5vw; }
    .section-title { font-family: "Playfair Display", serif; font-size: 1.8rem; color: var(--brown); margin-bottom: 2rem; }
    .categories-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1.2rem; }
    .cat-card { background: var(--white); border-radius: 20px; padding: 2rem 1.5rem; text-align: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; border: 1.5px solid transparent; text-decoration: none; display: block; }
    .cat-card:hover { transform: translateY(-6px); box-shadow: 0 16px 40px rgba(92,61,30,0.12); border-color: var(--accent); }
    .cat-card.active { border-color: var(--accent); background: #fff5f0; }
    .cat-icon { font-size: 2.8rem; margin-bottom: 0.8rem; display: block; }
    .cat-name { font-weight: 600; color: var(--brown); font-size: 0.95rem; }
    .cat-count { font-size: 0.78rem; color: var(--muted); margin-top: 0.2rem; }
    .products-section { padding: 0 5vw 4rem; }
    .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.5rem; }
    .product-card { background: var(--white); border-radius: 20px; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s; border: 1.5px solid transparent; }
    .product-card:hover { transform: translateY(-6px); box-shadow: 0 16px 40px rgba(92,61,30,0.12); border-color: var(--warm); }

    /* ===== IMAGE FIX ===== */
    .product-img {
      width: 100%;
      height: 180px;
      background: var(--cream);
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow: hidden;
      border-radius: 15px 15px 0 0;
    }
    .product-img img {
      position: absolute;
      top: 0; left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      z-index: 2;
    }
    .product-img .fallback-icon {
      font-size: 3rem;
      z-index: 1;
    }
    /* ===== END IMAGE FIX ===== */

    .product-badge { position: absolute; top: 0.8rem; left: 0.8rem; background: var(--accent); color: white; font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 50px; text-transform: uppercase; z-index: 3; }
    .product-info { padding: 1.2rem; }
    .product-category { font-size: 0.72rem; color: var(--accent); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.3rem; }
    .product-name { font-weight: 600; color: var(--text); font-size: 0.95rem; margin-bottom: 0.5rem; line-height: 1.3; }
    .product-price { font-family: "Playfair Display", serif; font-size: 1.2rem; font-weight: 700; color: var(--brown); }
    .btn-add-cart { width: 100%; margin-top: 0.6rem; padding: 0.6rem; background: var(--cream); color: var(--brown); border: 1.5px solid var(--warm); border-radius: 10px; font-family: "DM Sans", sans-serif; font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
    .btn-add-cart:hover { background: var(--accent); color: white; border-color: var(--accent); }
    .loading { text-align: center; padding: 3rem; color: var(--muted); }
    .no-results { text-align: center; padding: 3rem; color: var(--muted); }
    .no-results span { font-size: 3rem; display: block; margin-bottom: 1rem; }

    /* TOAST */
    #toast { position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%) translateY(100px); background: var(--brown); color: white; padding: 0.8rem 1.8rem; border-radius: 50px; font-size: 0.9rem; font-weight: 600; z-index: 9999; transition: transform 0.3s ease; box-shadow: 0 8px 24px rgba(0,0,0,0.2); pointer-events: none; }
    #toast.show { transform: translateX(-50%) translateY(0); }

    /* FOOTER */
    footer { background: var(--dark); color: rgba(255,255,255,0.5); font-size: 0.88rem; }
    .footer-top { display: grid; grid-template-columns: 1.8fr 1fr 1.5fr; gap: 3rem; padding: 3.5rem 5vw; border-bottom: 1px solid rgba(255,255,255,0.08); }
    .footer-brand p { margin-top: 1rem; line-height: 1.7; color: rgba(255,255,255,0.45); font-size: 0.9rem; max-width: 280px; }
    .footer-links h4, .footer-partner h4 { font-family: "Playfair Display", serif; color: var(--accent2); font-size: 1rem; font-weight: 700; margin-bottom: 1.2rem; letter-spacing: 0.3px; }
    .footer-links { display: flex; flex-direction: column; gap: 0.7rem; }
    .footer-links a { color: rgba(255,255,255,0.45); text-decoration: none; font-size: 0.9rem; transition: color 0.2s; }
    .footer-links a:hover { color: var(--accent2); }
    .footer-partner p { color: rgba(255,255,255,0.75); line-height: 1.6; margin-bottom: 1.2rem; font-size: 0.9rem; }
    .btn-sell { display: inline-flex; align-items: center; gap: 0.5rem; background: var(--accent); color: white; padding: 0.75rem 1.8rem; border-radius: 50px; font-family: "DM Sans", sans-serif; font-size: 0.92rem; font-weight: 700; text-decoration: none; transition: all 0.2s; }
    .btn-sell:hover { background: #c0551f; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(212,98,42,0.4); }
    .footer-bottom { padding: 1.5rem 5vw; text-align: center; color: rgba(255,255,255,0.35); font-size: 0.83rem; line-height: 1.8; }
    .footer-bottom strong { color: rgba(255,255,255,0.7); }
    .footer-bottom span { color: var(--accent2); }

    /* RESPONSIVE */
    @media (max-width: 900px) {
      .categories-grid { grid-template-columns: repeat(3, 1fr); }
      .footer-top { grid-template-columns: 1fr 1fr; gap: 2rem; }
      .footer-brand { grid-column: 1 / -1; }
      .footer-brand p { max-width: 100%; }
    }
    @media (max-width: 768px) {
      nav { padding: 0 4vw; height: auto; flex-wrap: wrap; gap: 0.5rem; padding-top: 0.6rem; padding-bottom: 0.6rem; }
      .logo { font-size: 1.5rem; }
      .nav-home { display: none; }
      .search-wrapper { order: 3; flex: unset; width: 100%; max-width: 100%; margin: 0; }
      .nav-right { gap: 0.5rem; }
      .nav-btn { padding: 0.4rem 0.7rem; font-size: 0.78rem; }
      .profile-btn span:not(.profile-avatar) { display: none; }
      .categories-grid { grid-template-columns: repeat(2, 1fr); gap: 0.8rem; }
      .cat-card { padding: 1.2rem 0.8rem; border-radius: 14px; }
      .cat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
      .hero { flex-direction: column; gap: 1.5rem; padding: 3rem 4vw; }
      .hero-stats { flex-direction: row; flex-wrap: wrap; justify-content: center; }
      .stat-card { padding: 0.8rem 1.2rem; }
      .products-section { padding: 0 4vw 3rem; }
      .section { padding: 2.5rem 4vw; }
      .products-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem; }
      .product-img { height: 140px; }
    }
    @media (max-width: 600px) {
      .footer-top { grid-template-columns: 1fr; gap: 2rem; padding: 2.5rem 5vw; }
      .footer-brand { grid-column: auto; }
    }
    @media (max-width: 480px) {
      .categories-grid { grid-template-columns: repeat(2, 1fr); gap: 0.6rem; }
      .products-grid { grid-template-columns: repeat(2, 1fr); gap: 0.7rem; }
      .product-info { padding: 0.8rem; }
      .product-name { font-size: 0.85rem; }
      .hero h1 { font-size: 1.6rem; }
      .hero p { font-size: 0.88rem; }
      .hero-btns { flex-direction: column; gap: 0.7rem; }
      .btn-primary, .btn-secondary { text-align: center; }
      .section-title { font-size: 1.4rem; }
      .nav-btn { padding: 0.35rem 0.55rem; font-size: 0.75rem; }
    }
  </style>
</head>
<body>

<div id="toast"></div>

  <nav>
    <a href="index.php" class="logo">Trenzo<span>Kart</span></a>
    <a href="index.php" class="nav-home">🏠 Home</a>

    <div class="search-wrapper">
      <span class="search-icon">🔍</span>
      <input type="text" id="searchInput" placeholder="Search products..." onkeydown="if(event.key==='Enter') window.location.href='search_results.php?q='+encodeURIComponent(this.value)"/>
      <div class="search-results" id="searchResults"></div>
    </div>

    <div class="nav-right">
      <?php if ($user_name): ?>
        <a href="cart.php" class="nav-btn btn-cart">
          🛒 Cart
          <?php if ($cart_count > 0): ?>
            <span style="background:#d4622a;color:white;border-radius:50%;padding:1px 7px;font-size:0.75rem;margin-left:4px;" id="cartBadge"><?= $cart_count > 5 ? '5+' : $cart_count ?></span>
          <?php else: ?>
            <span style="display:none;background:#d4622a;color:white;border-radius:50%;padding:1px 7px;font-size:0.75rem;margin-left:4px;" id="cartBadge">0</span>
          <?php endif; ?>
        </a>
        <div class="profile-dropdown">
          <button class="profile-btn" onclick="toggleDropdown()">
            <div class="profile-avatar"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
            <span><?= htmlspecialchars(explode(' ', $user_name)[0]) ?></span>
            <span style="font-size:0.7rem;">▼</span>
          </button>
          <div class="dropdown-menu" id="dropdownMenu">
            <a href="profile.php" class="dropdown-item">👤 My Profile</a>
            <a href="my_orders.php" class="dropdown-item">📦 My Orders</a>
            <a href="cart.php" class="dropdown-item">🛒 My Cart</a>
            <div class="dropdown-divider"></div>
            <a href="logout.php" class="dropdown-item logout-item">🚪 Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="cart.php" class="nav-btn btn-cart">
          🛒 Cart
          <span style="<?= $cart_count > 0 ? '' : 'display:none;' ?>background:#d4622a;color:white;border-radius:50%;padding:1px 7px;font-size:0.75rem;margin-left:4px;" id="cartBadge"><?= $cart_count > 5 ? '5+' : $cart_count ?></span>
        </a>
        <a href="login.php" class="nav-btn btn-outline">Login</a>
        <a href="signup.php" class="nav-btn btn-cart">Sign Up</a>
      <?php endif; ?>
    </div>
  </nav>

  <section class="hero">
    <div class="hero-content">
      <div class="hero-tag">✨ New Arrivals Every Week</div>
      <h1>Shop <span>Everything</span> You Need</h1>
      <p>Clothes, Electronics, Groceries & more — all in one place!</p>
      <div class="hero-btns">
        <a href="#products" class="btn-primary">Shop Now →</a>
        <a href="#categories" class="btn-secondary">Browse Categories</a>
      </div>
    </div>
    <div class="hero-stats">
      <div class="stat-card"><div class="stat-number">500+</div><div class="stat-label">Products</div></div>
      <div class="stat-card"><div class="stat-number">₹499</div><div class="stat-label">Free Delivery Above</div></div>
      <div class="stat-card"><div class="stat-number">7 Days</div><div class="stat-label">Easy Returns</div></div>
    </div>
  </section>

  <section class="section" id="categories">
    <h2 class="section-title">Shop by Category</h2>
    <div class="categories-grid">
      <a class="cat-card active" href="#products" onclick="filterProducts('all', this)"><span class="cat-icon">🛍️</span><div class="cat-name">All Products</div><div class="cat-count">Everything</div></a>
      <a class="cat-card" href="#products" onclick="filterProducts('Clothes', this)"><span class="cat-icon">👕</span><div class="cat-name">Clothes</div><div class="cat-count">Fashion & Style</div></a>
      <a class="cat-card" href="#products" onclick="filterProducts('Electronics', this)"><span class="cat-icon">📱</span><div class="cat-name">Electronics</div><div class="cat-count">Gadgets & Tech</div></a>
      <a class="cat-card" href="#products" onclick="filterProducts('Grocery', this)"><span class="cat-icon">🛒</span><div class="cat-name">Grocery</div><div class="cat-count">Daily Essentials</div></a>
      <a class="cat-card" href="#products" onclick="filterProducts('Food & Beverages', this)"><span class="cat-icon">🥤</span><div class="cat-name">Food & Beverages</div><div class="cat-count">Drinks & Snacks</div></a>
    </div>
  </section>

  <section class="products-section" id="products">
    <h2 class="section-title">Featured Products</h2>
    <div class="products-grid" id="productsGrid">
      <div class="loading">Loading products... ⏳</div>
    </div>
  </section>

<footer>
  <div class="footer-top">
    <div class="footer-brand">
      <a href="index.php" class="logo" style="font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:900;color:white;letter-spacing:-1px;text-decoration:none;">Trenzo<span style="color:var(--accent);">Kart</span></a>
      <p>Premium e-commerce experience. We deliver quality and happiness right to your doorstep.</p>
    </div>
    <div class="footer-links">
      <h4>Quick Links</h4>
      <a href="index.php">Home</a>
      <a href="#categories">Categories</a>
      <a href="cart.php">Cart</a>
    </div>
    <div class="footer-partner">
      <h4>Partner with Us</h4>
      <p>Start selling your products on TrenzoKart and grow your business today!</p>
      <a href="#" class="btn-sell">🚀 Sell with Us</a>
    </div>
  </div>
  <div class="footer-bottom">
    <p>© 2026 <strong>TrenzoKart</strong> — All Rights Reserved.</p>
    <p>Created By <span>Prateek Verma</span></p>
  </div>
</footer>

  <script>
    const icons = { 'Clothes': '👕', 'Electronics': '📱', 'Grocery': '🥗', 'Food & Beverages': '🥤' };

    // ===== IMAGE FIX =====
    // Database mein image ka value jo bhi ho (sirf filename ya full path),
    // yeh function sahi src banata hai aur fallback bhi handle karta hai
    function buildImageSrc(imagePath) {
      if (!imagePath || imagePath.trim() === '') return '';
      // Agar already full URL hai
      if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) return imagePath;
      // Agar already 'uploads/' se shuru ho raha hai
      if (imagePath.startsWith('uploads/')) return imagePath;
      // Agar '/' se shuru ho raha hai (absolute server path)
      if (imagePath.startsWith('/')) return imagePath;
      // Sirf filename hai — uploads/ prefix lagao
      return 'uploads/' + imagePath;
    }

    function loadProducts(category = 'all') {
      const grid = document.getElementById('productsGrid');
      grid.innerHTML = '<div class="loading">Loading products... ⏳</div>';
      fetch('get_products.php?category=' + encodeURIComponent(category))
        .then(r => r.json())
        .then(products => {
          if (products.length === 0) {
            grid.innerHTML = '<div class="no-results"><span>🔍</span>No products found!</div>';
            return;
          }
          grid.innerHTML = products.map(p => {
            const src = buildImageSrc(p.image);
            const fallbackIcon = icons[p.category] || '📦';
            const imgHTML = src
              ? `<img src="${src}" alt="${p.name}" onerror="this.remove();"/>`
              : `<span class="fallback-icon">${fallbackIcon}</span>`;

            return `
              <div class="product-card">
                <div class="product-img" onclick="window.location.href='product.php?id=${p.id}'" style="cursor:pointer;">
                  ${imgHTML}
                  ${!src ? '' : ''}
                  ${parseInt(p.stock) < 5 && parseInt(p.stock) > 0 ? '<span class="product-badge">Low Stock</span>' : ''}
                  ${parseInt(p.stock) === 0 ? '<span class="product-badge" style="background:#dc3545;">Out of Stock</span>' : ''}
                </div>
                <div class="product-info">
                  <div class="product-category">${p.category}</div>
                  <div class="product-name" onclick="window.location.href='product.php?id=${p.id}'" style="cursor:pointer;">${p.name}</div>
                  <div class="product-price">₹${parseFloat(p.price).toLocaleString('en-IN')}</div>
                  ${parseInt(p.stock) > 0 ? `
                  <div style="display:flex;align-items:center;gap:0.5rem;margin:0.6rem 0;">
                    <button onclick="changeQty('qty_${p.id}',-1,${p.stock})" style="width:28px;height:28px;border-radius:50%;border:1.5px solid var(--warm);background:var(--cream);cursor:pointer;font-size:1rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">−</button>
                    <input type="number" id="qty_${p.id}" value="1" min="1" max="${p.stock}" style="width:45px;text-align:center;border:1.5px solid var(--warm);border-radius:8px;padding:0.2rem;font-family:'DM Sans',sans-serif;font-size:0.9rem;font-weight:700;background:var(--white);outline:none;"/>
                    <button onclick="changeQty('qty_${p.id}',1,${p.stock})" style="width:28px;height:28px;border-radius:50%;border:1.5px solid var(--warm);background:var(--cream);cursor:pointer;font-size:1rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">+</button>
                  </div>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.4rem;">
                    <button class="btn-add-cart" onclick="addToCart(${p.id},'${p.name.replace(/'/g,"\\'")}')">🛒 Add</button>
                    <button class="btn-add-cart" style="background:var(--accent);color:white;border-color:var(--accent);" onclick="buyNow(${p.id})">⚡ Buy</button>
                  </div>` : `<button class="btn-add-cart" style="opacity:0.5;cursor:not-allowed;margin-top:0.6rem;" disabled>❌ Out of Stock</button>`}
                </div>
              </div>
            `;
          }).join('');
        })
        .catch(() => {
          grid.innerHTML = '<div class="no-results"><span>⚠️</span>Could not load products.</div>';
        });
    }
    // ===== END IMAGE FIX =====

    function filterProducts(category, el) {
      event.preventDefault();
      document.querySelectorAll('.cat-card').forEach(c => c.classList.remove('active'));
      el.classList.add('active');
      loadProducts(category);
    }

    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    let timeout;

    searchInput.addEventListener('input', function() {
      clearTimeout(timeout);
      const q = this.value.trim();
      if (q.length < 2) { searchResults.classList.remove('active'); return; }
      timeout = setTimeout(() => {
        fetch('search.php?q=' + encodeURIComponent(q))
          .then(r => r.json())
          .then(data => {
            if (data.length === 0) {
              searchResults.innerHTML = `<div class="search-empty">No results for "${q}"</div>`;
            } else {
              searchResults.innerHTML = data.map(p => {
                const src = buildImageSrc(p.image);
                return `
                  <div class="search-item" onclick="window.location.href='product.php?id=${p.id}'">
                    <div class="search-item-img">
                      ${src ? `<img src="${src}" style="width:100%;height:100%;object-fit:cover;border-radius:6px;" onerror="this.remove();"/>` : (icons[p.category] || '📦')}
                    </div>
                    <div style="flex:1"><div class="search-item-name">${p.name}</div><div class="search-item-cat">${p.category}</div></div>
                    <div class="search-item-price">₹${parseFloat(p.price).toLocaleString('en-IN')}</div>
                  </div>
                `;
              }).join('');
            }
            searchResults.classList.add('active');
          });
      }, 300);
    });

    function toggleDropdown() {
      document.getElementById('dropdownMenu').classList.toggle('open');
    }

    document.addEventListener('click', function(e) {
      if (!searchInput.contains(e.target) && !searchResults.contains(e.target))
        searchResults.classList.remove('active');
      const dd = document.querySelector('.profile-dropdown');
      if (dd && !dd.contains(e.target))
        document.getElementById('dropdownMenu') && document.getElementById('dropdownMenu').classList.remove('open');
    });

    function changeQty(inputId, delta, maxStock) {
      const input = document.getElementById(inputId);
      let val = parseInt(input.value) + delta;
      if (val < 1) val = 1;
      if (val > maxStock) val = maxStock;
      input.value = val;
    }

    function updateCartBadge(count) {
      const badge = document.getElementById('cartBadge');
      if (!badge) return;
      badge.style.display = 'inline';
      badge.textContent = count > 5 ? '5+' : count;
    }

    function addToCart(id, name) {
      const qtyInput = document.getElementById('qty_' + id);
      const qty = qtyInput ? parseInt(qtyInput.value) : 1;
      fetch('add_to_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `product_id=${id}&quantity=${qty}`
      })
      .then(r => r.json())
      .then(data => {
        if (data.status === 'success') {
          updateCartBadge(data.cart_count);
          showToast('✅ ' + name + ' added to cart!');
          searchResults.classList.remove('active');
          searchInput.value = '';
        }
      });
    }

    function buyNow(id) {
      const qtyInput = document.getElementById('qty_' + id);
      const qty = qtyInput ? parseInt(qtyInput.value) : 1;
      fetch('add_to_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `product_id=${id}&quantity=${qty}`
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

    loadProducts();
  </script>
</body>
</html>
