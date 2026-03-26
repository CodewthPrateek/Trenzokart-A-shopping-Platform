<?php
require '../config.php';
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
$success = '';

// Approve/Reject
if (isset($_GET['action']) && isset($_GET['id'])) {
    $rid    = intval($_GET['id']);
    $action = $_GET['action'] === 'approve' ? 'approved' : 'rejected';
    mysqli_query($conn, "UPDATE return_requests SET status='$action' WHERE id='$rid'");
    $success = "Return " . ($action === 'approved' ? 'approved!' : 'rejected!');
}

// Mark Refund Processed
if (isset($_GET['refund']) && isset($_GET['id'])) {
    $rid = intval($_GET['id']);
    mysqli_query($conn, "UPDATE return_requests SET refund_status='processed', refund_at=NOW() WHERE id='$rid'");
    $success = "Refund marked as processed!";
}

$filter = $_GET['status'] ?? 'all';
$where  = "1=1";
if ($filter === 'refund_pending') {
    $where = "rr.status='approved' AND rr.pickup_completed=1 AND rr.refund_method IS NOT NULL AND (rr.refund_status IS NULL OR rr.refund_status != 'processed')";
} elseif ($filter === 'refund_done') {
    $where = "rr.refund_status='processed'";
} elseif ($filter !== 'all') {
    $where = "rr.status='" . mysqli_real_escape_string($conn, $filter) . "'";
}

$result = mysqli_query($conn, "
    SELECT rr.*, o.total_amount, o.address, o.vendor_id, o.payment_method,
           u.name as customer_name, u.email as customer_email,
           o.full_name as order_name, o.phone as order_phone,
           v.name as vendor_name, v.shop_name
    FROM return_requests rr
    JOIN orders o ON rr.order_id = o.id
    JOIN users u ON rr.user_id = u.id
    LEFT JOIN vendors v ON o.vendor_id = v.id
    WHERE $where ORDER BY rr.created_at DESC
");
$returns = [];
while ($row = mysqli_fetch_assoc($result)) { $returns[] = $row; }

$counts = [
    'all'            => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM return_requests"))['c'],
    'pending'        => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM return_requests WHERE status='pending'"))['c'],
    'approved'       => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM return_requests WHERE status='approved'"))['c'],
    'rejected'       => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM return_requests WHERE status='rejected'"))['c'],
    'refund_pending' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM return_requests WHERE status='approved' AND pickup_completed=1 AND refund_method IS NOT NULL AND (refund_status IS NULL OR refund_status != 'processed')"))['c'],
    'refund_done'    => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM return_requests WHERE refund_status='processed'"))['c'],
];

// Total pending refund amount
$refund_amount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(o.total_amount) as t FROM return_requests rr JOIN orders o ON rr.order_id=o.id WHERE rr.status='approved' AND rr.pickup_completed=1 AND rr.refund_method IS NOT NULL AND (rr.refund_status IS NULL OR rr.refund_status != 'processed')"))['t'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart Admin — Returns</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{--cream:#f5efe6;--warm:#e8d5b7;--brown:#5c3d1e;--accent:#d4622a;--accent2:#e8a045;--text:#2d1a0a;--muted:#8a6a4a;--white:#fffdf8;--dark:#1a0f02;}
    body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--text);}
    nav{position:fixed;top:0;left:0;right:0;z-index:100;background:var(--dark);display:flex;align-items:center;justify-content:space-between;padding:0 5vw;height:65px;box-shadow:0 4px 20px rgba(0,0,0,0.3);}
    .logo{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:900;color:var(--white);text-decoration:none;}
    .logo span{color:var(--accent2);}
    .nav-right{display:flex;gap:0.8rem;flex-wrap:wrap;}
    .nav-link{padding:0.45rem 1.1rem;border:1.5px solid rgba(255,255,255,0.25);border-radius:50px;color:rgba(255,255,255,0.7);font-size:0.82rem;font-weight:600;text-decoration:none;transition:all .2s;}
    .nav-link:hover{border-color:var(--accent2);color:var(--accent2);}
    .main{margin-top:85px;padding:2.5rem 5vw;}
    .page-title{font-family:'Playfair Display',serif;font-size:2rem;color:var(--brown);margin-bottom:0.3rem;}
    .page-sub{color:var(--muted);font-size:0.9rem;margin-bottom:2rem;}
    .msg{padding:0.85rem 1.2rem;border-radius:12px;font-size:0.88rem;margin-bottom:1.5rem;font-weight:500;background:#d4edda;color:#155724;border:1.5px solid #b8dfc4;}

    /* STATS */
    .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1rem;}
    .stat-card{background:var(--white);border-radius:16px;padding:1.2rem;text-align:center;box-shadow:0 2px 12px rgba(92,61,30,0.07);text-decoration:none;display:block;border:2px solid transparent;transition:all .2s;}
    .stat-card:hover,.stat-card.active{border-color:var(--accent);transform:translateY(-2px);box-shadow:0 6px 20px rgba(92,61,30,0.1);}
    .stat-num{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:900;color:var(--brown);}
    .stat-label{font-size:0.75rem;color:var(--muted);font-weight:600;margin-top:0.2rem;}

    /* REFUND CARDS ROW */
    .refund-cards-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:2rem;}
    .refund-card{border-radius:16px;padding:1.3rem 1.5rem;text-decoration:none;display:block;border:2px solid transparent;transition:all .2s;position:relative;overflow:hidden;}
    .refund-card:hover,.refund-card.active{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,0.12);}
    .refund-card.pending-card{background:linear-gradient(135deg,#fff3cd,#ffe69c);border-color:#ffc107;}
    .refund-card.pending-card:hover,.refund-card.pending-card.active{border-color:#e6a800;box-shadow:0 8px 24px rgba(255,193,7,0.3);}
    .refund-card.done-card{background:linear-gradient(135deg,#d4edda,#b8dfc4);border-color:#28a745;}
    .refund-card.done-card:hover,.refund-card.done-card.active{border-color:#1e7e34;box-shadow:0 8px 24px rgba(40,167,69,0.25);}
    .refund-card-icon{font-size:2rem;margin-bottom:0.5rem;}
    .refund-card-num{font-family:'Playfair Display',serif;font-size:2rem;font-weight:900;margin-bottom:0.2rem;}
    .pending-card .refund-card-num{color:#856404;}
    .done-card .refund-card-num{color:#155724;}
    .refund-card-label{font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.3px;}
    .pending-card .refund-card-label{color:#a07000;}
    .done-card .refund-card-label{color:#276749;}
    .refund-card-amount{font-size:0.82rem;font-weight:600;margin-top:0.4rem;}
    .pending-card .refund-card-amount{color:#856404;}
    .done-card .refund-card-amount{color:#276749;}
    .refund-card-badge{position:absolute;top:0.8rem;right:0.8rem;width:10px;height:10px;border-radius:50%;animation:pulse 1.5s infinite;}
    .pending-card .refund-card-badge{background:#ffc107;}
    @keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:0.5;transform:scale(1.3)}}

    .filter-row{display:flex;gap:0.5rem;margin-bottom:1.5rem;flex-wrap:wrap;}
    .filter-tab{padding:0.45rem 1.1rem;border-radius:50px;font-size:0.82rem;font-weight:600;cursor:pointer;border:1.5px solid var(--warm);background:var(--white);color:var(--muted);text-decoration:none;transition:all .2s;}
    .filter-tab.active,.filter-tab:hover{background:var(--accent);border-color:var(--accent);color:white;}
    .filter-tab.refund-tab{border-color:#ffc107;color:#856404;background:#fff9e6;}
    .filter-tab.refund-tab.active,.filter-tab.refund-tab:hover{background:#ffc107;border-color:#ffc107;color:#1a0f02;}

    /* RETURN CARD */
    .return-card{background:var(--white);border-radius:20px;overflow:hidden;margin-bottom:1.2rem;box-shadow:0 3px 18px rgba(92,61,30,0.07);border:1.5px solid var(--warm);}
    .card-strip{height:4px;}
    .strip-pending{background:linear-gradient(90deg,#ffc107,#ffca2c);}
    .strip-approved{background:linear-gradient(90deg,#28a745,#48c774);}
    .strip-rejected{background:linear-gradient(90deg,#dc3545,#f06674);}
    .strip-refund{background:linear-gradient(90deg,#6f42c1,#9b6ee8);}
    .card-pad{padding:1.5rem;}
    .return-top{display:flex;justify-content:space-between;flex-wrap:wrap;gap:0.8rem;margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid var(--warm);}
    .return-order-id{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;color:var(--brown);}
    .return-date{font-size:0.78rem;color:var(--muted);margin-top:0.2rem;}
    .badges{display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;}
    .status-badge{padding:0.3rem 0.9rem;border-radius:50px;font-size:0.75rem;font-weight:700;}
    .s-pending{background:#fff3e0;color:#e65100;}
    .s-approved{background:#d4edda;color:#155724;}
    .s-rejected{background:#f8d7da;color:#721c24;}
    .pickup-badge{background:#e8f5e9;color:#2e7d32;padding:0.25rem 0.7rem;border-radius:50px;font-size:0.72rem;font-weight:700;border:1px solid #a5d6a7;}
    .refund-badge{background:#ebf8ff;color:#2b6cb0;padding:0.25rem 0.7rem;border-radius:50px;font-size:0.72rem;font-weight:700;border:1px solid #90cdf4;}
    .return-body{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1rem;}
    .info-item label{font-size:0.7rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.3px;display:block;margin-bottom:0.2rem;}
    .info-item span{font-size:0.88rem;color:var(--text);font-weight:500;}
    .reason-box{background:#fff3e0;border:1.5px solid #ffcc80;border-radius:10px;padding:0.8rem 1rem;margin-bottom:1rem;}
    .reason-label{font-size:0.7rem;font-weight:700;color:#e65100;text-transform:uppercase;margin-bottom:0.3rem;}
    .reason-text{font-size:0.88rem;color:var(--text);font-weight:600;}
    .refund-section{background:#f0fff4;border:1.5px solid #9ae6b4;border-radius:12px;padding:1rem 1.2rem;margin-bottom:1rem;}
    .refund-section-title{font-size:0.8rem;font-weight:700;color:#276749;text-transform:uppercase;letter-spacing:0.3px;margin-bottom:0.8rem;}
    .refund-detail-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:0.6rem 1.5rem;margin-bottom:0.8rem;}
    .refund-detail-item label{font-size:0.68rem;font-weight:700;color:#276749;text-transform:uppercase;display:block;margin-bottom:0.15rem;}
    .refund-detail-item span{font-size:0.9rem;color:var(--text);font-weight:600;}
    .btn-process-refund{padding:0.6rem 1.5rem;background:linear-gradient(135deg,#276749,#1e5035);color:white;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.88rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .2s;display:inline-flex;align-items:center;gap:0.4rem;}
    .btn-process-refund:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(39,103,73,0.4);}
    .refund-processed-box{background:#d4edda;border:1.5px solid #b8dfc4;border-radius:10px;padding:0.7rem 1rem;font-size:0.85rem;color:#155724;font-weight:700;margin-bottom:1rem;}
    .refund-waiting{background:#fff3cd;border:1.5px solid #ffc107;border-radius:10px;padding:0.7rem 1rem;font-size:0.82rem;color:#856404;font-weight:600;margin-bottom:1rem;}
    .action-row{display:flex;gap:0.8rem;flex-wrap:wrap;}
    .btn-approve{padding:0.55rem 1.4rem;background:linear-gradient(135deg,#28a745,#1e7e34);color:white;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.88rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .2s;}
    .btn-reject{padding:0.55rem 1.4rem;background:linear-gradient(135deg,#dc3545,#b02a37);color:white;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.88rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .2s;}
    .empty-state{text-align:center;padding:4rem;color:var(--muted);background:var(--white);border-radius:22px;border:1.5px dashed var(--warm);}
    .empty-state span{font-size:3.5rem;display:block;margin-bottom:1rem;}

    @media(max-width:768px){
      nav{height:auto;flex-wrap:wrap;padding:0.6rem 4vw;gap:0.5rem;}
      .logo{font-size:1.3rem;}.nav-right{gap:0.4rem;}.nav-link{padding:0.3rem 0.6rem;font-size:0.72rem;}
      .main{margin-top:110px;padding:1.5rem 4vw;}
      .stats-row{grid-template-columns:repeat(3,1fr);}
      .refund-cards-row{grid-template-columns:1fr 1fr;}
      .return-body{grid-template-columns:1fr 1fr;}
      .refund-detail-grid{grid-template-columns:1fr;}
    }
    @media(max-width:500px){
      .stats-row{grid-template-columns:repeat(2,1fr);}
      .return-body{grid-template-columns:1fr;}
      .card-pad{padding:1rem;}
      .refund-cards-row{grid-template-columns:1fr;}
    }
  </style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="logo">Trenzo<span>Kart</span> <span style="font-size:0.75rem;color:var(--accent2);font-weight:400;font-family:'DM Sans',sans-serif;">Admin</span></a>
  <div class="nav-right">
    <a href="dashboard.php" class="nav-link">Dashboard</a>
    <a href="vendors.php" class="nav-link">Vendors</a>
    <a href="assistants.php" class="nav-link">🤝 Assistants</a>
    <a href="summary.php" class="nav-link">📊 Summary</a>
    <a href="logout.php" class="nav-link">Logout</a>
  </div>
</nav>

<div class="main">
  <h1 class="page-title">↩ Return Requests</h1>
  <p class="page-sub">Saare vendors ke return requests manage karo</p>

  <?php if (!empty($success)): ?><div class="msg">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

  <!-- STATS -->
  <div class="stats-row">
    <a href="returns.php?status=all"      class="stat-card <?= $filter==='all'?'active':'' ?>"><div class="stat-num"><?= $counts['all'] ?></div><div class="stat-label">📋 Total</div></a>
    <a href="returns.php?status=pending"  class="stat-card <?= $filter==='pending'?'active':'' ?>"><div class="stat-num" style="color:#e65100;"><?= $counts['pending'] ?></div><div class="stat-label">⏳ Pending</div></a>
    <a href="returns.php?status=approved" class="stat-card <?= $filter==='approved'?'active':'' ?>"><div class="stat-num" style="color:#28a745;"><?= $counts['approved'] ?></div><div class="stat-label">✅ Approved</div></a>
  </div>

  <!-- REFUND CARDS -->
  <div class="refund-cards-row">
    <a href="returns.php?status=refund_pending" class="refund-card pending-card <?= $filter==='refund_pending'?'active':'' ?>">
      <?php if ($counts['refund_pending'] > 0): ?><div class="refund-card-badge"></div><?php endif; ?>
      <div class="refund-card-icon">💳</div>
      <div class="refund-card-num"><?= $counts['refund_pending'] ?></div>
      <div class="refund-card-label">Refund Pending</div>
      <?php if ($refund_amount > 0): ?>
        <div class="refund-card-amount">₹<?= number_format($refund_amount,2) ?> process karna hai</div>
      <?php endif; ?>
    </a>
    <a href="returns.php?status=refund_done" class="refund-card done-card <?= $filter==='refund_done'?'active':'' ?>">
      <div class="refund-card-icon">✅</div>
      <div class="refund-card-num"><?= $counts['refund_done'] ?></div>
      <div class="refund-card-label">Refund Processed</div>
      <div class="refund-card-amount">Successfully completed</div>
    </a>
  </div>

  <!-- FILTER TABS -->
  <div class="filter-row">
    <a href="returns.php?status=all"            class="filter-tab <?= $filter==='all'?'active':'' ?>">📋 All</a>
    <a href="returns.php?status=pending"        class="filter-tab <?= $filter==='pending'?'active':'' ?>">⏳ Pending</a>
    <a href="returns.php?status=approved"       class="filter-tab <?= $filter==='approved'?'active':'' ?>">✅ Approved</a>
    <a href="returns.php?status=rejected"       class="filter-tab <?= $filter==='rejected'?'active':'' ?>">❌ Rejected</a>
    <a href="returns.php?status=refund_pending" class="filter-tab refund-tab <?= $filter==='refund_pending'?'active':'' ?>">💳 Refund Pending <?= $counts['refund_pending']>0?"({$counts['refund_pending']})":'' ?></a>
    <a href="returns.php?status=refund_done"    class="filter-tab <?= $filter==='refund_done'?'active':'' ?>">💰 Refund Done</a>
  </div>

  <?php if (empty($returns)): ?>
    <div class="empty-state"><span>↩</span><p>Is category mein koi return nahi!</p></div>
  <?php else: ?>
    <?php foreach ($returns as $r):
      $cname  = $r['order_name'] ?? $r['customer_name'];
      $cphone = preg_replace('/\D/', '', $r['order_phone'] ?? '');
      $has_refund_pending = ($r['status']==='approved' && $r['pickup_completed'] && !empty($r['refund_method']) && $r['refund_status'] !== 'processed');
    ?>
    <div class="return-card">
      <div class="card-strip <?= $has_refund_pending ? 'strip-refund' : 'strip-'.$r['status'] ?>"></div>
      <div class="card-pad">
        <div class="return-top">
          <div>
            <div class="return-order-id">#TK<?= str_pad($r['order_id'],5,'0',STR_PAD_LEFT) ?></div>
            <div class="return-date">📅 <?= date('d M Y, h:i A', strtotime($r['created_at'])) ?></div>
          </div>
          <div class="badges">
            <?php if ($r['pickup_completed']): ?><span class="pickup-badge">📦 Pickup Done</span><?php endif; ?>
            <?php if (!empty($r['refund_method']) && $r['refund_status'] !== 'processed'): ?><span class="refund-badge">💳 Refund Details Submitted</span><?php endif; ?>
            <?php if ($r['refund_status'] === 'processed'): ?><span class="pickup-badge">✅ Refund Done</span><?php endif; ?>
            <span class="status-badge s-<?= $r['status'] ?>"><?= strtoupper($r['status']) ?></span>
          </div>
        </div>

        <div class="return-body">
          <div class="info-item"><label>Customer</label><span><?= htmlspecialchars($cname) ?></span></div>
          <div class="info-item"><label>Vendor</label><span><?= htmlspecialchars($r['vendor_name']??'N/A') ?> — <?= htmlspecialchars($r['shop_name']??'') ?></span></div>
          <div class="info-item"><label>Amount</label><span style="font-family:'Playfair Display',serif;font-size:1rem;color:var(--brown);font-weight:700;">₹<?= number_format($r['total_amount'],2) ?></span></div>
          <?php if ($cphone): ?><div class="info-item"><label>Phone</label><span><a href="tel:<?= $cphone ?>" style="color:var(--accent);font-weight:600;">📞 <?= $cphone ?></a></span></div><?php endif; ?>
          <div class="info-item"><label>Payment</label><span><?= strtoupper($r['payment_method']??'COD') ?></span></div>
          <div class="info-item"><label>Address</label><span style="font-size:0.8rem;"><?= htmlspecialchars($r['address']??'N/A') ?></span></div>
        </div>

        <div class="reason-box">
          <div class="reason-label">Return Reason</div>
          <div class="reason-text"><?= htmlspecialchars($r['reason']) ?></div>
          <?php if (!empty($r['description'])): ?><div style="font-size:0.82rem;color:var(--muted);margin-top:0.2rem;"><?= htmlspecialchars($r['description']) ?></div><?php endif; ?>
        </div>

        <!-- REFUND SECTION -->
        <?php if ($r['status'] === 'approved' && $r['pickup_completed']): ?>
          <?php if ($r['refund_status'] === 'processed'): ?>
            <div class="refund-processed-box">
              💰 Refund Processed on <?= date('d M Y', strtotime($r['refund_at'])) ?>
              <?php if ($r['refund_method']==='upi'): ?> — UPI: <?= htmlspecialchars($r['refund_upi']??'') ?>
              <?php elseif ($r['refund_method']==='bank'): ?> — Bank: <?= htmlspecialchars($r['refund_bank_name']??'') ?><?php endif; ?>
            </div>
          <?php elseif (!empty($r['refund_method'])): ?>
            <div class="refund-section">
              <div class="refund-section-title">💳 Refund Details — Process Karo</div>
              <?php if ($r['refund_method'] === 'upi'): ?>
                <div class="refund-detail-grid">
                  <div class="refund-detail-item"><label>Method</label><span>📱 UPI</span></div>
                  <div class="refund-detail-item"><label>UPI ID</label><span style="font-size:1rem;color:#276749;"><?= htmlspecialchars($r['refund_upi']??'') ?></span></div>
                  <div class="refund-detail-item"><label>Amount to Send</label><span style="color:#276749;font-size:1.1rem;font-family:'Playfair Display',serif;">₹<?= number_format($r['total_amount'],2) ?></span></div>
                </div>
              <?php else: ?>
                <div class="refund-detail-grid">
                  <div class="refund-detail-item"><label>Method</label><span>🏦 Bank Transfer</span></div>
                  <div class="refund-detail-item"><label>Account Holder</label><span><?= htmlspecialchars($r['refund_holder']??'') ?></span></div>
                  <div class="refund-detail-item"><label>Account Number</label><span style="font-size:1rem;color:#276749;"><?= htmlspecialchars($r['refund_account_no']??'') ?></span></div>
                  <div class="refund-detail-item"><label>IFSC Code</label><span><?= htmlspecialchars($r['refund_ifsc']??'') ?></span></div>
                  <div class="refund-detail-item"><label>Bank Name</label><span><?= htmlspecialchars($r['refund_bank_name']??'') ?></span></div>
                  <div class="refund-detail-item"><label>Amount to Send</label><span style="color:#276749;font-size:1.1rem;font-family:'Playfair Display',serif;">₹<?= number_format($r['total_amount'],2) ?></span></div>
                </div>
              <?php endif; ?>
              <a href="returns.php?refund=1&id=<?= $r['id'] ?>" class="btn-process-refund"
                 onclick="return confirm('Refund bhej diya? ₹<?= number_format($r['total_amount'],2) ?>')">
                💰 Mark Refund as Processed →
              </a>
            </div>
          <?php else: ?>
            <div class="refund-waiting">⏳ Pickup completed — Customer refund details submit karega</div>
          <?php endif; ?>
        <?php endif; ?>

        <!-- ACTION BUTTONS -->
        <?php if ($r['status'] === 'pending'): ?>
        <div class="action-row">
          <a href="returns.php?action=approve&id=<?= $r['id'] ?>" class="btn-approve" onclick="return confirm('Approve?')">✅ Approve</a>
          <a href="returns.php?action=reject&id=<?= $r['id'] ?>"  class="btn-reject"  onclick="return confirm('Reject?')">❌ Reject</a>
        </div>
        <?php elseif ($r['status'] === 'approved' && !$r['pickup_completed']): ?>
          <div style="font-size:0.85rem;font-weight:600;color:#155724;">✅ Return Approved — Vendor pickup karega</div>
        <?php elseif ($r['status'] === 'rejected'): ?>
          <div style="font-size:0.85rem;font-weight:600;color:#721c24;">❌ Return Rejected</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
</body>
</html>