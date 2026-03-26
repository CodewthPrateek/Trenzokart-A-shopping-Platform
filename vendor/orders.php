<?php
require '../config.php';
if (!isset($_SESSION['vendor_id'])) { header("Location: login.php"); exit(); }
$vendor_id = $_SESSION['vendor_id'];
$success   = '';
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oid    = intval($_POST['order_id']);
    $action = $_POST['action'];
    $order_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT o.* FROM orders o JOIN order_items oi ON oi.order_id=o.id JOIN products p ON p.id=oi.product_id WHERE o.id='$oid' AND p.vendor_id='$vendor_id' LIMIT 1"));
    if (!$order_check) { $error = "Order not found!"; }
    else {
        if ($action === 'confirm' && $order_check['status'] === 'pending') {
            mysqli_query($conn, "UPDATE orders SET status='confirmed', confirmed_at=NOW() WHERE id='$oid'");
            $success = "Order #$oid confirmed!";
        } elseif ($action === 'dispatch' && $order_check['status'] === 'confirmed') {
            mysqli_query($conn, "UPDATE orders SET status='dispatched', dispatched_at=NOW() WHERE id='$oid'");
            $success = "Order #$oid dispatched!";
        } elseif ($action === 'ship' && $order_check['status'] === 'dispatched') {
            $courier  = mysqli_real_escape_string($conn, trim($_POST['courier_name']));
            $tracking = mysqli_real_escape_string($conn, trim($_POST['tracking_number']));
            if (empty($courier) || empty($tracking)) { $error = "Courier name aur tracking number required!"; }
            else {
                mysqli_query($conn, "UPDATE orders SET status='shipped', shipped_at=NOW(), courier_name='$courier', tracking_number='$tracking' WHERE id='$oid'");
                $success = "Order #$oid shipped! Tracking: $tracking";
            }
        } elseif ($action === 'deliver' && $order_check['status'] === 'shipped') {
            mysqli_query($conn, "UPDATE orders SET status='delivered', delivered_at=NOW() WHERE id='$oid'");
            $success = "Order #$oid delivered!";
        }
    }
}

$filter = $_GET['status'] ?? 'all';
$where  = "o.id IN (SELECT DISTINCT oi2.order_id FROM order_items oi2 JOIN products p2 ON p2.id=oi2.product_id WHERE p2.vendor_id='$vendor_id')";
if ($filter !== 'all') { $f = mysqli_real_escape_string($conn, $filter); $where .= " AND o.status='$f'"; }

$orders_result = mysqli_query($conn, "
    SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone,
           o.full_name as customer_full_name, COALESCE(o.phone, u.phone) as phone,
           GROUP_CONCAT(p.name SEPARATOR ', ') as product_name,
           MIN(p.image) as product_image, MIN(p.category) as product_category,
           SUM(oi.quantity) as total_qty
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN products p ON p.id = oi.product_id AND p.vendor_id='$vendor_id'
    WHERE $where GROUP BY o.id ORDER BY o.id DESC
");
$orders = [];
while ($row = mysqli_fetch_assoc($orders_result)) { $orders[] = $row; }

$counts = [];
foreach (['pending','confirmed','dispatched','shipped','delivered','cancelled'] as $s) {
    $counts[$s] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT o.id) as c FROM orders o JOIN order_items oi ON oi.order_id=o.id JOIN products p ON p.id=oi.product_id WHERE p.vendor_id='$vendor_id' AND o.status='$s'"))['c'];
}
$counts['all'] = array_sum($counts);

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url  = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/ecommerce/';
$icons     = ['Clothes'=>'👕','Electronics'=>'📱','Grocery'=>'🥗','Food & Beverages'=>'🥤'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — My Orders</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{--cream:#f5efe6;--warm:#e8d5b7;--brown:#5c3d1e;--accent:#d4622a;--accent2:#e8a045;--text:#2d1a0a;--muted:#8a6a4a;--white:#fffdf8;--dark:#1a0f02;}
    body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--text);}

    /* NAV */
    nav{position:fixed;top:0;left:0;right:0;z-index:100;background:var(--dark);display:flex;align-items:center;justify-content:space-between;padding:0 5vw;height:65px;box-shadow:0 4px 20px rgba(0,0,0,0.3);}
    .logo{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:900;color:var(--white);text-decoration:none;}
    .logo span{color:var(--accent2);}
    .nav-right{display:flex;gap:0.6rem;align-items:center;flex-wrap:wrap;}
    .nav-link{padding:0.4rem 0.9rem;border:1.5px solid rgba(255,255,255,0.25);border-radius:50px;color:rgba(255,255,255,0.7);font-size:0.8rem;font-weight:600;text-decoration:none;transition:all .2s;}
    .nav-link:hover{border-color:var(--accent2);color:var(--accent2);}

    .main{margin-top:85px;padding:2.5rem 5vw;}
    .page-title{font-family:'Playfair Display',serif;font-size:2rem;color:var(--brown);margin-bottom:0.3rem;}
    .page-sub{color:var(--muted);font-size:0.9rem;margin-bottom:2rem;}
    .msg{padding:0.85rem 1.2rem;border-radius:12px;font-size:0.88rem;margin-bottom:1.5rem;font-weight:500;}
    .msg.success{background:#d4edda;color:#155724;border:1.5px solid #b8dfc4;}
    .msg.error{background:#f8d7da;color:#721c24;border:1.5px solid #f5b8bc;}

    /* STATS */
    .stats-row{display:grid;grid-template-columns:repeat(6,1fr);gap:1rem;margin-bottom:2rem;}
    .stat-card{background:var(--white);border-radius:16px;padding:1rem;text-align:center;cursor:pointer;border:2px solid transparent;transition:all .2s;text-decoration:none;display:block;}
    .stat-card:hover,.stat-card.active{border-color:var(--accent);transform:translateY(-2px);box-shadow:0 6px 20px rgba(92,61,30,0.1);}
    .stat-icon{font-size:1.5rem;margin-bottom:0.3rem;}
    .stat-num{font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;color:var(--brown);}
    .stat-label{font-size:0.72rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:0.3px;}

    /* FILTER TABS */
    .filter-row{display:flex;gap:0.5rem;margin-bottom:1.5rem;flex-wrap:wrap;}
    .filter-tab{padding:0.45rem 1rem;border-radius:50px;font-size:0.8rem;font-weight:600;cursor:pointer;border:1.5px solid var(--warm);background:var(--white);color:var(--muted);text-decoration:none;transition:all .2s;display:flex;align-items:center;gap:0.4rem;}
    .filter-tab:hover,.filter-tab.active{background:var(--accent);border-color:var(--accent);color:white;}
    .tab-count{background:rgba(0,0,0,0.15);border-radius:50px;padding:0.05rem 0.45rem;font-size:0.7rem;}

    /* ORDER CARDS */
    .orders-list{display:flex;flex-direction:column;gap:1.2rem;}
    .order-card{background:var(--white);border-radius:20px;overflow:hidden;box-shadow:0 3px 18px rgba(92,61,30,0.07);border:1.5px solid var(--warm);}
    .order-strip{height:5px;display:block;}
    .strip-pending{background:linear-gradient(90deg,#ffc107,#ffca2c);}
    .strip-confirmed{background:linear-gradient(90deg,#17a2b8,#45c2d9);}
    .strip-dispatched{background:linear-gradient(90deg,var(--accent2),#f0b429);}
    .strip-shipped{background:linear-gradient(90deg,#6f42c1,#9b6ee8);}
    .strip-delivered{background:linear-gradient(90deg,#28a745,#48c774);}
    .strip-cancelled{background:linear-gradient(90deg,#dc3545,#f06674);}

    .order-top{display:flex;align-items:flex-start;justify-content:space-between;padding:1.2rem 1.5rem 0;flex-wrap:wrap;gap:0.8rem;}
    .order-id-wrap{display:flex;align-items:center;gap:0.7rem;flex-wrap:wrap;}
    .order-id{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;color:var(--brown);}
    .status-badge{padding:0.3rem 0.9rem;border-radius:50px;font-size:0.75rem;font-weight:700;}
    .s-pending{background:#fff3cd;color:#856404;}.s-confirmed{background:#d1ecf1;color:#0c5460;}.s-dispatched{background:#fff0d6;color:#854d00;}.s-shipped{background:#e9d8fd;color:#44337a;}.s-delivered{background:#d4edda;color:#155724;}.s-cancelled{background:#f8d7da;color:#721c24;}
    .order-date{color:var(--muted);font-size:0.8rem;}

    /* TIMELINE */
    .timeline{display:flex;align-items:center;gap:0;overflow-x:auto;}
    .tl-step{display:flex;flex-direction:column;align-items:center;gap:0.2rem;}
    .tl-dot{width:22px;height:22px;border-radius:50%;border:2.5px solid var(--warm);background:var(--cream);display:flex;align-items:center;justify-content:center;font-size:0.65rem;color:var(--muted);flex-shrink:0;}
    .tl-dot.done{background:#28a745;border-color:#28a745;color:white;}
    .tl-dot.active{background:var(--accent);border-color:var(--accent);color:white;}
    .tl-label{font-size:0.6rem;color:var(--muted);font-weight:600;white-space:nowrap;}
    .tl-line{width:24px;height:2.5px;background:var(--warm);margin-bottom:1rem;flex-shrink:0;}
    .tl-line.done{background:#28a745;}

    /* ORDER BODY */
    .order-body{display:grid;grid-template-columns:auto 1fr auto;gap:1.2rem;align-items:center;padding:1.2rem 1.5rem;}
    .product-img-wrap{width:68px;height:68px;border-radius:12px;overflow:hidden;background:var(--cream);display:flex;align-items:center;justify-content:center;font-size:2rem;flex-shrink:0;}
    /* ── KEY FIX: image puri fit ho ── */
    .product-img-wrap img{width:100%;height:100%;object-fit:cover;object-position:center;display:block;}
    .product-info .prod-name{font-weight:700;font-size:0.95rem;color:var(--text);margin-bottom:0.2rem;line-height:1.3;}
    .product-info .prod-cat{font-size:0.72rem;color:var(--accent);font-weight:600;text-transform:uppercase;margin-bottom:0.4rem;}
    .product-info .prod-meta{display:flex;gap:0.6rem;flex-wrap:wrap;}
    .prod-meta span{font-size:0.78rem;color:var(--muted);}
    .order-amount{text-align:right;flex-shrink:0;}
    .amount-val{font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:900;color:var(--brown);}
    .amount-label{font-size:0.72rem;color:var(--muted);}

    .expand-btn{display:flex;align-items:center;gap:0.5rem;padding:0.7rem 1.5rem;border-top:1px dashed var(--warm);cursor:pointer;color:var(--accent);font-size:0.83rem;font-weight:600;transition:background .2s;background:none;border-left:none;border-right:none;border-bottom:none;width:100%;font-family:'DM Sans',sans-serif;}
    .expand-btn:hover{background:var(--cream);}
    .expand-icon{font-size:0.75rem;transition:transform .2s;margin-left:auto;}
    .expand-icon.open{transform:rotate(180deg);}
    .customer-details{display:none;padding:1.2rem 1.5rem;background:var(--cream);border-top:1px solid var(--warm);}
    .customer-details.open{display:block;}
    .detail-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:0.8rem 2rem;}
    .detail-item label{font-size:0.7rem;font-weight:700;color:var(--muted);text-transform:uppercase;display:block;margin-bottom:0.2rem;}
    .detail-item span{font-size:0.88rem;color:var(--text);font-weight:500;}

    .tracking-info{padding:0.8rem 1.5rem;background:#e9d8fd;border-top:1px solid #d8c4f8;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;}
    .tracking-info span{font-size:0.82rem;color:#44337a;font-weight:600;}

    /* ACTION ROW */
    .action-row{padding:1rem 1.5rem;border-top:1px solid var(--warm);display:flex;align-items:center;gap:0.7rem;flex-wrap:wrap;background:var(--white);}
    .btn-action{padding:0.55rem 1.2rem;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.85rem;font-weight:700;cursor:pointer;border:none;transition:all .2s;display:inline-flex;align-items:center;gap:0.4rem;}
    .btn-confirm{background:linear-gradient(135deg,#17a2b8,#0d7a8c);color:white;}
    .btn-confirm:hover{background:linear-gradient(135deg,#0d7a8c,#0a5f6e);transform:translateY(-1px);}
    .btn-dispatch{background:linear-gradient(135deg,var(--accent2),#d4870a);color:white;}
    .btn-dispatch:hover{background:linear-gradient(135deg,#d4870a,#b36f08);transform:translateY(-1px);}
    .btn-ship{background:linear-gradient(135deg,#6f42c1,#5731a3);color:white;}
    .btn-ship:hover{background:linear-gradient(135deg,#5731a3,#421e87);transform:translateY(-1px);}
    .btn-deliver{background:linear-gradient(135deg,#28a745,#1e7e34);color:white;}
    .btn-deliver:hover{background:linear-gradient(135deg,#1e7e34,#155724);transform:translateY(-1px);}
    .btn-print{background:linear-gradient(135deg,#2d3748,#1a202c);color:white;margin-left:auto;}
    .btn-print:hover{background:linear-gradient(135deg,#1a202c,#000);transform:translateY(-1px);}

    /* MODALS */
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:200;display:none;align-items:center;justify-content:center;padding:1rem;backdrop-filter:blur(4px);}
    .modal-overlay.open{display:flex;}
    .modal{background:var(--white);border-radius:24px;padding:2rem;width:100%;max-width:480px;max-height:92vh;overflow-y:auto;box-shadow:0 24px 80px rgba(0,0,0,0.3);animation:slideUp .3s ease;}
    @keyframes slideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
    .modal-title{font-family:'Playfair Display',serif;font-size:1.3rem;color:var(--brown);margin-bottom:1.5rem;padding-bottom:0.8rem;border-bottom:1.5px solid var(--warm);display:flex;justify-content:space-between;align-items:center;}
    .modal-close{background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--muted);}
    .form-group{margin-bottom:1.1rem;}
    .form-group label{display:block;font-size:0.82rem;font-weight:600;color:var(--brown);margin-bottom:0.4rem;}
    .form-group input,.form-group select{width:100%;padding:0.75rem 1rem;border:1.5px solid var(--warm);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.92rem;color:var(--text);background:var(--cream);outline:none;transition:all .2s;}
    .form-group input:focus,.form-group select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(212,98,42,0.1);background:var(--white);}
    .btn-ship-confirm{width:100%;padding:0.85rem;background:linear-gradient(135deg,#6f42c1,#5731a3);color:white;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.95rem;font-weight:700;cursor:pointer;transition:all .2s;margin-top:0.5rem;}
    .slip-preview{border:1.5px solid var(--warm);border-radius:12px;overflow:hidden;margin-top:0.5rem;}

    .empty-state{text-align:center;padding:5rem 2rem;color:var(--muted);background:var(--white);border-radius:22px;border:1.5px dashed var(--warm);}
    .empty-state span{font-size:4rem;display:block;margin-bottom:1rem;}

    /* ── RESPONSIVE ── */
    @media(max-width:900px){.stats-row{grid-template-columns:repeat(3,1fr);}}
    @media(max-width:768px){
      nav{height:auto;flex-wrap:wrap;padding:0.6rem 4vw;gap:0.5rem;}
      .logo{font-size:1.3rem;}.nav-right{gap:0.4rem;}.nav-link{padding:0.3rem 0.6rem;font-size:0.72rem;}
      .main{margin-top:110px;padding:1.5rem 4vw;}
      .page-title{font-size:1.5rem;}
      .stats-row{grid-template-columns:repeat(3,1fr);gap:0.7rem;}
      .stat-card{padding:0.8rem;}.stat-num{font-size:1.3rem;}.stat-icon{font-size:1.2rem;}
      .order-body{grid-template-columns:auto 1fr;gap:0.8rem;}
      .order-amount{grid-column:span 2;display:flex;align-items:center;gap:0.8rem;justify-content:flex-start;}
      .timeline{display:none;}
      .order-top{padding:1rem 1rem 0;}
      .action-row{padding:0.8rem 1rem;gap:0.5rem;}
      .btn-action{padding:0.5rem 0.9rem;font-size:0.8rem;}
      .btn-print{margin-left:0;}
      .customer-details{padding:1rem;}
      .detail-grid{grid-template-columns:1fr;}
      .expand-btn{padding:0.6rem 1rem;}
    }
    @media(max-width:500px){
      .stats-row{grid-template-columns:repeat(2,1fr);}
      .filter-row{gap:0.4rem;}
      .filter-tab{padding:0.35rem 0.7rem;font-size:0.75rem;}
      .order-body{grid-template-columns:1fr;}
      .product-img-wrap{width:56px;height:56px;}
      .amount-val{font-size:1.1rem;}
      .order-id{font-size:1rem;}
    }
  </style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="logo">Trenzo<span>Kart</span></a>
  <div class="nav-right">
    <a href="products.php" class="nav-link">Products</a>
    <a href="returns.php" class="nav-link">↩ Returns</a>
    <a href="notifications.php" class="nav-link">🔔</a>
    <a href="dashboard.php" class="nav-link">← Dashboard</a>
    <a href="logout.php" class="nav-link">Logout</a>
  </div>
</nav>

<div class="main">
  <h1 class="page-title">🧾 My Orders</h1>
  <p class="page-sub">Orders manage karo — confirm, dispatch, ship karo</p>

  <?php if (!empty($success)): ?><div class="msg success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if (!empty($error)): ?><div class="msg error">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- STATS -->
  <div class="stats-row">
    <?php
    $stat_items = ['all'=>['🧾','All'],'pending'=>['⏳','Pending'],'confirmed'=>['✅','Confirmed'],'dispatched'=>['📦','Dispatched'],'shipped'=>['🚚','Shipped'],'delivered'=>['🎉','Delivered']];
    foreach ($stat_items as $s => $info):
    ?>
    <a href="orders.php?status=<?= $s ?>" class="stat-card <?= $filter===$s?'active':'' ?>">
      <div class="stat-icon"><?= $info[0] ?></div>
      <div class="stat-num"><?= $counts[$s]??0 ?></div>
      <div class="stat-label"><?= $info[1] ?></div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- FILTER TABS -->
  <div class="filter-row">
    <?php
    $tabs = ['all'=>'📋 All','pending'=>'⏳ Pending','confirmed'=>'✅ Confirmed','dispatched'=>'📦 Dispatched','shipped'=>'🚚 Shipped','delivered'=>'🎉 Delivered','cancelled'=>'❌ Cancelled'];
    foreach ($tabs as $s => $label):
    ?>
    <a href="orders.php?status=<?= $s ?>" class="filter-tab <?= $filter===$s?'active':'' ?>">
      <?= $label ?> <span class="tab-count"><?= $counts[$s]??0 ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($orders)): ?>
    <div class="empty-state"><span>📭</span><p>Is category mein koi order nahi!</p></div>
  <?php else: ?>
  <div class="orders-list">
    <?php foreach ($orders as $o):
      $status = $o['status'];
      $steps  = ['pending','confirmed','dispatched','shipped','delivered'];
      $cur    = array_search($status, $steps);
      $token  = md5('trenzokart_' . $o['id'] . '_label');
      $label_url = $base_url . 'order_label.php?id=' . $o['id'] . '&token=' . $token;

      // Get first image from comma-separated list
      $prod_imgs = array_values(array_filter(array_map('trim', explode(',', $o['product_image'] ?? ''))));
      $prod_first_img = $prod_imgs[0] ?? '';
    ?>
    <div class="order-card">
      <div class="order-strip strip-<?= $status ?>"></div>

      <div class="order-top">
        <div class="order-id-wrap">
          <span class="order-id">#TK<?= str_pad($o['id'],5,'0',STR_PAD_LEFT) ?></span>
          <span class="status-badge s-<?= $status ?>"><?= strtoupper($status) ?></span>
        </div>
        <!-- Timeline (hidden on mobile) -->
        <div class="timeline">
          <?php foreach ($steps as $i => $step):
            $dc = ($i < $cur) ? 'done' : (($i == $cur && $status !== 'delivered') ? 'active' : ($i <= $cur ? 'done' : ''));
          ?>
            <?php if ($i > 0): ?><div class="tl-line <?= $i<=$cur?'done':'' ?>"></div><?php endif; ?>
            <div class="tl-step">
              <div class="tl-dot <?= $dc ?>"><?= $dc==='done'?'✓':($i+1) ?></div>
              <div class="tl-label"><?= ucfirst($step) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <span class="order-date">📅 <?= date('d M Y, h:i A', strtotime($o['created_at'])) ?></span>
      </div>

      <div class="order-body">
        <!-- Product image — proper fit -->
        <div class="product-img-wrap">
          <?php if ($prod_first_img): ?>
            <img src="../<?= htmlspecialchars($prod_first_img) ?>" alt=""
                 onerror="this.style.display='none'"/>
          <?php else: ?>
            <?= $icons[$o['product_category']] ?? '📦' ?>
          <?php endif; ?>
        </div>
        <div class="product-info">
          <div class="prod-name"><?= htmlspecialchars($o['product_name'] ?? 'Product') ?></div>
          <div class="prod-cat"><?= htmlspecialchars($o['product_category'] ?? '') ?></div>
          <div class="prod-meta">
            <span>👤 <?= htmlspecialchars($o['full_name'] ?? $o['customer_name']) ?></span>
            <span>📦 Qty: <?= $o['total_qty'] ?></span>
            <?php if (!empty($o['confirmed_at'])): ?><span>✅ <?= date('d M', strtotime($o['confirmed_at'])) ?></span><?php endif; ?>
            <?php if (!empty($o['shipped_at'])): ?><span>🚚 <?= date('d M', strtotime($o['shipped_at'])) ?></span><?php endif; ?>
          </div>
        </div>
        <div class="order-amount">
          <div class="amount-val">₹<?= number_format($o['total_amount'],2) ?></div>
          <div class="amount-label"><?= strtoupper($o['payment_method']??'COD') ?></div>
        </div>
      </div>

      <?php if (!empty($o['tracking_number'])): ?>
      <div class="tracking-info">
        <span>🚚 <?= htmlspecialchars($o['courier_name']) ?></span>
        <span>📍 <?= htmlspecialchars($o['tracking_number']) ?></span>
      </div>
      <?php endif; ?>

      <button class="expand-btn" onclick="toggleDetails(<?= $o['id'] ?>)">
        👤 Customer Details <span class="expand-icon" id="icon-<?= $o['id'] ?>">▼</span>
      </button>
      <div class="customer-details" id="details-<?= $o['id'] ?>">
        <div class="detail-grid">
          <div class="detail-item"><label>Customer Name</label><span><?= htmlspecialchars($o['full_name'] ?? $o['customer_name']) ?></span></div>
          <div class="detail-item"><label>Email</label><span><?= htmlspecialchars($o['customer_email']) ?></span></div>
          <div class="detail-item"><label>Phone</label><span><?= htmlspecialchars($o['phone'] ?? $o['customer_phone'] ?? 'N/A') ?></span></div>
          <div class="detail-item"><label>Payment</label><span><?= strtoupper($o['payment_method']??'COD') ?></span></div>
          <div class="detail-item" style="grid-column:span 2"><label>Delivery Address</label><span><?= htmlspecialchars($o['address']??'N/A') ?></span></div>
        </div>
      </div>

      <div class="action-row">
        <?php if ($status !== 'cancelled' && $status !== 'delivered'): ?>
          <?php if ($status === 'pending'): ?>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="order_id" value="<?= $o['id'] ?>"/>
              <input type="hidden" name="action" value="confirm"/>
              <button type="submit" class="btn-action btn-confirm" onclick="return confirm('Confirm order?')">✅ Confirm</button>
            </form>
          <?php elseif ($status === 'confirmed'): ?>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="order_id" value="<?= $o['id'] ?>"/>
              <input type="hidden" name="action" value="dispatch"/>
              <button type="submit" class="btn-action btn-dispatch" onclick="return confirm('Dispatch?')">📦 Dispatch</button>
            </form>
          <?php elseif ($status === 'dispatched'): ?>
            <button class="btn-action btn-ship" onclick="openShipModal(<?= $o['id'] ?>)">🚚 Ship</button>
          <?php elseif ($status === 'shipped'): ?>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="order_id" value="<?= $o['id'] ?>"/>
              <input type="hidden" name="action" value="deliver"/>
              <button type="submit" class="btn-action btn-deliver" onclick="return confirm('Mark delivered?')">🎉 Delivered</button>
            </form>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($status !== 'cancelled'): ?>
        <button class="btn-action btn-print" onclick='openPrintSlip(<?= json_encode([
          "id"       => $o["id"],
          "name"     => $o["full_name"] ?? $o["customer_name"],
          "phone"    => $o["phone"] ?? $o["customer_phone"] ?? "",
          "address"  => $o["address"] ?? "",
          "product"  => $o["product_name"] ?? "Product",
          "amount"   => number_format($o["total_amount"],2),
          "payment"  => strtoupper($o["payment_method"] ?? "COD"),
          "tracking" => $o["tracking_number"] ?? "",
          "courier"  => $o["courier_name"] ?? "",
          "date"     => date("d M Y", strtotime($o["created_at"])),
          "label_url"=> $label_url,
        ]) ?>)'>🖨️ Print</button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- SHIP MODAL -->
<div class="modal-overlay" id="shipModal">
  <div class="modal">
    <div class="modal-title">🚚 Shipping Details <button class="modal-close" onclick="closeShipModal()">✕</button></div>
    <form method="POST">
      <input type="hidden" name="order_id" id="ship_order_id"/>
      <input type="hidden" name="action" value="ship"/>
      <div class="form-group">
        <label>Courier Company *</label>
        <select name="courier_name" id="courierSelect" required onchange="generateTracking(this.value)">
          <option value="">-- Select Courier --</option>
          <option value="Delhivery">🚚 Delhivery</option>
          <option value="Bluedart">🔵 Bluedart</option>
          <option value="DTDC">📦 DTDC</option>
          <option value="Ekart">🛒 Ekart</option>
          <option value="India Post">📮 India Post</option>
          <option value="Amazon Logistics">📦 Amazon Logistics</option>
          <option value="Xpressbees">🐝 Xpressbees</option>
          <option value="Shadowfax">🦊 Shadowfax</option>
          <option value="Other">📦 Other</option>
        </select>
      </div>
      <div class="form-group">
        <label style="display:flex;justify-content:space-between;align-items:center;">
          Tracking Number *
          <button type="button" onclick="generateTracking(document.getElementById('courierSelect').value)"
            style="font-size:0.72rem;color:var(--accent);background:none;border:none;cursor:pointer;font-weight:700;font-family:'DM Sans',sans-serif;">
            🔄 Regenerate
          </button>
        </label>
        <input type="text" name="tracking_number" id="trackingInput"
               placeholder="Courier select karo — auto generate hoga"
               style="font-family:monospace;font-size:0.95rem;font-weight:700;letter-spacing:1px;"
               required/>
        <div style="font-size:0.72rem;color:var(--muted);margin-top:0.3rem;">
          💡 Courier select karte hi auto-generate hota hai • Edit bhi kar sakte ho
        </div>
      </div>
      <button type="submit" class="btn-ship-confirm">🚚 Confirm Shipment →</button>
    </form>
  </div>
</div>

<!-- PRINT SLIP MODAL -->
<div class="modal-overlay" id="printModal">
  <div class="modal" style="max-width:440px;">
    <div class="modal-title">🖨️ Shipping Label <button class="modal-close" onclick="closePrintModal()">✕</button></div>
    <div id="printSlipContent" class="slip-preview"></div>
    <div style="display:flex;gap:0.8rem;margin-top:1.2rem;">
      <button onclick="printSlip()" style="flex:1;padding:0.8rem;background:var(--accent);color:white;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.95rem;font-weight:700;cursor:pointer;">🖨️ Print</button>
      <button onclick="closePrintModal()" style="flex:1;padding:0.8rem;background:var(--warm);color:var(--brown);border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.95rem;font-weight:600;cursor:pointer;">Cancel</button>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
function toggleDetails(id) {
  document.getElementById('details-'+id).classList.toggle('open');
  document.getElementById('icon-'+id).classList.toggle('open');
}

// ── AUTO TRACKING NUMBER GENERATOR ──
const courierPrefixes = {
  'Delhivery':        { prefix: 'DL',  len: 12, suffix: 'IN' },
  'Bluedart':         { prefix: 'BD',  len: 11, suffix: ''   },
  'DTDC':             { prefix: 'D',   len: 12, suffix: 'IN' },
  'Ekart':            { prefix: 'EK',  len: 10, suffix: ''   },
  'India Post':       { prefix: 'EP',  len: 11, suffix: 'IN' },
  'Amazon Logistics': { prefix: 'AZ',  len: 12, suffix: ''   },
  'Xpressbees':       { prefix: 'XB',  len: 12, suffix: 'IN' },
  'Shadowfax':        { prefix: 'SF',  len: 11, suffix: ''   },
  'Other':            { prefix: 'TK',  len: 10, suffix: ''   },
};

function generateTracking(courier) {
  const input = document.getElementById('trackingInput');
  if (!courier) { input.value = ''; input.placeholder = 'Courier select karo — auto generate hoga'; return; }
  const cfg = courierPrefixes[courier] || { prefix: 'TK', len: 10, suffix: '' };
  const digits = cfg.len - cfg.prefix.length - cfg.suffix.length;
  let num = '';
  for (let i = 0; i < digits; i++) num += Math.floor(Math.random() * 10);
  input.value = cfg.prefix + num + cfg.suffix;
}
function openShipModal(id) { document.getElementById('ship_order_id').value=id; document.getElementById('shipModal').classList.add('open'); }
function closeShipModal() { document.getElementById('shipModal').classList.remove('open'); }
function closePrintModal() { document.getElementById('printModal').classList.remove('open'); }
document.getElementById('shipModal').addEventListener('click',function(e){if(e.target===this)closeShipModal();});
document.getElementById('printModal').addEventListener('click',function(e){if(e.target===this)closePrintModal();});

function openPrintSlip(order) {
  const phone = order.phone.replace(/\D/g, '');
  const qrUrl = 'tel:' + phone;
  const tempDiv = document.createElement('div');
  tempDiv.style.cssText = 'position:absolute;left:-9999px;top:-9999px;';
  document.body.appendChild(tempDiv);
  new QRCode(tempDiv, { text: qrUrl, width: 90, height: 90, colorDark:'#000', colorLight:'#fff' });
  setTimeout(() => {
    let qrSrc = '';
    const canvas = tempDiv.querySelector('canvas');
    const img    = tempDiv.querySelector('img');
    if (canvas) qrSrc = canvas.toDataURL('image/png');
    else if (img) qrSrc = img.src;
    document.body.removeChild(tempDiv);
    const qrHtml = qrSrc ? `<img src="${qrSrc}" width="90" height="90" style="display:block;"/>` : '';
    const slip = `<div id="slipInner" style="font-family:Arial,sans-serif;background:white;">
      <div style="background:#1a0f02;padding:8px 12px;display:flex;align-items:center;justify-content:space-between;">
        <div style="font-size:18px;font-weight:900;color:#fff;">Trenzo<span style="color:#e8a045;">Kart</span></div>
        <div style="color:#aaa;font-size:8px;letter-spacing:2px;font-weight:700;">SHIPPING LABEL</div>
      </div>
      <div style="background:#f0f0f0;padding:6px 12px;display:flex;justify-content:space-between;border-bottom:2px solid #000;">
        <div><div style="font-size:7px;color:#666;font-weight:700;text-transform:uppercase;">ORDER ID</div>
          <div style="font-size:20px;font-weight:900;color:#000;">#TK${String(order.id).padStart(5,'0')}</div></div>
        <div style="text-align:right;"><div style="font-size:7px;color:#666;font-weight:700;text-transform:uppercase;">DATE</div>
          <div style="font-size:11px;font-weight:700;">${order.date}</div></div>
      </div>
      <div style="padding:6px 12px;border-bottom:1px dashed #bbb;">
        <div style="font-size:7px;color:#666;font-weight:700;text-transform:uppercase;margin-bottom:2px;">FROM</div>
        <div style="font-size:11px;font-weight:700;">TrenzoKart Fulfillment Center</div>
      </div>
      <div style="padding:8px 12px;border-bottom:2px solid #000;display:flex;gap:10px;align-items:flex-start;">
        <div style="flex:1;">
          <div style="font-size:7px;color:#666;font-weight:700;text-transform:uppercase;margin-bottom:4px;">SHIP TO</div>
          <div style="font-size:17px;font-weight:900;color:#000;margin-bottom:3px;">${order.name}</div>
          <div style="font-size:13px;font-weight:700;color:#000;margin-bottom:2px;">📞 ${order.phone}</div>
          <div style="font-size:11px;color:#333;line-height:1.5;">📍 ${order.address}</div>
        </div>
        <div style="text-align:center;flex-shrink:0;">
          <div style="font-size:7px;color:#666;font-weight:700;letter-spacing:1px;margin-bottom:3px;">📞 CALL QR</div>
          ${qrHtml}
          <div style="font-size:7px;color:#999;margin-top:2px;">Scan to Call</div>
        </div>
      </div>
      <div style="padding:7px 12px;border-bottom:1px solid #ddd;display:flex;justify-content:space-between;align-items:center;background:#fafafa;">
        <div>
          <div style="font-size:7px;color:#666;font-weight:700;text-transform:uppercase;">PRODUCT</div>
          <div style="font-size:12px;font-weight:700;">${order.product}</div>
        </div>
        <div style="text-align:right;">
          <div style="font-size:15px;font-weight:900;color:#000;">₹${order.amount}</div>
          <span style="font-size:9px;font-weight:700;color:#c00;background:#ffe;padding:2px 6px;border:1px solid #c00;border-radius:3px;">${order.payment}</span>
        </div>
      </div>
      ${order.tracking ? `<div style="padding:6px 12px;border-bottom:1px solid #ddd;display:flex;gap:20px;background:#f0ecff;">
        <div><div style="font-size:7px;color:#44337a;font-weight:700;text-transform:uppercase;">COURIER</div><div style="font-size:11px;font-weight:700;">${order.courier}</div></div>
        <div><div style="font-size:7px;color:#44337a;font-weight:700;text-transform:uppercase;">TRACKING #</div><div style="font-size:11px;font-weight:700;">${order.tracking}</div></div>
      </div>` : ''}
      <div style="background:#1a0f02;padding:6px 12px;text-align:center;">
        <div style="font-size:8px;color:#aaa;letter-spacing:1px;">THANK YOU FOR SHOPPING WITH TRENZOKART 🛍️</div>
      </div>
    </div>`;
    document.getElementById('printSlipContent').innerHTML = slip;
    document.getElementById('printModal').classList.add('open');
  }, 300);
}

function printSlip() {
  const content = document.getElementById('slipInner').outerHTML;
  const win = window.open('', '_blank', 'width=450,height=680');
  win.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8"/><title>Shipping Label</title>
  <style>*{box-sizing:border-box;margin:0;padding:0;}body{font-family:Arial,sans-serif;background:white;}
  @page{size:4in 6in;margin:0;}@media print{html,body{width:4in;height:6in;overflow:hidden;}}</style>
  </head><body>${content}</body></html>`);
  win.document.close(); win.focus();
  setTimeout(() => { win.print(); win.close(); }, 600);
}
</script>
</body>
</html>