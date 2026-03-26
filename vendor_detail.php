<?php
require '../config.php';
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }

$vendor_id = intval($_GET['id'] ?? 0);
if (!$vendor_id) { header("Location: summary.php"); exit(); }

$success = ''; $error = '';

// Update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $oid    = intval($_POST['order_id']);
    $action = $_POST['action'];
    $valid  = ['confirmed','dispatched','shipped','delivered','cancelled'];
    if (in_array($action, $valid)) {
        $time_col = match($action) {
            'confirmed'  => ", confirmed_at=NOW()",
            'dispatched' => ", dispatched_at=NOW()",
            'shipped'    => ", shipped_at=NOW()",
            'delivered'  => ", delivered_at=NOW()",
            default      => ""
        };
        mysqli_query($conn, "UPDATE orders SET status='$action' $time_col WHERE id='$oid' AND vendor_id='$vendor_id'");
        $success = "Order #$oid updated to " . strtoupper($action) . "!";
    }
}

// Fetch vendor info
$vendor = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM vendors WHERE id='$vendor_id'"));
if (!$vendor) { header("Location: summary.php"); exit(); }

// Filter
$filter = $_GET['status'] ?? 'all';
$where  = "o.vendor_id='$vendor_id'";
if ($filter !== 'all') { $f = mysqli_real_escape_string($conn, $filter); $where .= " AND o.status='$f'"; }

// Fetch orders
$orders_result = mysqli_query($conn, "
    SELECT o.*, u.name as customer_name, u.email as customer_email,
           COALESCE(o.phone, u.phone) as phone,
           GROUP_CONCAT(p.name SEPARATOR ', ') as product_name,
           SUM(oi.quantity) as total_qty
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE $where GROUP BY o.id ORDER BY o.id DESC
");
$orders = [];
while ($row = mysqli_fetch_assoc($orders_result)) { $orders[] = $row; }

// Counts
$counts = [];
foreach (['pending','confirmed','dispatched','shipped','delivered','cancelled'] as $s) {
    $counts[$s] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE vendor_id='$vendor_id' AND status='$s'"))['c'];
}
$counts['all'] = array_sum($counts);

// Return requests
$returns = mysqli_query($conn, "
    SELECT rr.*, u.name as customer_name, o.total_amount, o.full_name as order_name
    FROM return_requests rr
    JOIN orders o ON rr.order_id = o.id
    JOIN users u ON rr.user_id = u.id
    WHERE o.vendor_id='$vendor_id' AND rr.status='pending'
    ORDER BY rr.created_at DESC
");
$return_rows = [];
while ($row = mysqli_fetch_assoc($returns)) { $return_rows[] = $row; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — <?= htmlspecialchars($vendor['name']) ?> Orders</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{--cream:#f5efe6;--warm:#e8d5b7;--brown:#5c3d1e;--accent:#d4622a;--accent2:#e8a045;--text:#2d1a0a;--muted:#8a6a4a;--white:#fffdf8;--dark:#1a0f02;}
    body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--text);}
    nav{position:fixed;top:0;left:0;right:0;z-index:100;background:var(--dark);display:flex;align-items:center;justify-content:space-between;padding:0 5vw;height:65px;box-shadow:0 4px 20px rgba(0,0,0,0.3);}
    .logo{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:900;color:var(--white);text-decoration:none;}
    .logo span{color:var(--accent2);}
    .nav-right{display:flex;gap:0.8rem;}
    .nav-link{padding:0.45rem 1.1rem;border:1.5px solid rgba(255,255,255,0.25);border-radius:50px;color:rgba(255,255,255,0.7);font-size:0.82rem;font-weight:600;text-decoration:none;transition:all .2s;}
    .nav-link:hover{border-color:var(--accent2);color:var(--accent2);}
    .main{margin-top:85px;padding:2.5rem 5vw;}
    .msg{padding:0.85rem 1.2rem;border-radius:12px;font-size:0.88rem;margin-bottom:1.5rem;font-weight:500;background:#d4edda;color:#155724;border:1.5px solid #b8dfc4;}

    /* VENDOR HEADER */
    .vendor-hero{background:linear-gradient(135deg,var(--brown),#1a0f02);border-radius:20px;padding:1.5rem 2rem;display:flex;align-items:center;gap:1.2rem;margin-bottom:2rem;color:white;}
    .v-avatar{width:56px;height:56px;border-radius:50%;background:var(--accent);color:white;font-size:1.5rem;font-weight:900;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .v-name{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:900;}
    .v-shop{color:rgba(255,255,255,0.6);font-size:0.88rem;margin-top:0.2rem;}

    /* STATS */
    .stats-row{display:grid;grid-template-columns:repeat(7,1fr);gap:0.8rem;margin-bottom:1.5rem;}
    @media(max-width:900px){.stats-row{grid-template-columns:repeat(4,1fr);}}
    .stat-card{background:var(--white);border-radius:14px;padding:0.8rem;text-align:center;cursor:pointer;border:2px solid transparent;transition:all .2s;text-decoration:none;display:block;}
    .stat-card:hover,.stat-card.active{border-color:var(--accent);transform:translateY(-2px);}
    .stat-num{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:900;color:var(--brown);}
    .stat-label{font-size:0.68rem;color:var(--muted);font-weight:600;text-transform:uppercase;}

    /* RETURN ALERT */
    .return-alert{background:#ffebee;border:1.5px solid #f5b8bc;border-radius:14px;padding:1rem 1.5rem;margin-bottom:1.5rem;}
    .return-alert-title{font-weight:700;color:#c62828;font-size:0.95rem;margin-bottom:0.8rem;}
    .return-item{display:flex;align-items:center;justify-content:space-between;padding:0.6rem 0;border-bottom:1px solid #f5c6cb;flex-wrap:wrap;gap:0.5rem;}
    .return-item:last-child{border-bottom:none;}
    .return-info{font-size:0.85rem;color:var(--text);}
    .return-reason{font-size:0.78rem;color:#c62828;font-weight:600;}
    .return-actions{display:flex;gap:0.5rem;}
    .btn-approve{padding:0.3rem 0.8rem;background:#d4edda;color:#155724;border:none;border-radius:8px;font-size:0.78rem;font-weight:700;cursor:pointer;text-decoration:none;}
    .btn-approve:hover{background:#155724;color:white;}
    .btn-reject{padding:0.3rem 0.8rem;background:#f8d7da;color:#721c24;border:none;border-radius:8px;font-size:0.78rem;font-weight:700;cursor:pointer;text-decoration:none;}
    .btn-reject:hover{background:#721c24;color:white;}

    /* ORDER CARDS */
    .orders-list{display:flex;flex-direction:column;gap:1rem;}
    .order-card{background:var(--white);border-radius:16px;padding:1.2rem 1.5rem;box-shadow:0 2px 16px rgba(92,61,30,0.07);border:1.5px solid var(--warm);}
    .order-top{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.8rem;margin-bottom:0.8rem;}
    .order-id{font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;color:var(--brown);}
    .order-date{font-size:0.78rem;color:var(--muted);}
    .status-badge{padding:0.25rem 0.8rem;border-radius:50px;font-size:0.75rem;font-weight:700;}
    .s-pending{background:#fff3cd;color:#856404;}
    .s-confirmed{background:#d1ecf1;color:#0c5460;}
    .s-dispatched{background:#fff0d6;color:#854d00;}
    .s-shipped{background:#e9d8fd;color:#44337a;}
    .s-delivered{background:#d4edda;color:#155724;}
    .s-cancelled{background:#f8d7da;color:#721c24;}
    .order-body{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:0.8rem;padding:0.8rem;background:var(--cream);border-radius:10px;}
    .ob-item label{font-size:0.68rem;font-weight:700;color:var(--muted);text-transform:uppercase;display:block;margin-bottom:0.2rem;}
    .ob-item span{font-size:0.88rem;color:var(--text);font-weight:500;}
    .order-actions{display:flex;gap:0.6rem;flex-wrap:wrap;}
    .btn-action{padding:0.45rem 1rem;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:0.82rem;font-weight:700;cursor:pointer;border:none;transition:all .2s;}
    .btn-confirm{background:linear-gradient(135deg,#17a2b8,#0d7a8c);color:white;}
    .btn-dispatch{background:linear-gradient(135deg,var(--accent2),#d4870a);color:white;}
    .btn-ship{background:linear-gradient(135deg,#6f42c1,#5731a3);color:white;}
    .btn-deliver{background:linear-gradient(135deg,#28a745,#1e7e34);color:white;}
    .btn-cancel{background:linear-gradient(135deg,#dc3545,#b02a37);color:white;}
    .btn-action:hover{transform:translateY(-1px);opacity:0.9;}
    .empty-state{text-align:center;padding:3rem;color:var(--muted);background:var(--white);border-radius:16px;border:1.5px dashed var(--warm);}
    @media(max-width:600px){.order-body{grid-template-columns:1fr;} .stats-row{grid-template-columns:repeat(3,1fr);} nav{height:auto;flex-wrap:wrap;padding:0.6rem 4vw;gap:0.5rem;} .logo{font-size:1.2rem;} .nav-links{gap:0.4rem;flex-wrap:wrap;} .nav-link{padding:0.3rem 0.6rem;font-size:0.72rem;} .main{margin-top:100px !important;padding:1rem 3vw;} }
  </style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="logo">Trenzo<span>Kart</span></a>
  <div class="nav-right">
    <a href="summary.php" class="nav-link">← Summary</a>
    <a href="returns.php" class="nav-link">↩ Returns</a>
    <a href="dashboard.php" class="nav-link">Dashboard</a>
    <a href="logout.php" class="nav-link">Logout</a>
  </div>
</nav>

<div class="main">
  <?php if (!empty($success)): ?><div class="msg">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

  <!-- VENDOR HERO -->
  <div class="vendor-hero">
    <div class="v-avatar"><?= strtoupper(substr($vendor['name'], 0, 1)) ?></div>
    <div>
      <div class="v-name"><?= htmlspecialchars($vendor['name']) ?></div>
      <div class="v-shop">🏪 <?= htmlspecialchars($vendor['shop_name']) ?> • ✉️ <?= htmlspecialchars($vendor['email']) ?></div>
    </div>
  </div>

  <!-- STATS -->
  <div class="stats-row">
    <?php
    $tabs = ['all'=>['📋','All'],'pending'=>['⏳','Pending'],'confirmed'=>['✅','Confirmed'],'dispatched'=>['📦','Dispatch'],'shipped'=>['🚚','Shipped'],'delivered'=>['🎉','Delivered'],'cancelled'=>['❌','Cancelled']];
    foreach ($tabs as $s => $info):
    ?>
    <a href="vendor_detail.php?id=<?= $vendor_id ?>&status=<?= $s ?>" class="stat-card <?= $filter===$s?'active':'' ?>">
      <div class="stat-num"><?= $counts[$s]??0 ?></div>
      <div class="stat-label"><?= $info[0] ?> <?= $info[1] ?></div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- PENDING RETURNS ALERT -->
  <?php if (!empty($return_rows)): ?>
  <div class="return-alert">
    <div class="return-alert-title">⚠️ <?= count($return_rows) ?> Pending Return Request(s)</div>
    <?php foreach ($return_rows as $r): ?>
    <div class="return-item">
      <div>
        <div class="return-info">#TK<?= str_pad($r['order_id'],5,'0',STR_PAD_LEFT) ?> — <?= htmlspecialchars($r['order_name'] ?? $r['customer_name']) ?> — ₹<?= number_format($r['total_amount'],2) ?></div>
        <div class="return-reason">↩ <?= htmlspecialchars($r['reason']) ?></div>
      </div>
      <div class="return-actions">
        <a href="vendor_detail.php?id=<?= $vendor_id ?>&ret_action=approve&ret_id=<?= $r['id'] ?>" class="btn-approve" onclick="return confirm('Approve return?')">✅ Approve</a>
        <a href="vendor_detail.php?id=<?= $vendor_id ?>&ret_action=reject&ret_id=<?= $r['id'] ?>" class="btn-reject" onclick="return confirm('Reject return?')">❌ Reject</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ORDERS LIST -->
  <?php if (empty($orders)): ?>
    <div class="empty-state"><span style="font-size:3rem;display:block;margin-bottom:1rem;">📭</span><p>Koi order nahi is category mein!</p></div>
  <?php else: ?>
  <div class="orders-list">
    <?php foreach ($orders as $o): 
      $status = $o['status'];
      // Check if this order has a return request
      $ret = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM return_requests WHERE order_id='{$o['id']}' LIMIT 1"));
    ?>
    <div class="order-card">
      <div class="order-top">
        <div>
          <span class="order-id">#TK<?= str_pad($o['id'],5,'0',STR_PAD_LEFT) ?></span>
          <span class="order-date" style="margin-left:0.5rem;">📅 <?= date('d M Y', strtotime($o['created_at'])) ?></span>
        </div>
        <div style="display:flex;gap:0.5rem;align-items:center;">
          <?php if ($ret): ?>
            <span style="background:<?= $ret['status']==='pending'?'#fff3e0':($ret['status']==='approved'?'#d4edda':'#f8d7da') ?>;color:<?= $ret['status']==='pending'?'#e65100':($ret['status']==='approved'?'#155724':'#721c24') ?>;padding:0.2rem 0.7rem;border-radius:50px;font-size:0.72rem;font-weight:700;">↩ Return <?= strtoupper($ret['status']) ?></span>
          <?php endif; ?>
          <span class="status-badge s-<?= $status ?>"><?= strtoupper($status) ?></span>
        </div>
      </div>
      <div class="order-body">
        <div class="ob-item"><label>Customer</label><span><?= htmlspecialchars($o['full_name'] ?? $o['customer_name']) ?></span></div>
        <div class="ob-item"><label>Product</label><span><?= htmlspecialchars($o['product_name'] ?? 'Product') ?></span></div>
        <div class="ob-item"><label>Amount</label><span style="font-family:'Playfair Display',serif;font-size:1rem;font-weight:900;color:var(--brown);">₹<?= number_format($o['total_amount'],2) ?></span></div>
      </div>

      <!-- RETURN CARD -->
      <?php if ($ret): ?>
      <div style="background:<?= $ret['pickup_completed'] ? '#d4edda' : '#fff3e0' ?>;border:1.5px solid <?= $ret['pickup_completed'] ? '#b8dfc4' : '#ffcc80' ?>;border-radius:12px;padding:1rem;margin-bottom:0.8rem;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.5rem;">
          <div>
            <div style="font-size:0.78rem;font-weight:700;color:#e65100;text-transform:uppercase;margin-bottom:0.3rem;">↩ Return Request</div>
            <div style="font-size:0.88rem;font-weight:600;color:var(--text);"><?= htmlspecialchars($ret['reason']) ?></div>
            <?php if (!empty($ret['description'])): ?>
              <div style="font-size:0.78rem;color:var(--muted);margin-top:0.2rem;"><?= htmlspecialchars($ret['description']) ?></div>
            <?php endif; ?>
            <div style="font-size:0.72rem;color:var(--muted);margin-top:0.3rem;">Requested: <?= date('d M Y, h:i A', strtotime($ret['created_at'])) ?></div>
          </div>
          <div style="display:flex;flex-direction:column;gap:0.4rem;align-items:flex-end;">
            <?php if ($ret['status'] === 'pending'): ?>
              <a href="vendor_detail.php?id=<?= $vendor_id ?>&ret_action=approve&ret_id=<?= $ret['id'] ?>" class="btn-approve" onclick="return confirm('Approve return?')">✅ Approve</a>
              <a href="vendor_detail.php?id=<?= $vendor_id ?>&ret_action=reject&ret_id=<?= $ret['id'] ?>" class="btn-reject" onclick="return confirm('Reject return?')">❌ Reject</a>
            <?php elseif ($ret['status'] === 'approved' && !$ret['pickup_completed']): ?>
              <a href="vendor_detail.php?id=<?= $vendor_id ?>&pickup=<?= $ret['id'] ?>" class="btn-action btn-deliver" style="font-size:0.82rem;padding:0.45rem 1rem;text-decoration:none;" onclick="return confirm('Mark pickup completed?')">📦 Return Pickup Done</a>
            <?php elseif ($ret['pickup_completed']): ?>
              <span style="background:#d4edda;color:#155724;padding:0.3rem 0.8rem;border-radius:8px;font-size:0.78rem;font-weight:700;">✅ Pickup Completed<br><span style="font-size:0.68rem;font-weight:400;"><?= date('d M Y', strtotime($ret['pickup_at'])) ?></span></span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!in_array($status, ['delivered','cancelled'])): ?>
      <div class="order-actions">
        <form method="POST" style="display:contents;">
          <input type="hidden" name="order_id" value="<?= $o['id'] ?>"/>
          <?php if ($status === 'pending'): ?>
            <button type="submit" name="action" value="confirmed" class="btn-action btn-confirm" onclick="return confirm('Confirm order?')">✅ Confirm</button>
            <button type="submit" name="action" value="cancelled" class="btn-action btn-cancel" onclick="return confirm('Cancel order?')">❌ Cancel</button>
          <?php elseif ($status === 'confirmed'): ?>
            <button type="submit" name="action" value="dispatched" class="btn-action btn-dispatch" onclick="return confirm('Mark dispatched?')">📦 Dispatch</button>
            <button type="submit" name="action" value="cancelled" class="btn-action btn-cancel" onclick="return confirm('Cancel order?')">❌ Cancel</button>
          <?php elseif ($status === 'dispatched'): ?>
            <button type="submit" name="action" value="shipped" class="btn-action btn-ship" onclick="return confirm('Mark shipped?')">🚚 Ship</button>
          <?php elseif ($status === 'shipped'): ?>
            <button type="submit" name="action" value="delivered" class="btn-action btn-deliver" onclick="return confirm('Mark delivered?')">🎉 Delivered</button>
          <?php endif; ?>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php
// Handle return actions
if (isset($_GET['ret_action']) && isset($_GET['ret_id'])) {
    $rid = intval($_GET['ret_id']);
    $ract = $_GET['ret_action'] === 'approve' ? 'approved' : 'rejected';
    mysqli_query($conn, "UPDATE return_requests SET status='$ract' WHERE id='$rid'");
    header("Location: vendor_detail.php?id=$vendor_id");
    exit();
}

// Handle pickup completed
if (isset($_GET['pickup'])) {
    $rid = intval($_GET['pickup']);
    mysqli_query($conn, "UPDATE return_requests SET pickup_completed=1, pickup_at=NOW() WHERE id='$rid'");
    header("Location: vendor_detail.php?id=$vendor_id");
    exit();
}
?>
</body>
</html>