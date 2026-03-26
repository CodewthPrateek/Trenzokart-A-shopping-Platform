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

    /* --- NAVIGATION --- */
    nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; background: rgba(245,239,230,0.96); backdrop-filter: blur(12px); border-bottom: 1px solid var(--warm); display: flex; align-items: center; justify-content: space-between; padding: 0 5vw; height: 68px; box-shadow: 0 2px 20px rgba(92,61,30,0.08); }
    .logo { font-family: "Playfair Display", serif; font-size: 1.8rem; font-weight: 900; color: var(--brown); letter-spacing: -1px; text-decoration: none; }
    .logo span { color: var(--accent); }
    .nav-home { display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: 50px; font-size: 0.85rem; font-weight: 600; text-decoration: none; color: var(--brown); border: 1.5px solid var(--warm); transition: all .2s; margin-right: 0.5rem; }
    .nav-home:hover { border-color: var(--accent); color: var(--accent); background: var(--white); }

    /* Search Bar */
    .search-wrapper { flex: 1; max-width: 480px; margin: 0 1.5rem; position: relative; }
    .search-wrapper input { width: 100%; padding: 0.65rem 1rem 0.65rem 2.8rem; border: 1.5px solid var(--warm); border-radius: 50px; font-size: 0.9rem; background: var(--white); outline: none; transition: 0.2s; }
    .search-wrapper input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(212,98,42,0.1); }
    .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--muted); }

    .search-results { position: absolute; top: 110%; left: 0; right: 0; background: var(--white); border: 1.5px solid var(--warm); border-radius: 16px; box-shadow: 0 12px 40px rgba(92,61,30,0.15); max-height: 320px; overflow-y: auto; display: none; z-index: 200; }
    .search-results.active { display: block; }
    .search-item { display: flex; align-items: center; gap: 0.8rem; padding: 0.8rem 1rem; cursor: pointer; border-bottom: 1px solid rgba(232,213,183,0.5); }
    .search-item:hover { background: var(--cream); }

    /* Buttons */
    .nav-right { display: flex; align-items: center; gap: 0.8rem; }
    .nav-btn { display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: 50px; font-size: 0.85rem; font-weight: 600; text-decoration: none; transition: 0.2s; }
    .btn-cart { background: var(--warm); color: var(--brown); border: none; }
    .btn-cart:hover { background: var(--accent); color: white; }
    .btn-outline { background: none; color: var(--muted); border: 1.5px solid var(--warm); }
    .btn-outline:hover { border-color: var(--accent); color: var(--accent); }

    /* PROFILE DROPDOWN */
    .profile-dropdown { position: relative; }
    .profile-btn { display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.9rem 0.4rem 0.4rem; background: var(--white); border: 1.5px solid var(--warm); border-radius: 50px; cursor: pointer; font-size: 0.85rem; font-weight: 600; color: var(--brown); }
    .profile-avatar { width: 30px; height: 30px; background: var(--accent); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; }
    .dropdown-menu { position: absolute; top: calc(100% + 10px); right: 0; background: var(--white); border: 1.5px solid var(--warm); border-radius: 16px; box-shadow: 0 12px 40px rgba(92,61,30,0.15); min-width: 200px; display: none; z-index: 300; overflow: hidden;}
    .dropdown-menu.open { display: block; animation: fadeDown 0.2s ease; }
    .dropdown-item { display: flex; align-items: center; gap: 0.6rem; padding: 0.8rem 1.2rem; font-size: 0.88rem; color: var(--text); text-decoration: none; border-bottom: 1px solid rgba(232,213,183,0.3); }
    .dropdown-item:hover { background: var(--cream); color: var(--accent); }

    /* --- HERO SECTION --- */
    .hero { margin-top: 68px; background: linear-gradient(135deg, var(--brown) 0%, #3a2010 60%, #1a0f02 100%); padding: 5rem 5vw; display: flex; align-items: center; justify-content: space-between; min-height: 420px; position: relative; }
    .hero-content { position: relative; z-index: 1; max-width: 520px; }
    .hero h1 { font-family: "Playfair Display", serif; font-size: clamp(2rem, 4vw, 3.2rem); color: var(--white); line-height: 1.15; margin-bottom: 1rem; }
    .hero h1 span { color: var(--accent2); }
    .hero p { color: rgba(255,255,255,0.65); font-size: 1rem; margin-bottom: 2rem; }
    .btn-primary { padding: 0.85rem 2rem; background: var(--accent); color: white; border-radius: 50px; text-decoration: none; font-weight: 600; display: inline-block; transition: 0.2s; }
    .btn-primary:hover { background: #c0551f; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(212,98,42,0.4); }

    /* --- CATEGORIES --- */
    .section { padding: 4rem 5vw; }
    .section-title { font-family: "Playfair Display", serif; font-size: 1.8rem; color: var(--brown); margin-bottom: 2rem; }
    .categories-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1.2rem; }
    .cat-card { background: var(--white); border-radius: 20px; padding: 2rem 1.5rem; text-align: center; cursor: pointer; transition: 0.2s; border: 1.5px solid transparent; text-decoration: none; display: block; }
    .cat-card:hover, .cat-card.active { border-color: var(--accent); background: #fff5f0; transform: translateY(-6px); }
    .cat-icon { font-size: 2.8rem; margin-bottom: 0.8rem; display: block; }
    .cat-name { font-weight: 600; color: var(--brown); }

    /* --- PRODUCTS SECTION & IMAGE FIX --- */
    .products-section { padding: 0 5vw 4rem; }
    .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.5rem; }
    .product-card { background: var(--white); border-radius: 20px; overflow: hidden; transition: 0.2s; border: 1.5px solid transparent; position: relative; }
    .product-card:hover { transform: translateY(-6px); box-shadow: 0 16px 40px rgba(92,61,30,0.12); border-color: var(--warm); }

    /* THE IMAGE FIX YOU ASKED FOR */
    .product-img { 
        width: 100%; height: 180px; background: #ffffff; 
        display: flex; align-items: center; justify-content: center; 
        position: relative; overflow: hidden; 
    }
    .product-img img { 
        max-width: 100%; max-height: 100%; width: auto; height: auto; 
        object-fit: contain; /* Sabse important: Image adjust hogi bina kate */
        display: block; 
    }

    .product-info { padding: 1.2rem; }
    .product-name { font-weight: 600; color: var(--text); font-size: 0.95rem; margin-bottom: 0.5rem; cursor: pointer; }
    .product-price { font-family: "Playfair Display", serif; font-size: 1.2rem; font-weight: 700; color: var(--brown); }
    .btn-add-cart { width: 100%; margin-top: 0.6rem; padding: 0.6rem; background: var(--cream); color: var(--brown); border: 1.5px solid var(--warm); border-radius: 10px; font-weight: 600; cursor: pointer; transition: 0.2s; }
    .btn-add-cart:hover { background: var(--accent); color: white; }

    /* --- FOOTER (NEW VERSION) --- */
    footer { background: var(--dark); color: var(--white); padding: 4rem 5vw 2rem; margin-top: 4rem; }
    .footer-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 3rem; margin-bottom: 3rem; }
    .footer-col h4 { font-family: 'Playfair Display', serif; color: var(--accent2); margin-bottom: 1.2rem; }
    .footer-links { list-style: none; }
    .footer-links li { margin-bottom: 0.6rem; }
    .footer-links a { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 0.9rem; transition: 0.2s; }
    .footer-links a:hover { color: var(--accent2); padding-left: 5px; }
    .btn-footer-sell { display: inline-block; padding: 0.8rem 1.6rem; background: var(--accent); color: white; text-decoration: none; border-radius: 50px; font-weight: 700; margin-top: 1rem; transition: 0.3s; }
    .btn-footer-sell:hover { background: var(--accent2); transform: translateY(-3px); }
    .footer-bottom { border-top: 1px solid rgba(255,255,255,0.1); padding-top: 2rem; text-align: center; color: rgba(255,255,255,0.4); font-size: 0.85rem; }

    /* Toast Notifications */
    .toast { position: fixed; bottom: 2rem; right: 2rem; background: var(--brown); color: white; padding: 0.8rem 1.5rem; border-radius: 12px; transform: translateY(100px); opacity: 0; transition: 0.3s; z-index: 999; }
    .toast.show { transform: translateY(0); opacity: 1; }

    @keyframes fadeDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

    /* RESPONSIVE */
    @media (max-width: 768px) {
      .categories-grid { grid-template-columns: repeat(2, 1fr); }
      .products-grid { grid-template-columns: repeat(2, 1fr); gap: 0.8rem; }
      .hero { padding: 3rem 4vw; flex-direction: column; text-align: center; }
    }
  </style>
</head>
<body>

  <nav>
    <a href="index.php" class="logo">Trenzo<span>Kart</span></a>
    <a href="index.php" class="nav-home">🏠 Home</a>

    <div class="search-wrapper">
      <span class="search-icon">🔍</span>
      <input type="text" id="searchInput" placeholder="Search products..." />
      <div class="search-results" id="searchResults"></div>
    </div>

    <div class="nav-right">
      <a href="cart.php" class="nav-btn btn-cart">🛒 Cart <span id="cartBadge" style="background:var(--accent); color:white; border-radius:50%; padding:2px 8px; font-size:0.75rem; margin-left:5px;"><?= $cart_count ?></span></a>
      
      <?php if ($user_name): ?>
        <div class="profile-dropdown">
          <button class="profile-btn" onclick="toggleDropdown()">
            <div class="profile-avatar"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
            <span><?= htmlspecialchars(explode(' ', $user_name)[0]) ?></span>
          </button>
          <div class="dropdown-menu" id="dropdownMenu">
            <a href="profile.php" class="dropdown-item">👤 Profile</a>
            <a href="my_orders.php" class="dropdown-item">📦 Orders</a>
            <a href="logout.php" class="dropdown-item logout-item" style="color:#dc3545;">🚪 Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="login.php" class="nav-btn btn-outline">Login</a>
        <a href="signup.php" class="nav-btn btn-cart" style="background:var(--accent); color:white;">Sign Up</a>
      <?php endif; ?>
    </div>
  </nav>

  <section class="hero">
    <div class="hero-content">
      <h1>Shop <span>Everything</span> You Need</h1>
      <p>Latest Fashion, Gadgets, and Home Essentials — All at TrenzoKart.</p>
      <a href="#products" class="btn-primary">Shop Now →</a>
    </div>
  </section>

  <section class="section" id="categories">
    <h2 class="section-title">Shop by Category</h2>
    <div class="categories-grid">
      <a class="cat-card active" href="#" onclick="filterProducts('all', this)"><span class="cat-icon">🛍️</span><div class="cat-name">All Products</div></a>
      <a class="cat-card" href="#" onclick="filterProducts('Clothes', this)"><span class="cat-icon">👕</span><div class="cat-name">Clothes</div></a>
      <a class="cat-card" href="#" onclick="filterProducts('Electronics', this)"><span class="cat-icon">📱</span><div class="cat-name">Electronics</div></a>
      <a class="cat-card" href="#" onclick="filterProducts('Grocery', this)"><span class="cat-icon">🛒</span><div class="cat-name">Grocery</div></a>
      <a class="cat-card" href="#" onclick="filterProducts('Food & Beverages', this)"><span class="cat-icon">🥤</span><div class="cat-name">Food</div></a>
    </div>
  </section>

  <section class="products-section" id="products">
    <h2 class="section-title">Featured Products</h2>
    <div class="products-grid" id="productsGrid">
      <div class="loading">Loading products... ⏳</div>
    </div>
  </section>

  <footer>
    <div class="footer-grid">
      <div class="footer-col">
        <a href="#" class="logo" style="color:white; margin-bottom:1rem; display:block;">Trenzo<span>Kart</span></a>
        <p>Premium e-commerce experience. We deliver quality and happiness right to your doorstep.</p>
      </div>
      <div class="footer-col">
        <h4>Quick Links</h4>
        <ul class="footer-links">
          <li><a href="index.php">Home</a></li>
          <li><a href="#categories">Categories</a></li>
          <li><a href="cart.php">Cart</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Partner with Us</h4>
        <p>Start selling your products on TrenzoKart and grow your business today!</p>
        <a href="vendor/login.php" class="btn-footer-sell">🚀 Sell with Us</a>
      </div>
    </div>
    <div class="footer-bottom">
      <p>© 2026 <strong>TrenzoKart</strong> — All Rights Reserved. <br> Created By <span>Prateek Verma</span></p>
    </div>
  </footer>

  <div class="toast" id="toast"></div>

  <script>
    const icons = { 'Clothes': '👕', 'Electronics': '📱', 'Grocery': '🥗', 'Food & Beverages': '🥤' };

    function loadProducts(category = 'all') {
      const grid = document.getElementById('productsGrid');
      grid.innerHTML = '<div class="loading">Loading...</div>';
      
      fetch('get_products.php?category=' + encodeURIComponent(category))
        .then(r => r.json())
        .then(products => {
          if (products.length === 0) { grid.innerHTML = 'No products found.'; return; }
          grid.innerHTML = products.map(p => {
            // Fix for multiple images
            const img = p.image ? p.image.split(',')[0] : null;
            return `
            <div class="product-card">
              <div class="product-img" onclick="window.location.href='product.php?id=${p.id}'" style="cursor:pointer;">
                ${img ? `<img src="${img}" alt="${p.name}"/>` : `<span>${icons[p.category] || '📦'}</span>`}
              </div>
              <div class="product-info">
                <div class="product-name" onclick="window.location.href='product.php?id=${p.id}'">${p.name}</div>
                <div class="product-price">₹${parseFloat(p.price).toLocaleString('en-IN')}</div>
                <button class="btn-add-cart" onclick="addToCart(${p.id},'${p.name}')">🛒 Add to Cart</button>
              </div>
            </div>`;
          }).join('');
        });
    }

    function filterProducts(category, el) {
      event.preventDefault();
      document.querySelectorAll('.cat-card').forEach(c => c.classList.remove('active'));
      el.classList.add('active');
      loadProducts(category);
    }

    function toggleDropdown() {
      document.getElementById('dropdownMenu').classList.toggle('open');
    }

    function showToast(msg) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.classList.add('show');
      setTimeout(() => t.classList.remove('show'), 3000);
    }

    function addToCart(id, name) {
      fetch('add_to_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `product_id=${id}&quantity=1`
      })
      .then(r => r.json())
      .then(data => {
        if (data.status === 'success') {
          document.getElementById('cartBadge').textContent = data.cart_count;
          showToast('✅ ' + name + ' added to cart!');
        }
      });
    }

    // Initialize
    loadProducts();

    // Close dropdown on click outside
    document.addEventListener('click', (e) => {
      const dd = document.querySelector('.profile-dropdown');
      if (dd && !dd.contains(e.target)) {
        document.getElementById('dropdownMenu').classList.remove('open');
      }
    });
  </script>
</body>
</html>