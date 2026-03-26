<?php
require '../config.php';
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
$success = '';

// Assign delivery boy
if (isset($_POST['assign_db'])) {
    $oid   = intval($_POST['order_id']);
    $db_id = intval($_POST['delivery_boy_id']);
    mysqli_query($conn, "UPDATE orders SET delivery_boy_id='$db_id' WHERE id='$oid'");
    $success = "Delivery boy assigned successfully!";
}

$filter = $_GET['status'] ?? 'all';
$where  = "1=1";
if ($filter !== 'all') { $where = "o.status='" . mysqli_real_escape_string($conn, $filter) . "'"; }

$result = mysqli_query($conn, "
    SELECT o.*, u.name as customer_name, u.email as customer_email,
           o.full_name, o.phone as order_phone,
           v.name as vendor_name, v.shop_name,
           db.name as delivery_boy_name, db.phone as db_phone, db.company as db_company,
           GROUP_CONCAT(p.name SEPARATOR ', ') as product_names
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN vendors v ON o.vendor_id = v.id
    LEFT JOIN delivery_boys db ON o.delivery_boy_id = db.id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE $where
    GROUP BY o.id
    ORDER BY o.id DESC
");
$orders = [];
while ($row = mysqli_fetch_assoc($result)) { $orders[] = $row; }

// Active delivery boys
$db_result = mysqli_query($conn, "SELECT * FROM delivery_boys WHERE status='active' ORDER BY name ASC");
$delivery_boys = [];
while ($row = mysqli_fetch_assoc($db_result)) { $delivery_boys[] = $row; }

$counts = [];
foreach (['pending','confirmed','dispatched','shipped','delivered','cancelled'] as $s) {
    $counts[$s] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE status='$s'"))['c'];
}
$counts['all'] = array_sum($counts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart Admin — Orders</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{--cream:#f5efe6;--warm:#e8d5b7;--brown:#5c3d1e;--accent:#d4622a;--accent2:#e8a045;--text:#2d1a0a;--muted:#8a6a4a;--white:#fffdf8;--dark:#1a0f02;}
    body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--text);}
    nav{position:fixed;top:0;left:0;right:0;z-index:100;background:var(--dark);display:flex;align-items:center;justify-content:space-between;padding:0 5vw;height:65px;box-shadow:0 4px 20px rgba(0,0,0,0.3);}
    .logo{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:900;color:var(--white);text-decoration:none;}
    .logo span{color:var(--accent2);}
    .nav-right{display:flex;gap:0.6rem;align-items:center;flex-wrap:wrap;}
    .nav-link{padding:0.4rem 0.9rem;border:1.5px solid rgba(255,255,255,0.25);border-radius:50px;color:rgba(255,255,255,0.7);font-size:0.8rem;font-weight:600;text-decoration:none;transition:all .2s;}
    .nav-link:hover{border-color:var(--accent2);color:var(--accent2);}
    .main{margin-top:85px;padding:2rem 5vw;}
    .page-title{font-family:'Playfair Display',serif;font-size:2rem;color:var(--brown);margin-bottom:0.3rem;}
    .page-sub{color:var(--muted);font-size:0.9rem;margin-bottom:2rem;}
    .msg{padding:0.85rem 1.2rem;border-radius:12px;font-size:0.88rem;margin-bottom:1.5rem;font-weight:500;background:#d4edda;color:#155724;border:1.5px solid #b8dfc4;}
    .stats-row{display:grid;grid-template-columns:repeat(7,1fr);gap:0.8rem;margin-bottom:2rem;}
    .stat-card{background:var(--white);border-radius:14px;padding:0.9rem;text-align:center;box-shadow:0 2px 12px rgba(92,61,30,0.07);text-decoration:none;display:block;border:2px solid transparent;transition:all .2s;}
    .stat-card:hover,.stat-card.active{border-color:var(--accent);transform:translateY(-2px);}
    .stat-num{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:900;color:var(--brown);}
    .stat-label{font-size:0.68rem;color:var(--muted);font-weight:600;text-transform:uppercase;}
    .filter-row{display:flex;gap:0.5rem;margin-bottom:1.5rem;flex-wrap:wrap;}
    .filter-tab{padding:0.4rem 1rem;border-radius:50px;font-size:0.8rem;font-weight:600;border:1.5px solid var(--warm);background:var(--white);color:var(--muted);text-decoration:none;transition:all .2s;}
    .filter-tab.active,.filter-tab:hover{background:var(--accent);border-color:var(--accent);color:white;}

    /* ORDER CARDS */
    .orders-list{display:flex;flex-direction:column;gap:1.2rem;}
    .order-card{background:var(--white);border-radius:20px;overflow:hidden;box-shadow:0 3px 18px rgba(92,61,30,0.07);border:1.5px solid var(--warm);}
    .card-strip{height:4px;}
    .strip-pending{background:linear-gradient(90deg,#ffc107,#ffca2c);}
    .strip-confirmed{background:linear-gradient(90deg,#17a2b8,#45c2d9);}
    .strip-dispatched{background:linear-gradient(90deg,var(--accent2),#f0b429);}
    .strip-shipped{background:linear-gradient(90deg,#6f42c1,#9b6ee8);}
    .strip-delivered{background:linear-gradient(90deg,#28a745,#48c774);}
    .strip-cancelled{background:linear-gradient(90deg,#dc3545,#f06674);}
    .card-body{padding:1.2rem 1.5rem;}
    .card-top{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:0.8rem;margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid var(--warm);}
    .order-id{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;color:var(--brown);}
    .order-date{font-size:0.75rem;color:var(--muted);margin-top:0.2rem;}
    .badges{display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;}
    .status-badge{padding:0.25rem 0.8rem;border-radius:50px;font-size:0.72rem;font-weight:700;text-transform:uppercase;}
    .s-pending{background:#fff3cd;color:#856404;}
    .s-confirmed{background:#d1ecf1;color:#0c5460;}
    .s-dispatched{background:#fff0d6;color:#854d00;}
    .s-shipped{background:#e9d8fd;color:#44337a;}
    .s-delivered{background:#d4edda;color:#155724;}
    .s-cancelled{background:#f8d7da;color:#721c24;}
    .pickup-badge{background:#d4edda;color:#155724;padding:0.2rem 0.6rem;border-radius:50px;font-size:0.68rem;font-weight:700;border:1px solid #b8dfc4;}
    .info-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1rem;}
    .info-item label{font-size:0.68rem;font-weight:700;color:var(--muted);text-transform:uppercase;display:block;margin-bottom:0.2rem;}
    .info-item span{font-size:0.88rem;color:var(--text);font-weight:500;}

    /* DELIVERY BOY ASSIGN */
    .assign-section{background:var(--cream);border-radius:12px;padding:1rem;border:1.5px solid var(--warm);}
    .assign-title{font-size:0.78rem;font-weight:700;color:var(--brown);margin-bottom:0.7rem;}
    .assign-form{display:flex;gap:0.6rem;flex-wrap:wrap;align-items:center;}
    .assign-select{flex:1;min-width:180px;padding:0.6rem 0.9rem;border:1.5px solid var(--warm);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.85rem;color:var(--text);background:var(--white);outline:none;}
    .assign-select:focus{border-color:var(--accent);}
    .btn-assign{padding:0.6rem 1.3rem;background:var(--accent);color:white;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.85rem;font-weight:700;cursor:pointer;transition:all .2s;}
    .btn-assign:hover{background:#c0551f;transform:translateY(-1px);}
    .assigned-box{display:flex;align-items:center;gap:0.8rem;background:#e8f5e9;border:1.5px solid #a5d6a7;border-radius:10px;padding:0.7rem 1rem;}
    .assigned-name{font-weight:700;font-size:0.9rem;color:#155724;}
    .assigned-detail{font-size:0.75rem;color:#2e7d32;}
    .btn-reassign{padding:0.4rem 0.8rem;background:white;color:var(--accent);border:1.5px solid var(--accent);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:0.78rem;font-weight:700;cursor:pointer;margin-left:auto;}

    /* TRACKING TIMELINE */
    .tracking-toggle{display:flex;align-items:center;justify-content:space-between;padding:0.6rem 0;cursor:pointer;color:var(--accent);font-size:0.82rem;font-weight:700;margin-top:0.8rem;border-top:1px dashed var(--warm);}
    .tracking-body{display:none;margin-top:0.8rem;}
    .tracking-body.open{display:block;}
    .t-step{display:flex;gap:0.7rem;padding-bottom:0.6rem;align-items:flex-start;}
    .t-dot{width:10px;height:10px;border-radius:50%;background:var(--accent);flex-shrink:0;margin-top:3px;}
    .t-dot.green{background:#28a745;}
    .t-dot.purple{background:#6f42c1;}
    .t-info .t-loc{font-size:0.82rem;font-weight:700;color:var(--text);}
    .t-info .t-time{font-size:0.7rem;color:var(--muted);}

    .empty-state{text-align:center;padding:4rem;color:var(--muted);background:var(--white);border-radius:22px;border:1.5px dashed var(--warm);}

    @media(max-width:900px){.stats-row{grid-template-columns:repeat(4,1fr);}.info-grid{grid-template-columns:1fr 1fr;}}
    @media(max-width:768px){
      nav{height:auto;flex-wrap:wrap;padding:0.6rem 4vw;gap:0.5rem;}
      .logo{font-size:1.3rem;}.nav-right{gap:0.4rem;}.nav-link{padding:0.3rem 0.6rem;font-size:0.72rem;}
      .main{margin-top:110px;padding:1.5rem 4vw;}
      .stats-row{grid-template-columns:repeat(3,1fr);}
      .info-grid{grid-template-columns:1fr 1fr;}
      .card-body{padding:1rem;}
    }
    @media(max-width:500px){
      .stats-row{grid-template-columns:repeat(2,1fr);}
      .info-grid{grid-template-columns:1fr;}
      .assign-form{flex-direction:column;}
      .assign-select{width:100%;}
      .btn-assign{width:100%;}
    }
  </style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="logo">Trenzo<span>Kart</span> <span style="font-size:0.75rem;color:var(--accent2);font-weight:400;font-family:'DM Sans',sans-serif;">Admin</span></a>
  <div class="nav-right">
    <a href="dashboard.php" class="nav-link">Dashboard</a>
    <a href="vendors.php" class="nav-link">Vendors</a>
    <a href="returns.php" class="nav-link">↩ Returns</a>
    <a href="delivery_boys.php" class="nav-link">🚚 Delivery Boys</a>
    <a href="assistants.php" class="nav-link">Assistants</a>
    <a href="logout.php" class="nav-link">Logout</a>
  </div>
</nav>

<div class="main">
  <h1 class="page-title">🛒 All Orders</h1>
  <p class="page-sub">View orders, assign delivery boys and track shipments</p>

  <?php if (!empty($success)): ?><div class="msg">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

  <div class="stats-row">
    <?php
    $stat_items = ['all'=>['📋','All'],'pending'=>['⏳','Pending'],'confirmed'=>['✅','Confirmed'],'dispatched'=>['📦','Dispatched'],'shipped'=>['🚚','Shipped'],'delivered'=>['🎉','Delivered'],'cancelled'=>['❌','Cancelled']];
    foreach ($stat_items as $s => $info):
    ?>
    <a href="orders.php?status=<?= $s ?>" class="stat-card <?= $filter===$s?'active':'' ?>">
      <div style="font-size:1.3rem;"><?= $info[0] ?></div>
      <div class="stat-num"><?= $counts[$s]??0 ?></div>
      <div class="stat-label"><?= $info[1] ?></div>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="filter-row">
    <?php foreach ($stat_items as $s => $info): ?>
    <a href="orders.php?status=<?= $s ?>" class="filter-tab <?= $filter===$s?'active':'' ?>"><?= $info[0].' '.$info[1] ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($orders)): ?>
    <div class="empty-state"><span style="font-size:3.5rem;display:block;margin-bottom:1rem;">📭</span><p>No orders in this category!</p></div>
  <?php else: ?>
  <div class="orders-list">
    <?php foreach ($orders as $o):
      $cname = $o['full_name'] ?? $o['customer_name'];
      $track_result = mysqli_query($conn, "SELECT * FROM order_tracking WHERE order_id='{$o['id']}' ORDER BY created_at DESC LIMIT 5");
      $track_rows = [];
      while ($t = mysqli_fetch_assoc($track_result)) { $track_rows[] = $t; }
    ?>
    <div class="order-card">
      <div class="card-strip strip-<?= $o['status'] ?>"></div>
      <div class="card-body">
        <div class="card-top">
          <div>
            <div class="order-id">#TK<?= str_pad($o['id'],5,'0',STR_PAD_LEFT) ?></div>
            <div class="order-date">📅 <?= date('d M Y, h:i A', strtotime($o['created_at'])) ?></div>
          </div>
          <div class="badges">
            <?php if ($o['pickup_confirmed']): ?><span class="pickup-badge">📦 Picked Up</span><?php endif; ?>
            <span class="status-badge s-<?= $o['status'] ?>"><?= strtoupper($o['status']) ?></span>
          </div>
        </div>

        <div class="info-grid">
          <div class="info-item"><label>Customer</label><span><?= htmlspecialchars($cname) ?></span></div>
          <div class="info-item"><label>Vendor</label><span><?= htmlspecialchars($o['vendor_name']??'N/A') ?> — <?= htmlspecialchars($o['shop_name']??'') ?></span></div>
          <div class="info-item"><label>Amount</label><span style="font-family:'Playfair Display',serif;font-size:1rem;color:var(--brown);font-weight:700;">₹<?= number_format($o['total_amount'],2) ?> <span style="font-size:0.72rem;color:var(--muted);font-family:'DM Sans',sans-serif;">(<?= strtoupper($o['payment_method']??'COD') ?>)</span></span></div>
          <div class="info-item"><label>Products</label><span style="font-size:0.82rem;"><?= htmlspecialchars($o['product_names']??'N/A') ?></span></div>
          <div class="info-item"><label>Phone</label><span><?= htmlspecialchars($o['order_phone']??'N/A') ?></span></div>
          <div class="info-item"><label>Address</label><span style="font-size:0.8rem;"><?= htmlspecialchars($o['address']??'N/A') ?></span></div>
        </div>

        <!-- ASSIGN DELIVERY BOY -->
        <?php if ($o['status'] !== 'cancelled' && $o['status'] !== 'delivered'): ?>
        <div class="assign-section">
          <?php if ($o['delivery_boy_id'] && $o['delivery_boy_name']): ?>
            <div class="assign-title">🚚 Assigned Delivery Boy</div>
            <div class="assigned-box">
              <div>
                <div class="assigned-name">🚚 <?= htmlspecialchars($o['delivery_boy_name']) ?></div>
                <div class="assigned-detail"><?= htmlspecialchars($o['db_company']??'') ?> • 📞 <?= htmlspecialchars($o['db_phone']??'') ?></div>
              </div>
              <button class="btn-reassign" onclick="showAssign(<?= $o['id'] ?>)">🔄 Reassign</button>
            </div>
            <div id="assign-form-<?= $o['id'] ?>" style="display:none;margin-top:0.8rem;">
          <?php else: ?>
            <div class="assign-title">🚚 Assign Delivery Boy</div>
            <div id="assign-form-<?= $o['id'] ?>">
          <?php endif; ?>
              <form method="POST" class="assign-form">
                <input type="hidden" name="assign_db" value="1"/>
                <input type="hidden" name="order_id" value="<?= $o['id'] ?>"/>
                <select name="delivery_boy_id" class="assign-select" required>
                  <option value="">-- Select Delivery Boy --</option>
                  <?php foreach ($delivery_boys as $db): ?>
                    <option value="<?= $db['id'] ?>" <?= $o['delivery_boy_id']==$db['id']?'selected':'' ?>>
                      <?= htmlspecialchars($db['name']) ?> — <?= htmlspecialchars($db['company']??'') ?> (<?= htmlspecialchars($db['phone']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-assign">✅ Assign</button>
              </form>
            </div>
          <?php if ($o['delivery_boy_id']): ?></div><?php endif; ?>
        </div>
        <?php elseif ($o['delivery_boy_name']): ?>
        <div style="font-size:0.82rem;color:#155724;font-weight:600;padding:0.5rem 0;">
          🚚 Delivered by: <?= htmlspecialchars($o['delivery_boy_name']) ?> (<?= htmlspecialchars($o['db_company']??'') ?>)
        </div>
        <?php endif; ?>

        <!-- TRACKING -->
        <?php if (!empty($track_rows)): ?>
        <div class="tracking-toggle" onclick="toggleTracking(<?= $o['id'] ?>)">
          📍 Tracking History (<?= count($track_rows) ?> updates)
          <span id="track-icon-<?= $o['id'] ?>">▼</span>
        </div>
        <div class="tracking-body" id="track-body-<?= $o['id'] ?>">
          <?php foreach ($track_rows as $t): ?>
          <div class="t-step">
            <div class="t-dot <?= $t['status']==='Delivered'?'green':($t['status']==='Picked Up'?'purple':'') ?>"></div>
            <div class="t-info">
              <div class="t-loc"><?= htmlspecialchars($t['location']) ?> — <span style="color:var(--accent);font-size:0.75rem;"><?= htmlspecialchars($t['status']) ?></span></div>
              <div class="t-time">🕐 <?= date('d M Y, h:i A', strtotime($t['created_at'])) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<script>
function showAssign(id) {
  const form = document.getElementById('assign-form-' + id);
  form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
function toggleTracking(id) {
  const body = document.getElementById('track-body-' + id);
  const icon = document.getElementById('track-icon-' + id);
  body.classList.toggle('open');
  icon.textContent = body.classList.contains('open') ? '▲' : '▼';
}
</script>
</body>
</html>