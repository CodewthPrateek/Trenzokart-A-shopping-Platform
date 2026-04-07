<?php
require '../config.php';
if (!isset($_SESSION['vendor_id'])) { header("Location: login.php"); exit(); }

$vendor_id   = $_SESSION['vendor_id'];
$vendor_name = $_SESSION['vendor_name'];
$vendor_shop = $_SESSION['vendor_shop'];
$commission  = $_SESSION['vendor_commission'];

$total_products  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM products WHERE vendor_id=$vendor_id"))['c'];
$total_orders    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE vendor_id=$vendor_id"))['c'];
$pending_orders  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE vendor_id=$vendor_id AND status='pending'"))['c'];
$total_revenue_r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_amount) as t FROM orders WHERE vendor_id=$vendor_id AND status!='cancelled'"))['t'] ?? 0;
$my_commission   = $total_revenue_r * ($commission / 100);
$my_earning      = $total_revenue_r - $my_commission;

$orders_result = mysqli_query($conn, "SELECT orders.*, users.name as user_name FROM orders JOIN users ON orders.user_id=users.id WHERE orders.vendor_id=$vendor_id ORDER BY orders.id DESC LIMIT 5");
$orders = [];
while ($row = mysqli_fetch_assoc($orders_result)) { $orders[] = $row; }

$products_result = mysqli_query($conn, "SELECT * FROM products WHERE vendor_id=$vendor_id ORDER BY id DESC LIMIT 5");
$products = [];
while ($row = mysqli_fetch_assoc($products_result)) { $products[] = $row; }

$icons = ['Clothes'=>'👕','Electronics'=>'📱','Grocery'=>'🥗','Food & Beverages'=>'🥤'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — Vendor Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{--cream:#f5efe6;--warm:#e8d5b7;--brown:#5c3d1e;--accent:#d4622a;--accent2:#e8a045;--text:#2d1a0a;--muted:#8a6a4a;--white:#fffdf8;--dark:#1a0f02;}
    body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--text);}

    /* ── DARK NAV ── */
    nav{position:fixed;top:0;left:0;right:0;z-index:1000;background:var(--dark);display:flex;align-items:center;gap:0.8rem;padding:0 4vw;height:65px;box-shadow:0 4px 20px rgba(0,0,0,0.35);}
    .logo{font-family:'Playfair Display',serif;font-size:1.45rem;font-weight:900;color:var(--white);text-decoration:none;flex-shrink:0;}
    .logo span{color:var(--accent2);}
    .nav-user{color:rgba(255,255,255,0.75);font-size:0.83rem;font-weight:500;white-space:nowrap;flex-shrink:0;cursor:default;}
    .nav-badge-shop{background:var(--accent2);color:var(--dark);font-size:0.7rem;font-weight:700;padding:0.18rem 0.55rem;border-radius:50px;white-space:nowrap;flex-shrink:0;}
    .nav-links{display:flex;align-items:center;gap:0.4rem;margin-left:auto;flex-shrink:0;}
    .nav-link{padding:0.38rem 0.85rem;border:1.5px solid rgba(255,255,255,0.22);border-radius:50px;color:rgba(255,255,255,0.75);font-family:'DM Sans',sans-serif;font-size:0.8rem;font-weight:600;text-decoration:none;cursor:pointer;transition:all .2s;white-space:nowrap;}
    .nav-link:hover{border-color:var(--accent2);color:var(--accent2);}
    .nav-link.logout{border-color:rgba(220,53,69,0.4);color:rgba(220,53,69,0.85);}
    .nav-link.logout:hover{border-color:#dc3545;color:#dc3545;background:rgba(220,53,69,0.08);}

    /* hamburger */
    .hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;background:none;border:none;padding:5px;z-index:1100;flex-shrink:0;margin-left:auto;}
    .hamburger span{display:block;width:22px;height:2px;background:rgba(255,255,255,0.85);border-radius:4px;transition:all .28s ease;}
    .hamburger.open span:nth-child(1){transform:translateY(7px) rotate(45deg);}
    .hamburger.open span:nth-child(2){opacity:0;transform:scaleX(0);}
    .hamburger.open span:nth-child(3){transform:translateY(-7px) rotate(-45deg);}

    /* ✅ FIX: Overlay sirf left side — sidebar (270px) ke upar NAHI */
    .nav-overlay{display:none;position:fixed;top:0;left:0;bottom:0;right:270px;background:rgba(0,0,0,0.5);z-index:1050;}
    .nav-overlay.open{display:block;}

    .sidebar-user-info{display:none;}

    /* MAIN */
    .main{margin-top:85px;padding:2rem 5vw;}
    .page-title{font-family:'Playfair Display',serif;font-size:1.8rem;color:var(--brown);margin-bottom:0.3rem;}
    .page-sub{color:var(--muted);font-size:0.88rem;margin-bottom:2rem;}
    .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;}
    .stat-card{background:var(--white);border-radius:16px;padding:1.5rem;box-shadow:0 4px 20px rgba(92,61,30,0.06);border-left:4px solid transparent;}
    .stat-card.orange{border-left-color:#d4622a;}.stat-card.yellow{border-left-color:#e8a045;}.stat-card.green{border-left-color:#2d8a4e;}.stat-card.blue{border-left-color:#004085;}
    .stat-icon{font-size:2rem;margin-bottom:0.5rem;}
    .stat-num{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:900;color:var(--brown);}
    .stat-label{font-size:0.78rem;color:var(--muted);margin-top:0.2rem;}
    .commission-box{background:linear-gradient(135deg,var(--dark) 0%,#3a2010 100%);border-radius:20px;padding:1.5rem 2rem;margin-bottom:2rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;}
    .comm-item{text-align:center;}
    .comm-num{font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;color:var(--accent2);}
    .comm-label{font-size:0.78rem;color:rgba(255,255,255,0.5);margin-top:0.2rem;}
    .comm-divider{width:1px;height:50px;background:rgba(255,255,255,0.1);}
    .quick-actions{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;}
    .action-btn{display:flex;align-items:center;gap:0.8rem;padding:1.2rem 1.5rem;background:var(--white);border-radius:16px;text-decoration:none;color:var(--text);font-weight:600;font-size:0.9rem;box-shadow:0 4px 20px rgba(92,61,30,0.06);transition:all .2s;border:1.5px solid transparent;}
    .action-btn:hover{border-color:var(--accent);transform:translateY(-3px);box-shadow:0 8px 24px rgba(92,61,30,0.12);}
    .action-icon{font-size:1.8rem;}
    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;}
    .card{background:var(--white);border-radius:20px;padding:1.5rem;box-shadow:0 4px 20px rgba(92,61,30,0.06);}
    .card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem;}
    .card-title{font-family:'Playfair Display',serif;font-size:1.1rem;color:var(--brown);}
    .view-all{font-size:0.82rem;color:var(--accent);text-decoration:none;font-weight:600;}
    .mini-table{width:100%;border-collapse:collapse;}
    .mini-table th{font-size:0.72rem;color:var(--muted);text-transform:uppercase;font-weight:700;padding:0.4rem 0.5rem;border-bottom:1.5px solid var(--warm);text-align:left;}
    .mini-table td{padding:0.7rem 0.5rem;font-size:0.82rem;border-bottom:1px solid rgba(232,213,183,0.4);}
    .mini-table tr:last-child td{border-bottom:none;}
    .status-badge{padding:0.2rem 0.6rem;border-radius:50px;font-size:0.68rem;font-weight:700;text-transform:uppercase;}
    .status-pending{background:#fff3cd;color:#856404;}.status-confirmed{background:#d4edda;color:#155724;}.status-shipped{background:#cce5ff;color:#004085;}.status-delivered{background:#d1ecf1;color:#0c5460;}.status-cancelled{background:#f8d7da;color:#721c24;}
    .product-item{display:flex;align-items:center;gap:0.8rem;padding:0.7rem 0;border-bottom:1px solid rgba(232,213,183,0.4);}
    .product-item:last-child{border-bottom:none;}
    .product-thumb{width:50px;height:50px;border-radius:10px;overflow:hidden;flex-shrink:0;background:var(--warm);display:flex;align-items:center;justify-content:center;font-size:1.3rem;}
    .product-thumb img{width:100%;height:100%;object-fit:cover;object-position:center;display:block;}
    .prod-info{flex:1;min-width:0;}
    .prod-name{font-size:0.85rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .prod-stock{font-size:0.72rem;color:var(--muted);margin-top:0.1rem;}
    .prod-price{font-size:0.85rem;color:var(--accent);font-weight:700;flex-shrink:0;}
    .empty-state{text-align:center;padding:2rem;color:var(--muted);font-size:0.88rem;}

    /* ── RESPONSIVE ── */
    @media(max-width:1024px){.stats-grid,.quick-actions{grid-template-columns:repeat(3,1fr);}}
    @media(max-width:768px){
      .stats-grid,.quick-actions{grid-template-columns:repeat(2,1fr)!important;}
      .two-col{grid-template-columns:1fr;}
      .commission-box{flex-direction:column;align-items:flex-start;}
      .comm-divider{width:100%;height:1px;}
      .main{padding:1.2rem 3vw;margin-top:65px;}
    }
    @media(max-width:500px){
      .action-btn{padding:0.9rem;font-size:0.82rem;}
      .stat-card{padding:1rem;}
      .stat-num{font-size:1.4rem;}
    }

    /* ── MOBILE SIDEBAR ── */
    @media(max-width:900px){
      .hamburger{display:flex;}
      .nav-user,.nav-badge-shop{display:none;}

      /* ✅ FIX: Sidebar z-index 1060 — overlay (1050) se upar */
      .nav-links{
        position:fixed;top:0;right:-290px;width:270px;height:100vh;
        background:#150a02;flex-direction:column;align-items:stretch;
        padding:72px 1.2rem 2rem;gap:0.4rem;
        z-index:1060;
        box-shadow:-6px 0 28px rgba(0,0,0,0.5);
        transition:right .3s ease;
        overflow-y:auto;margin-left:0;
        pointer-events:auto;
      }
      .nav-links.open{right:0;}

      /* ✅ Links bhi clickable rahenge */
      .nav-link{
        border-radius:10px;padding:0.75rem 1rem;font-size:0.9rem;
        border-color:rgba(255,255,255,0.12);
        position:relative;z-index:1061;
        pointer-events:auto;
      }

      .sidebar-user-info{
        display:block;color:rgba(255,255,255,0.55);font-size:0.78rem;
        padding:0 0.4rem 0.8rem;border-bottom:1px solid rgba(255,255,255,0.08);margin-bottom:0.5rem;
      }
      .sidebar-user-name{color:rgba(255,255,255,0.9);font-size:0.95rem;font-weight:700;margin-bottom:0.15rem;}
    }
  </style>
</head>
<body>

<!-- ✅ FIX: Overlay right:270px — sidebar ke upar cover nahi karega -->
<div class="nav-overlay" id="navOverlay"></div>

<nav>
  <a href="#" class="logo">Trenzo<span>Kart</span></a>
  <span class="nav-badge-shop">🏪 <?= htmlspecialchars($_SESSION['vendor_shop']) ?></span>
  <span class="nav-user"><?= htmlspecialchars($_SESSION['vendor_name']) ?></span>
  <button class="hamburger" id="hamburger" aria-label="Menu"><span></span><span></span><span></span></button>
  <div class="nav-links" id="navPanel">
    <div class="sidebar-user-info">
      <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['vendor_name']) ?></div>
      <div style="font-size:0.78rem;color:rgba(255,255,255,0.5);margin-top:0.15rem;">🏪 <?= htmlspecialchars($_SESSION['vendor_shop']) ?></div>
    </div>
    <a href="add_product.php" class="nav-link">➕ Add Product</a>
    <a href="products.php" class="nav-link">🏷️ Products</a>
    <a href="orders.php" class="nav-link">📦 Orders</a>
    <a href="returns.php" class="nav-link">↩ Returns</a>
    <a href="notifications.php" class="nav-link">📢 Notifications</a>
    <a href="delivery_boys.php" class="nav-link">🚚 Delivery</a>
    <a href="profile.php" class="nav-link">⚙️ Settings</a>
    <a href="logout.php" class="nav-link logout">🚪 Logout</a>
  </div>
</nav>

<div class="main">
  <h1 class="page-title">👋 Welcome, <?= htmlspecialchars($vendor_name) ?>!</h1>
  <p class="page-sub"><?= htmlspecialchars($vendor_shop) ?> — Vendor Dashboard</p>

  <div class="stats-grid">
    <div class="stat-card orange"><div class="stat-icon">📦</div><div class="stat-num"><?= $total_products ?></div><div class="stat-label">Total Products</div></div>
    <div class="stat-card yellow"><div class="stat-icon">🛒</div><div class="stat-num"><?= $total_orders ?></div><div class="stat-label">Total Orders</div></div>
    <div class="stat-card green"><div class="stat-icon">⏳</div><div class="stat-num"><?= $pending_orders ?></div><div class="stat-label">Pending Orders</div></div>
    <div class="stat-card blue"><div class="stat-icon">💰</div><div class="stat-num">₹<?= number_format($total_revenue_r,0) ?></div><div class="stat-label">Total Revenue</div></div>
  </div>

  <div class="commission-box">
    <div class="comm-item"><div class="comm-num">₹<?= number_format($total_revenue_r,0) ?></div><div class="comm-label">Total Sales</div></div>
    <div class="comm-divider"></div>
    <div class="comm-item"><div class="comm-num"><?= $commission ?>%</div><div class="comm-label">Commission Rate</div></div>
    <div class="comm-divider"></div>
    <div class="comm-item"><div class="comm-num">₹<?= number_format($my_commission,0) ?></div><div class="comm-label">Commission Paid</div></div>
    <div class="comm-divider"></div>
    <div class="comm-item"><div class="comm-num" style="color:#4ade80;">₹<?= number_format($my_earning,0) ?></div><div class="comm-label">Net Earnings</div></div>
  </div>

  <div class="quick-actions">
    <a href="add_product.php" class="action-btn"><span class="action-icon">➕</span> Add Product</a>
    <a href="orders.php" class="action-btn"><span class="action-icon">📦</span> Orders</a>
    <a href="products.php" class="action-btn"><span class="action-icon">🏷️</span> My Products</a>
    <a href="profile.php" class="action-btn"><span class="action-icon">⚙️</span> Settings</a>
  </div>

  <div class="two-col">
    <div class="card">
      <div class="card-header"><div class="card-title">🛒 Recent Orders</div><a href="orders.php" class="view-all">View All →</a></div>
      <?php if (empty($orders)): ?>
        <div class="empty-state">😴 No orders yet!</div>
      <?php else: ?>
        <table class="mini-table">
          <thead><tr><th>Order</th><th>Customer</th><th>Amount</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($orders as $o): ?>
            <tr>
              <td><strong>#TK<?= str_pad($o['id'],5,'0',STR_PAD_LEFT) ?></strong></td>
              <td><?= htmlspecialchars(explode(' ',$o['user_name'])[0]) ?></td>
              <td>₹<?= number_format($o['total_amount'],0) ?></td>
              <td><span class="status-badge status-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-header"><div class="card-title">🏷️ Recent Products</div><a href="products.php" class="view-all">View All →</a></div>
      <?php if (empty($products)): ?>
        <div class="empty-state">📭 <a href="add_product.php" style="color:var(--accent);">Add first product →</a></div>
      <?php else: ?>
        <?php foreach ($products as $p):
          $imgs = array_values(array_filter(array_map('trim', explode(',', $p['image'] ?? ''))));
          $first = $imgs[0] ?? '';
        ?>
        <div class="product-item">
          <div class="product-thumb">
            <?php if ($first): ?>
              <img src="../<?= htmlspecialchars($first) ?>" alt="" onerror="this.style.display='none'"/>
            <?php else: ?>
              <?= $icons[$p['category']] ?? '📦' ?>
            <?php endif; ?>
          </div>
          <div class="prod-info">
            <div class="prod-name"><?= htmlspecialchars($p['name']) ?></div>
            <div class="prod-stock">Stock: <?= $p['stock'] ?><?= $p['stock']==0?' ❌':($p['stock']<5?' ⚠️':'') ?></div>
          </div>
          <div class="prod-price">₹<?= number_format($p['price'],0) ?></div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function(){
  var hamburger = document.getElementById('hamburger');
  var navPanel  = document.getElementById('navPanel');
  var navOverlay= document.getElementById('navOverlay');

  function openNav(){
    navPanel.classList.add('open');
    hamburger.classList.add('open');
    navOverlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeNav(){
    navPanel.classList.remove('open');
    hamburger.classList.remove('open');
    navOverlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  // Hamburger toggle
  hamburger.addEventListener('click', function(e){
    e.stopPropagation();
    navPanel.classList.contains('open') ? closeNav() : openNav();
  });

  // ✅ Overlay click pe close — sidebar pe click nahi pakdega kyunki overlay wahan hai hi nahi
  navOverlay.addEventListener('click', closeNav);

  // Desktop pe bahar click pe close
  document.addEventListener('click', function(e){
    if(navPanel.classList.contains('open')){
      if(!navPanel.contains(e.target) && !hamburger.contains(e.target)){
        closeNav();
      }
    }
  });

  window.openNav  = openNav;
  window.closeNav = closeNav;
})();
</script>
</body>
</html>
