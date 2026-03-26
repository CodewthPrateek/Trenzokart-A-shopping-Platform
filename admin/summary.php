<?php
require '../config.php';
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }

$success = ''; $error = '';
$month = $_GET['month'] ?? date('Y-m');

// Deduct 10% commission for selected month
if (isset($_POST['deduct_commission'])) {
    $m = mysqli_real_escape_string($conn, $_POST['month']);
    $vendors_res = mysqli_query($conn, "SELECT id, name, commission FROM vendors WHERE status='approved'");
    while ($v = mysqli_fetch_assoc($vendors_res)) {
        $vid = $v['id'];
        $rate = $v['commission'] ?? 10;
        // Revenue for this month
        $rev = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT SUM(o.total_amount) as rev FROM orders o
            WHERE o.vendor_id='$vid' AND o.status='delivered'
            AND DATE_FORMAT(o.created_at, '%Y-%m') = '$m'
        "))['rev'] ?? 0;
        $commission = round($rev * $rate / 100, 2);
        // Check if already deducted
        $exists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM commission_deductions WHERE vendor_id='$vid' AND month='$m'"));
        if (!$exists && $rev > 0) {
            mysqli_query($conn, "INSERT INTO commission_deductions (vendor_id, month, total_revenue, commission_rate, commission_amount, status) VALUES ('$vid','$m','$rev','$rate','$commission','deducted')");
        }
    }
    $success = "Commission deducted for $m!";
}

// Fetch vendor summaries with all status counts
$vendors_res = mysqli_query($conn, "SELECT v.*, 
    (SELECT COUNT(*) FROM products p WHERE p.vendor_id = v.id) as product_count,
    (SELECT COUNT(*) FROM orders o WHERE o.vendor_id = v.id) as total_orders,
    (SELECT COUNT(*) FROM orders o WHERE o.vendor_id = v.id AND o.status='pending') as pending_count,
    (SELECT COUNT(*) FROM orders o WHERE o.vendor_id = v.id AND o.status='confirmed') as confirmed_count,
    (SELECT COUNT(*) FROM orders o WHERE o.vendor_id = v.id AND o.status='dispatched') as dispatched_count,
    (SELECT COUNT(*) FROM orders o WHERE o.vendor_id = v.id AND o.status='shipped') as shipped_count,
    (SELECT COUNT(*) FROM orders o WHERE o.vendor_id = v.id AND o.status='delivered') as delivered_count,
    (SELECT COUNT(*) FROM orders o WHERE o.vendor_id = v.id AND o.status='cancelled') as cancelled_count,
    (SELECT COUNT(*) FROM return_requests rr JOIN orders o ON rr.order_id=o.id WHERE o.vendor_id = v.id) as return_count,
    (SELECT COUNT(*) FROM return_requests rr JOIN orders o ON rr.order_id=o.id WHERE o.vendor_id = v.id AND rr.status='pending') as return_pending,
    (SELECT SUM(o.total_amount) FROM orders o WHERE o.vendor_id = v.id AND o.status = 'delivered') as total_revenue,
    (SELECT SUM(o.total_amount) FROM orders o WHERE o.vendor_id = v.id AND o.status = 'delivered' AND DATE_FORMAT(o.created_at,'%Y-%m') = '$month') as monthly_revenue
    FROM vendors v WHERE v.status='approved' ORDER BY v.id");
$vendors = [];
while ($row = mysqli_fetch_assoc($vendors_res)) { $vendors[] = $row; }

// Total stats
$total_revenue   = array_sum(array_column($vendors, 'total_revenue'));
$monthly_revenue = array_sum(array_column($vendors, 'monthly_revenue'));
$total_commission = round($monthly_revenue * 10 / 100, 2);

// Commission deductions history
$deductions = mysqli_query($conn, "
    SELECT cd.*, v.name as vendor_name, v.shop_name
    FROM commission_deductions cd
    JOIN vendors v ON cd.vendor_id = v.id
    ORDER BY cd.created_at DESC LIMIT 20
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart Admin — Account Summary</title>
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
    .page-title{font-family:'Playfair Display',serif;font-size:2rem;color:var(--brown);margin-bottom:0.3rem;}
    .page-sub{color:var(--muted);font-size:0.9rem;margin-bottom:2rem;}
    .msg{padding:0.85rem 1.2rem;border-radius:12px;font-size:0.88rem;margin-bottom:1.5rem;font-weight:500;background:#d4edda;color:#155724;border:1.5px solid #b8dfc4;}

    /* TOP STATS */
    .top-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;}
    .top-stat{background:var(--white);border-radius:18px;padding:1.5rem;text-align:center;box-shadow:0 4px 20px rgba(92,61,30,0.06);border-left:4px solid var(--accent);}
    .top-stat-num{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:900;color:var(--brown);}
    .top-stat-label{font-size:0.78rem;color:var(--muted);font-weight:600;margin-top:0.3rem;}

    /* MONTH SELECTOR + DEDUCT */
    .month-section{background:var(--white);border-radius:18px;padding:1.5rem;margin-bottom:2rem;box-shadow:0 4px 20px rgba(92,61,30,0.06);display:flex;align-items:center;gap:1rem;flex-wrap:wrap;}
    .month-section label{font-size:0.88rem;font-weight:600;color:var(--brown);}
    .month-input{padding:0.65rem 1rem;border:1.5px solid var(--warm);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.9rem;color:var(--text);background:var(--cream);outline:none;}
    .month-input:focus{border-color:var(--accent);}
    .btn-deduct{padding:0.7rem 1.5rem;background:var(--accent);color:white;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.9rem;font-weight:700;cursor:pointer;transition:all .2s;}
    .btn-deduct:hover{background:#c0551f;transform:translateY(-1px);}
    .commission-preview{font-size:0.88rem;color:var(--muted);margin-left:auto;}
    .commission-preview strong{color:var(--accent);font-size:1rem;}

    /* VENDOR CARDS */
    .section-title{font-family:'Playfair Display',serif;font-size:1.4rem;color:var(--brown);margin-bottom:1rem;}
    .vendors-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:1.5rem;margin-bottom:2rem;}
    .vendor-card{background:var(--white);border-radius:20px;padding:1.5rem;box-shadow:0 4px 20px rgba(92,61,30,0.07);border:1.5px solid var(--warm);transition:all .2s;cursor:pointer;text-decoration:none;display:block;color:var(--text);}
    .vendor-card:hover{box-shadow:0 8px 32px rgba(92,61,30,0.15);border-color:var(--accent);transform:translateY(-3px);}
    .vendor-header{display:flex;align-items:center;gap:0.8rem;margin-bottom:1.2rem;padding-bottom:0.8rem;border-bottom:1.5px solid var(--warm);}
    .v-avatar{width:46px;height:46px;border-radius:50%;background:var(--accent);color:white;font-size:1.2rem;font-weight:900;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .v-name{font-weight:700;font-size:1rem;color:var(--text);}
    .v-shop{font-size:0.78rem;color:var(--muted);}
    .v-email{font-size:0.72rem;color:var(--muted);}

    /* ACTIVITY GRID */
    .activity-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:0.5rem;margin-bottom:1rem;}
    .activity-item{background:var(--cream);border-radius:10px;padding:0.6rem 0.4rem;text-align:center;border:1px solid var(--warm);}
    .activity-num{font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:900;}
    .activity-label{font-size:0.62rem;font-weight:700;text-transform:uppercase;letter-spacing:0.3px;margin-top:0.1rem;}
    .a-pending{color:#856404;}.a-confirmed{color:#0c5460;}.a-dispatched{color:#854d00;}.a-shipped{color:#44337a;}
    .a-delivered{color:#155724;}.a-cancelled{color:#721c24;}.a-return{color:#c62828;}.a-products{color:var(--brown);}

    .v-revenue-row{display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;margin-bottom:0.8rem;}
    .v-rev-box{background:var(--cream);border-radius:10px;padding:0.7rem;text-align:center;}
    .v-rev-num{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:900;color:var(--brown);}
    .v-rev-label{font-size:0.68rem;color:var(--muted);font-weight:700;text-transform:uppercase;margin-top:0.1rem;}
    .v-commission{padding:0.6rem 0.8rem;background:linear-gradient(135deg,#fff3e0,#ffe0b2);border-radius:10px;display:flex;justify-content:space-between;align-items:center;}
    .v-commission-label{font-size:0.78rem;color:#e65100;font-weight:600;}
    .v-commission-amount{font-size:1rem;font-weight:900;color:#e65100;}
    .return-alert{background:#ffebee;border:1px solid #f5b8bc;border-radius:8px;padding:0.4rem 0.7rem;font-size:0.75rem;color:#c62828;font-weight:600;margin-top:0.6rem;display:none;}
    .return-alert.show{display:block;}

    /* DEDUCTION HISTORY */
    .history-table{background:var(--white);border-radius:18px;padding:1.5rem;box-shadow:0 4px 20px rgba(92,61,30,0.06);overflow-x:auto;}
    table{width:100%;border-collapse:collapse;}
    th{text-align:left;font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;padding:0.6rem 0.8rem;border-bottom:2px solid var(--warm);}
    td{padding:0.8rem 0.8rem;font-size:0.88rem;border-bottom:1px solid rgba(232,213,183,0.4);}
    tr:last-child td{border-bottom:none;}
    tr:hover td{background:var(--cream);}
    .deducted-badge{background:#d4edda;color:#155724;padding:0.2rem 0.6rem;border-radius:50px;font-size:0.72rem;font-weight:700;}
    @media(max-width:768px){.top-stats{grid-template-columns:repeat(2,1fr);} .vendors-grid{grid-template-columns:1fr;}}
    @media(max-width:600px){ nav{height:auto;flex-wrap:wrap;padding:0.6rem 4vw;gap:0.5rem;} .logo{font-size:1.2rem;} .nav-right,.nav-links{gap:0.4rem;flex-wrap:wrap;} .nav-link{padding:0.3rem 0.6rem;font-size:0.72rem;} .main{margin-top:100px !important;padding:1rem 3vw;} .top-stats{grid-template-columns:repeat(2,1fr);} }
  </style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="logo">Trenzo<span>Kart</span> <span style="font-size:0.75rem;color:var(--accent2);font-weight:400;font-family:'DM Sans',sans-serif;">Admin</span></a>
  <div class="nav-right">
    <a href="dashboard.php" class="nav-link">Dashboard</a>
    <a href="returns.php" class="nav-link">↩ Returns</a>
    <a href="assistants.php" class="nav-link">🤝 Assistants</a>
    <a href="logout.php" class="nav-link">Logout</a>
  </div>
</nav>

<div class="main">
  <h1 class="page-title">📊 Account Summary</h1>
  <p class="page-sub">Saare vendors ki earnings aur monthly commission report</p>

  <?php if (!empty($success)): ?><div class="msg">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

  <!-- TOP STATS -->
  <div class="top-stats">
    <div class="top-stat">
      <div class="top-stat-num">₹<?= number_format($total_revenue, 0) ?></div>
      <div class="top-stat-label">💰 Total Revenue (All Time)</div>
    </div>
    <div class="top-stat" style="border-left-color:#e8a045;">
      <div class="top-stat-num">₹<?= number_format($monthly_revenue, 0) ?></div>
      <div class="top-stat-label">📅 Monthly Revenue (<?= $month ?>)</div>
    </div>
    <div class="top-stat" style="border-left-color:#28a745;">
      <div class="top-stat-num">₹<?= number_format($total_commission, 0) ?></div>
      <div class="top-stat-label">✂️ 10% Commission (<?= $month ?>)</div>
    </div>
    <div class="top-stat" style="border-left-color:#6f42c1;">
      <div class="top-stat-num"><?= count($vendors) ?></div>
      <div class="top-stat-label">🏪 Active Vendors</div>
    </div>
  </div>

  <!-- MONTH SELECTOR + DEDUCT -->
  <form method="POST">
    <div class="month-section">
      <label>📅 Select Month:</label>
      <input type="month" name="month" class="month-input" value="<?= $month ?>"/>
      <button type="submit" name="deduct_commission" class="btn-deduct">✂️ Deduct 10% Commission</button>
      <div class="commission-preview">
        Monthly 10% = <strong>₹<?= number_format($total_commission, 2) ?></strong>
      </div>
    </div>
  </form>

  <!-- VENDOR SUMMARIES -->
  <div class="section-title">🏪 Vendor Activity Report — <?= $month ?></div>
  <div class="vendors-grid">
    <?php foreach ($vendors as $v):
      $monthly_rev = floatval($v['monthly_revenue'] ?? 0);
      $commission  = round($monthly_rev * ($v['commission'] ?? 10) / 100, 2);
      $net_income  = $monthly_rev - $commission;
    ?>
    <a href="vendor_detail.php?id=<?= $v['id'] ?>" class="vendor-card">
      <!-- Header -->
      <div class="vendor-header">
        <div class="v-avatar"><?= strtoupper(substr($v['name'], 0, 1)) ?></div>
        <div style="flex:1;">
          <div class="v-name"><?= htmlspecialchars($v['name']) ?></div>
          <div class="v-shop">🏪 <?= htmlspecialchars($v['shop_name']) ?></div>
          <div class="v-email">✉️ <?= htmlspecialchars($v['email']) ?></div>
        </div>
        <div style="text-align:right;">
          <div style="font-size:0.72rem;color:var(--muted);font-weight:700;">PRODUCTS</div>
          <div style="font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:900;color:var(--brown);"><?= $v['product_count'] ?></div>
        </div>
      </div>

      <!-- Activity Grid -->
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:0.5rem;">📊 Order Activity</div>
      <div class="activity-grid">
        <div class="activity-item">
          <div class="activity-num a-pending"><?= $v['pending_count'] ?></div>
          <div class="activity-label a-pending">⏳ Pending</div>
        </div>
        <div class="activity-item">
          <div class="activity-num a-confirmed"><?= $v['confirmed_count'] ?></div>
          <div class="activity-label a-confirmed">✅ Confirmed</div>
        </div>
        <div class="activity-item">
          <div class="activity-num a-dispatched"><?= $v['dispatched_count'] ?></div>
          <div class="activity-label a-dispatched">📦 Dispatch</div>
        </div>
        <div class="activity-item">
          <div class="activity-num a-shipped"><?= $v['shipped_count'] ?></div>
          <div class="activity-label a-shipped">🚚 Shipped</div>
        </div>
        <div class="activity-item">
          <div class="activity-num a-delivered"><?= $v['delivered_count'] ?></div>
          <div class="activity-label a-delivered">🎉 Delivered</div>
        </div>
        <div class="activity-item">
          <div class="activity-num a-cancelled"><?= $v['cancelled_count'] ?></div>
          <div class="activity-label a-cancelled">❌ Cancelled</div>
        </div>
        <div class="activity-item" style="grid-column:span 2;background:<?= $v['return_count'] > 0 ? '#ffebee' : 'var(--cream)' ?>;">
          <div class="activity-num a-return"><?= $v['return_count'] ?></div>
          <div class="activity-label a-return">↩ Returns <?= $v['return_pending'] > 0 ? "({$v['return_pending']} pending)" : '' ?></div>
        </div>
      </div>

      <!-- Revenue -->
      <div class="v-revenue-row">
        <div class="v-rev-box">
          <div class="v-rev-num">₹<?= number_format($v['total_revenue'] ?? 0, 0) ?></div>
          <div class="v-rev-label">💰 Total Revenue</div>
        </div>
        <div class="v-rev-box">
          <div class="v-rev-num">₹<?= number_format($monthly_rev, 0) ?></div>
          <div class="v-rev-label">📅 This Month</div>
        </div>
      </div>

      <!-- Commission -->
      <div class="v-commission">
        <div>
          <div class="v-commission-label">✂️ 10% Commission Deduction</div>
          <div style="font-size:0.72rem;color:#e65100;">Net Payout: ₹<?= number_format($net_income, 2) ?></div>
        </div>
        <div class="v-commission-amount">−₹<?= number_format($commission, 2) ?></div>
      </div>

      <?php if ($v['return_pending'] > 0): ?>
      <div class="return-alert show">⚠️ <?= $v['return_pending'] ?> return request(s) pending approval!</div>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- DEDUCTION HISTORY -->
  <div class="section-title">📋 Commission Deduction History</div>
  <div class="history-table">
    <table>
      <thead>
        <tr>
          <th>Vendor</th>
          <th>Month</th>
          <th>Revenue</th>
          <th>Rate</th>
          <th>Commission</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($d = mysqli_fetch_assoc($deductions)): ?>
        <tr>
          <td><strong><?= htmlspecialchars($d['vendor_name']) ?></strong><br><span style="font-size:0.75rem;color:var(--muted);"><?= htmlspecialchars($d['shop_name']) ?></span></td>
          <td><?= $d['month'] ?></td>
          <td>₹<?= number_format($d['total_revenue'], 2) ?></td>
          <td><?= $d['commission_rate'] ?>%</td>
          <td><strong>₹<?= number_format($d['commission_amount'], 2) ?></strong></td>
          <td><span class="deducted-badge">✅ Deducted</span></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>