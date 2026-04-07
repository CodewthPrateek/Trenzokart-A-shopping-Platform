<?php
require '../config.php';
if (!isset($_SESSION['vendor_id'])) { header("Location: login.php"); exit(); }

$vendor_id   = $_SESSION['vendor_id'];
$vendor_name = $_SESSION['vendor_name'];
$success = '';

// Mark pickup completed
if (isset($_GET['pickup']) && isset($_GET['id'])) {
    $rid = intval($_GET['id']);
    mysqli_query($conn, "UPDATE return_requests SET pickup_completed=1, pickup_at=NOW() WHERE id='$rid' AND order_id IN (SELECT id FROM orders WHERE vendor_id='$vendor_id')");
    $success = "Pickup marked as completed!";
}

$filter = $_GET['status'] ?? 'all';
$where  = "o.vendor_id='$vendor_id'";
if ($filter !== 'all') { $f = mysqli_real_escape_string($conn, $filter); $where .= " AND rr.status='$f'"; }

$result = mysqli_query($conn, "
    SELECT rr.*, o.total_amount, o.address, o.payment_method,
           u.name as customer_name, u.email as customer_email,
           o.full_name as order_full_name, o.phone as order_phone,
           GROUP_CONCAT(p.name SEPARATOR ', ') as product_names
    FROM return_requests rr
    JOIN orders o ON rr.order_id = o.id
    JOIN users u ON rr.user_id = u.id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE $where
    GROUP BY rr.id
    ORDER BY rr.created_at DESC
");
$returns = [];
while ($row = mysqli_fetch_assoc($result)) { $returns[] = $row; }

$counts = [
    'all'      => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM return_requests rr JOIN orders o ON rr.order_id=o.id WHERE o.vendor_id='$vendor_id'"))['c'],
    'pending'  => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM return_requests rr JOIN orders o ON rr.order_id=o.id WHERE o.vendor_id='$vendor_id' AND rr.status='pending'"))['c'],
    'approved' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM return_requests rr JOIN orders o ON rr.order_id=o.id WHERE o.vendor_id='$vendor_id' AND rr.status='approved'"))['c'],
    'rejected' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM return_requests rr JOIN orders o ON rr.order_id=o.id WHERE o.vendor_id='$vendor_id' AND rr.status='rejected'"))['c'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — Returns</title>
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

    /* STATS */
    .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;}
    .stat-card{background:var(--white);border-radius:16px;padding:1.2rem;text-align:center;box-shadow:0 2px 12px rgba(92,61,30,0.07);text-decoration:none;display:block;border:2px solid transparent;transition:all .2s;}
    .stat-card:hover,.stat-card.active{border-color:var(--accent);transform:translateY(-2px);}
    .stat-num{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:900;color:var(--brown);}
    .stat-label{font-size:0.75rem;color:var(--muted);font-weight:600;margin-top:0.2rem;}

    /* FILTER */
    .filter-row{display:flex;gap:0.5rem;margin-bottom:1.5rem;flex-wrap:wrap;}
    .filter-tab{padding:0.45rem 1rem;border-radius:50px;font-size:0.82rem;font-weight:600;cursor:pointer;border:1.5px solid var(--warm);background:var(--white);color:var(--muted);text-decoration:none;transition:all .2s;}
    .filter-tab.active,.filter-tab:hover{background:var(--accent);border-color:var(--accent);color:white;}

    /* RETURN CARDS */
    .return-card{background:var(--white);border-radius:20px;overflow:hidden;box-shadow:0 3px 18px rgba(92,61,30,0.07);border:1.5px solid var(--warm);margin-bottom:1.2rem;}
    .card-strip{height:4px;display:block;}
    .strip-pending{background:linear-gradient(90deg,#ffc107,#ffca2c);}
    .strip-approved{background:linear-gradient(90deg,#28a745,#48c774);}
    .strip-rejected{background:linear-gradient(90deg,#dc3545,#f06674);}

    .card-top{display:flex;justify-content:space-between;align-items:flex-start;padding:1.2rem 1.5rem 0.8rem;flex-wrap:wrap;gap:0.8rem;border-bottom:1px solid var(--warm);}
    .order-id{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;color:var(--brown);}
    .return-date{font-size:0.75rem;color:var(--muted);margin-top:0.2rem;}
    .badges{display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;}
    .status-badge{padding:0.25rem 0.8rem;border-radius:50px;font-size:0.75rem;font-weight:700;text-transform:uppercase;}
    .s-pending{background:#fff3e0;color:#e65100;}
    .s-approved{background:#d4edda;color:#155724;}
    .s-rejected{background:#f8d7da;color:#721c24;}
    .pickup-badge{background:#e8f5e9;color:#2e7d32;padding:0.25rem 0.7rem;border-radius:50px;font-size:0.72rem;font-weight:700;border:1px solid #a5d6a7;}

    .card-body{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;padding:1rem 1.5rem;}
    .info-item label{font-size:0.68rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.3px;display:block;margin-bottom:0.2rem;}
    .info-item span{font-size:0.88rem;color:var(--text);font-weight:500;line-height:1.4;}

    .reason-box{margin:0 1.5rem 1rem;background:#fff3e0;border:1.5px solid #ffcc80;border-radius:10px;padding:0.8rem 1rem;}
    .reason-label{font-size:0.68rem;font-weight:700;color:#e65100;text-transform:uppercase;margin-bottom:0.3rem;}
    .reason-text{font-size:0.88rem;color:var(--text);font-weight:600;}
    .reason-desc{font-size:0.78rem;color:var(--muted);margin-top:0.2rem;}

    .products-box{margin:0 1.5rem 1rem;background:var(--cream);border-radius:10px;padding:0.8rem 1rem;border:1px solid var(--warm);}
    .products-label{font-size:0.68rem;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:0.3rem;}
    .products-text{font-size:0.85rem;color:var(--text);font-weight:500;}

    .card-actions{padding:1rem 1.5rem;border-top:1px solid var(--warm);display:flex;align-items:center;gap:0.8rem;flex-wrap:wrap;background:var(--white);}
    .btn-pickup{padding:0.55rem 1.3rem;background:linear-gradient(135deg,#28a745,#1e7e34);color:white;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.85rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .2s;}
    .btn-pickup:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(40,167,69,0.4);}
    .btn-call{padding:0.55rem 1.1rem;background:#e8f5e9;color:#2e7d32;border:1.5px solid #a5d6a7;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.85rem;font-weight:700;text-decoration:none;transition:all .2s;}
    .btn-call:hover{background:#2e7d32;color:white;}
    .btn-wa{padding:0.55rem 1.1rem;background:#e8f5e9;color:#25D366;border:1.5px solid #a5d6a7;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.85rem;font-weight:700;text-decoration:none;transition:all .2s;}
    .btn-wa:hover{background:#25D366;color:white;}

    .refund-info{padding:0.7rem 1rem;background:#e8f5e9;border:1px solid #a5d6a7;border-radius:8px;font-size:0.82rem;color:#155724;font-weight:600;}
    .refund-processed{padding:0.7rem 1rem;background:#d4edda;border:1.5px solid #b8dfc4;border-radius:8px;font-size:0.82rem;color:#155724;font-weight:700;}

    .empty-state{text-align:center;padding:5rem 2rem;color:var(--muted);background:var(--white);border-radius:22px;border:1.5px dashed var(--warm);}
    .empty-state span{font-size:3.5rem;display:block;margin-bottom:1rem;}

    /* RESPONSIVE */
    @media(max-width:900px){.stats-row{grid-template-columns:repeat(2,1fr);}}
    @media(max-width:768px){
      nav{height:auto;flex-wrap:wrap;padding:0.6rem 4vw;gap:0.5rem;}
      .logo{font-size:1.3rem;}.nav-right{gap:0.4rem;}.nav-link{padding:0.3rem 0.6rem;font-size:0.72rem;}
      .main{margin-top:110px;padding:1rem 4vw;}
      .page-title{font-size:1.5rem;}
      .card-body{grid-template-columns:1fr 1fr;}
      .card-top{padding:1rem 1rem 0.8rem;}
      .card-body{padding:1rem;}
      .reason-box,.products-box{margin:0 1rem 1rem;}
      .card-actions{padding:0.8rem 1rem;gap:0.5rem;}
    }
    @media(max-width:500px){
      .stats-row{grid-template-columns:repeat(2,1fr);gap:0.7rem;}
      .card-body{grid-template-columns:1fr;}
      .btn-pickup,.btn-call,.btn-wa{padding:0.5rem 0.9rem;font-size:0.8rem;}
    }
  </style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="logo">Trenzo<span>Kart</span></a>
  <div class="nav-right">
    <a href="orders.php" class="nav-link">📦 Orders</a>
    <a href="products.php" class="nav-link">🏷️ Products</a>
    <a href="dashboard.php" class="nav-link">← Dashboard</a>
    <a href="logout.php" class="nav-link">Logout</a>
  </div>
</nav>

<div class="main">
  <h1 class="page-title">↩ Return Requests</h1>
  <p class="page-sub">Customer return requests dekho aur pickup schedule karo</p>

  <?php if (!empty($success)): ?>
    <div class="msg">✅ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <!-- STATS -->
  <div class="stats-row">
    <a href="returns.php?status=all"      class="stat-card <?= $filter==='all'?'active':'' ?>"><div class="stat-num"><?= $counts['all'] ?></div><div class="stat-label">📋 Total</div></a>
    <a href="returns.php?status=pending"  class="stat-card <?= $filter==='pending'?'active':'' ?>"><div class="stat-num" style="color:#e65100;"><?= $counts['pending'] ?></div><div class="stat-label">⏳ Pending</div></a>
    <a href="returns.php?status=approved" class="stat-card <?= $filter==='approved'?'active':'' ?>"><div class="stat-num" style="color:#28a745;"><?= $counts['approved'] ?></div><div class="stat-label">✅ Approved</div></a>
    <a href="returns.php?status=rejected" class="stat-card <?= $filter==='rejected'?'active':'' ?>"><div class="stat-num" style="color:#dc3545;"><?= $counts['rejected'] ?></div><div class="stat-label">❌ Rejected</div></a>
  </div>

  <!-- FILTER -->
  <div class="filter-row">
    <a href="returns.php?status=all"      class="filter-tab <?= $filter==='all'?'active':'' ?>">📋 All <span style="background:rgba(0,0,0,0.1);border-radius:50px;padding:0.05rem 0.4rem;font-size:0.72rem;"><?= $counts['all'] ?></span></a>
    <a href="returns.php?status=pending"  class="filter-tab <?= $filter==='pending'?'active':'' ?>">⏳ Pending</a>
    <a href="returns.php?status=approved" class="filter-tab <?= $filter==='approved'?'active':'' ?>">✅ Approved</a>
    <a href="returns.php?status=rejected" class="filter-tab <?= $filter==='rejected'?'active':'' ?>">❌ Rejected</a>
  </div>

  <?php if (empty($returns)): ?>
    <div class="empty-state"><span>↩</span><p>Koi return request nahi!</p></div>
  <?php else: ?>
    <?php foreach ($returns as $r):
      $cname  = $r['order_full_name'] ?? $r['customer_name'];
      $cphone = preg_replace('/\D/', '', $r['order_phone'] ?? '');
      $wa_text = urlencode("Hi {$cname}! TrenzoKart se aapke return request #TK".str_pad($r['order_id'],5,'0',STR_PAD_LEFT)." ke baare mein baat karni thi.");
    ?>
    <div class="return-card">
      <div class="card-strip strip-<?= $r['status'] ?>"></div>

      <div class="card-top">
        <div>
          <div class="order-id">#TK<?= str_pad($r['order_id'],5,'0',STR_PAD_LEFT) ?></div>
          <div class="return-date">📅 <?= date('d M Y, h:i A', strtotime($r['created_at'])) ?></div>
        </div>
        <div class="badges">
          <?php if ($r['pickup_completed']): ?>
            <span class="pickup-badge">📦 Pickup Done</span>
          <?php endif; ?>
          <?php if (!empty($r['refund_method'])): ?>
            <span class="pickup-badge" style="color:#2b6cb0;border-color:#90cdf4;background:#ebf8ff;">💳 Refund Details Submitted</span>
          <?php endif; ?>
          <span class="status-badge s-<?= $r['status'] ?>"><?= strtoupper($r['status']) ?></span>
        </div>
      </div>

      <div class="card-body">
        <div class="info-item"><label>Customer</label><span><?= htmlspecialchars($cname) ?></span></div>
        <div class="info-item"><label>Phone</label><span><?= htmlspecialchars($cphone ?: 'N/A') ?></span></div>
        <div class="info-item"><label>Amount</label><span style="font-family:'Playfair Display',serif;font-size:1rem;color:var(--brown);font-weight:700;">₹<?= number_format($r['total_amount'],2) ?></span></div>
        <div class="info-item"><label>Payment</label><span><?= strtoupper($r['payment_method']??'COD') ?></span></div>
        <div class="info-item" style="grid-column:span 2"><label>Delivery Address</label><span><?= htmlspecialchars($r['address']??'N/A') ?></span></div>
      </div>

      <!-- Products -->
      <?php if (!empty($r['product_names'])): ?>
      <div class="products-box">
        <div class="products-label">📦 Products in Order</div>
        <div class="products-text"><?= htmlspecialchars($r['product_names']) ?></div>
      </div>
      <?php endif; ?>

      <!-- Reason -->
      <div class="reason-box">
        <div class="reason-label">Return Reason</div>
        <div class="reason-text"><?= htmlspecialchars($r['reason']) ?></div>
        <?php if (!empty($r['description'])): ?>
          <div class="reason-desc"><?= htmlspecialchars($r['description']) ?></div>
        <?php endif; ?>
      </div>

      <!-- Refund info if submitted -->
      <?php if (!empty($r['refund_method'])): ?>
      <div style="margin: 0 1.5rem 1rem;">
        <div class="refund-info">
          💳 Refund Details:
          <?php if ($r['refund_method']==='upi'): ?>
            📱 UPI — <?= htmlspecialchars($r['refund_upi'] ?? '') ?>
          <?php else: ?>
            🏦 Bank — <?= htmlspecialchars($r['refund_bank_name']??'') ?> | A/C: <?= htmlspecialchars($r['refund_account_no']??'') ?>
          <?php endif; ?>
          <?php if ($r['refund_status']==='processed'): ?>
            <br><strong>✅ Refund Processed on <?= date('d M Y', strtotime($r['refund_at'])) ?></strong>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- ACTIONS -->
      <div class="card-actions">
        <?php if ($r['status'] === 'approved' && !$r['pickup_completed']): ?>
          <a href="returns.php?pickup=1&id=<?= $r['id'] ?>" class="btn-pickup" onclick="return confirm('Mark pickup as completed?')">📦 Mark Pickup Done</a>
        <?php elseif ($r['status'] === 'approved' && $r['pickup_completed']): ?>
          <span style="font-size:0.85rem;color:#155724;font-weight:600;">✅ Pickup Completed on <?= date('d M Y', strtotime($r['pickup_at'])) ?></span>
        <?php elseif ($r['status'] === 'pending'): ?>
          <span style="font-size:0.82rem;color:#e65100;font-weight:600;">⏳ Pending approval by admin/assistant</span>
        <?php else: ?>
          <span style="font-size:0.82rem;color:#721c24;font-weight:600;">❌ Return Rejected</span>
        <?php endif; ?>

        <?php if ($cphone): ?>
          <a href="tel:<?= $cphone ?>" class="btn-call">📞 Call</a>
          <a href="https://wa.me/91<?= $cphone ?>?text=<?= $wa_text ?>" target="_blank" class="btn-wa">💬 WhatsApp</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
</body>
</html>