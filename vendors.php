<?php
require '../config.php';
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }

$success = '';
$error   = '';

// Approve / Block / Delete vendor
if (isset($_GET['action']) && isset($_GET['id'])) {
    $vid    = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action === 'approve') {
        $upd = mysqli_prepare($conn, "UPDATE vendors SET status='approved' WHERE id=?");
        mysqli_stmt_bind_param($upd, 'i', $vid);
        mysqli_stmt_execute($upd);
        $success = "Vendor approved successfully!";
    } elseif ($action === 'block') {
        $upd = mysqli_prepare($conn, "UPDATE vendors SET status='blocked' WHERE id=?");
        mysqli_stmt_bind_param($upd, 'i', $vid);
        mysqli_stmt_execute($upd);
        $success = "Vendor blocked!";
    } elseif ($action === 'delete') {
        $del = mysqli_prepare($conn, "DELETE FROM vendors WHERE id=?");
        mysqli_stmt_bind_param($del, 'i', $vid);
        mysqli_stmt_execute($del);
        $success = "Vendor deleted!";
    }
}

// Update commission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vendor_id'])) {
    $vid        = intval($_POST['vendor_id']);
    $commission = floatval($_POST['commission']);
    if ($commission >= 0 && $commission <= 100) {
        mysqli_query($conn, "UPDATE vendors SET commission='$commission' WHERE id='$vid'");
        $success = "Commission updated!";
    } else {
        $error = "Commission must be between 0 and 100!";
    }
}

// Filter
$filter = $_GET['filter'] ?? 'all';
$where  = $filter !== 'all' ? "WHERE status='$filter'" : "";

$vendors_result = mysqli_query($conn, "SELECT * FROM vendors $where ORDER BY id DESC");
$vendors = [];
while ($row = mysqli_fetch_assoc($vendors_result)) { $vendors[] = $row; }

// Counts
$counts = [];
foreach (['all', 'pending', 'approved', 'blocked'] as $s) {
    $w = $s === 'all' ? "" : "WHERE status='$s'";
    $counts[$s] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM vendors $w"))['c'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — Manage Vendors</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --cream: #f5efe6; --warm: #e8d5b7; --brown: #5c3d1e;
      --accent: #d4622a; --accent2: #e8a045; --text: #2d1a0a;
      --muted: #8a6a4a; --white: #fffdf8; --dark: #1a0f02;
    }
    body { font-family: 'DM Sans', sans-serif; background: var(--cream); color: var(--text); min-height: 100vh; }

    /* NAV */
    nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; background: var(--dark); display: flex; align-items: center; justify-content: space-between; padding: 0 5vw; height: 65px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
    .logo { font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 900; color: var(--white); text-decoration: none; letter-spacing: -0.5px; }
    .logo span { color: var(--accent2); }
    .nav-right { display: flex; align-items: center; gap: 0.8rem; }
    .nav-link { padding: 0.45rem 1.1rem; background: none; border: 1.5px solid rgba(255,255,255,0.25); border-radius: 50px; color: rgba(255,255,255,0.7); font-size: 0.82rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: all .2s; font-family: 'DM Sans', sans-serif; }
    .nav-link:hover { border-color: var(--accent2); color: var(--accent2); }

    /* MAIN */
    .main { margin-top: 85px; padding: 2.5rem 5vw; max-width: 1400px; margin-left: auto; margin-right: auto; }
    .page-header { margin-bottom: 2rem; }
    .page-title { font-family: 'Playfair Display', serif; font-size: 2rem; color: var(--brown); margin-bottom: 0.3rem; }
    .page-sub { color: var(--muted); font-size: 0.9rem; }

    /* MESSAGES */
    .msg { padding: 0.85rem 1.2rem; border-radius: 12px; font-size: 0.88rem; margin-bottom: 1.5rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; }
    .msg.success { background: #d4edda; color: #155724; border: 1.5px solid #b8dfc4; }
    .msg.error   { background: #f8d7da; color: #721c24; border: 1.5px solid #f5b8bc; }

    /* STATS */
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.2rem; margin-bottom: 2rem; }
    .stat-card { background: var(--white); border-radius: 18px; padding: 1.4rem 1.6rem; box-shadow: 0 4px 20px rgba(92,61,30,0.07); display: flex; align-items: center; gap: 1.2rem; border-top: 4px solid transparent; transition: transform .2s, box-shadow .2s; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(92,61,30,0.12); }
    .stat-card.orange { border-top-color: var(--accent); }
    .stat-card.yellow { border-top-color: var(--accent2); }
    .stat-card.green  { border-top-color: #2d8a4e; }
    .stat-card.red    { border-top-color: #dc3545; }
    .stat-icon-wrap { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; flex-shrink: 0; }
    .stat-card.orange .stat-icon-wrap { background: rgba(212,98,42,0.12); }
    .stat-card.yellow .stat-icon-wrap { background: rgba(232,160,69,0.12); }
    .stat-card.green  .stat-icon-wrap { background: rgba(45,138,78,0.12); }
    .stat-card.red    .stat-icon-wrap { background: rgba(220,53,69,0.12); }
    .stat-num { font-family: 'Playfair Display', serif; font-size: 1.8rem; font-weight: 900; color: var(--brown); line-height: 1; }
    .stat-label { font-size: 0.78rem; color: var(--muted); margin-top: 0.2rem; font-weight: 500; }

    /* FILTER TABS */
    .filter-tabs { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.8rem; }
    .filter-tab { padding: 0.5rem 1.2rem; border-radius: 50px; font-size: 0.83rem; font-weight: 600; text-decoration: none; border: 1.5px solid var(--warm); color: var(--muted); background: var(--white); transition: all .2s; display: flex; align-items: center; gap: 0.4rem; }
    .filter-tab:hover { border-color: var(--accent); color: var(--accent); background: #fff5f0; }
    .filter-tab.active { background: var(--accent); color: white; border-color: var(--accent); box-shadow: 0 4px 14px rgba(212,98,42,0.3); }
    .filter-count { background: rgba(0,0,0,0.12); color: inherit; border-radius: 50px; padding: 0 7px; font-size: 0.72rem; font-weight: 700; }
    .filter-tab.active .filter-count { background: rgba(255,255,255,0.25); }

    /* VENDORS GRID */
    .vendors-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 1.5rem; }

    /* VENDOR CARD */
    .vendor-card { background: var(--white); border-radius: 22px; padding: 1.6rem; box-shadow: 0 4px 24px rgba(92,61,30,0.07); border: 1.5px solid rgba(232,213,183,0.5); transition: all .25s; position: relative; overflow: hidden; }
    .vendor-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
    .vendor-card.pending::before  { background: linear-gradient(90deg, #e8a045, #f5c842); }
    .vendor-card.approved::before { background: linear-gradient(90deg, #2d8a4e, #43c069); }
    .vendor-card.blocked::before  { background: linear-gradient(90deg, #dc3545, #ff6b6b); }
    .vendor-card:hover { transform: translateY(-4px); box-shadow: 0 16px 40px rgba(92,61,30,0.13); border-color: var(--warm); }
    .vendor-card.blocked { opacity: 0.88; }

    /* VENDOR TOP */
    .vendor-top { display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1.2rem; }
    .vendor-avatar { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-family: 'Playfair Display', serif; font-size: 1.4rem; font-weight: 900; color: white; flex-shrink: 0; box-shadow: 0 4px 14px rgba(0,0,0,0.15); }
    .avatar-pending  { background: linear-gradient(135deg, #e8a045, #f5c842); }
    .avatar-approved { background: linear-gradient(135deg, #2d8a4e, #43c069); }
    .avatar-blocked  { background: linear-gradient(135deg, #dc3545, #ff6b6b); }
    .vendor-info { flex: 1; min-width: 0; }
    .vendor-name { font-weight: 700; font-size: 1rem; color: var(--text); margin-bottom: 0.15rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .vendor-shop { font-size: 0.82rem; color: var(--brown); font-weight: 600; margin-bottom: 0.1rem; }
    .vendor-email { font-size: 0.75rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .status-badge { padding: 0.28rem 0.85rem; border-radius: 50px; font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; flex-shrink: 0; }
    .badge-pending  { background: #fff3cd; color: #856404; border: 1.5px solid #ffd700; }
    .badge-approved { background: #d4edda; color: #155724; border: 1.5px solid #90d4a8; }
    .badge-blocked  { background: #f8d7da; color: #721c24; border: 1.5px solid #f5a0a8; }

    /* VENDOR DETAILS */
    .vendor-details { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0; margin-bottom: 1.2rem; background: linear-gradient(135deg, var(--cream), #ede0cc); border-radius: 14px; overflow: hidden; border: 1.5px solid var(--warm); }
    .detail-item { padding: 0.75rem 1rem; text-align: center; border-right: 1px solid rgba(232,213,183,0.7); border-bottom: 1px solid rgba(232,213,183,0.7); }
    .detail-item:nth-child(2) { border-right: none; }
    .detail-item:nth-child(3) { border-bottom: none; }
    .detail-item:nth-child(4) { border-right: none; border-bottom: none; }
    .detail-item strong { display: block; font-family: 'Playfair Display', serif; font-size: 1.2rem; font-weight: 900; color: var(--brown); margin-bottom: 0.15rem; }
    .detail-item span { font-size: 0.72rem; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

    /* VENDOR JOIN */
    .vendor-join { font-size: 0.75rem; color: var(--muted); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap; }
    .vendor-join-item { display: flex; align-items: center; gap: 0.3rem; }

    /* COMMISSION FORM */
    .commission-form { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.2rem; padding: 0.8rem 1rem; background: linear-gradient(135deg, var(--cream), #ede0cc); border-radius: 12px; border: 1.5px solid var(--warm); flex-wrap: nowrap; }
    .commission-label { font-size: 0.82rem; font-weight: 700; color: var(--brown); flex-shrink: 0; white-space: nowrap; }
    .commission-input { flex: 1; min-width: 0; max-width: 90px; padding: 0.45rem 0.6rem; border: 1.5px solid var(--warm); border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 0.9rem; font-weight: 600; color: var(--text); background: var(--white); outline: none; text-align: center; transition: border-color .2s; }
    .commission-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(212,98,42,0.1); }
    .commission-pct { font-size: 0.82rem; color: var(--muted); font-weight: 600; flex-shrink: 0; }
    .btn-comm { flex-shrink: 0; padding: 0.48rem 1.1rem; background: var(--accent); color: white; border: none; border-radius: 9px; font-size: 0.82rem; font-weight: 700; cursor: pointer; font-family: 'DM Sans', sans-serif; transition: all .2s; white-space: nowrap; }
    .btn-comm:hover { background: #c0551f; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(212,98,42,0.3); }

    /* ACTION BUTTONS */
    .vendor-actions { display: flex; gap: 0.6rem; }
    .action-btn { flex: 1; padding: 0.6rem 0.5rem; border-radius: 11px; font-size: 0.8rem; font-weight: 700; cursor: pointer; font-family: 'DM Sans', sans-serif; text-align: center; text-decoration: none; transition: all .22s; border: none; display: flex; align-items: center; justify-content: center; gap: 0.3rem; }
    .btn-approve { background: linear-gradient(135deg, #d4edda, #b8dfc4); color: #155724; box-shadow: 0 2px 8px rgba(45,138,78,0.15); }
    .btn-approve:hover { background: linear-gradient(135deg, #2d8a4e, #1e6b3a); color: white; transform: translateY(-2px); box-shadow: 0 6px 18px rgba(45,138,78,0.35); }
    .btn-block { background: linear-gradient(135deg, #fff3cd, #ffe8a0); color: #856404; box-shadow: 0 2px 8px rgba(133,100,4,0.12); }
    .btn-block:hover { background: linear-gradient(135deg, #856404, #6b5003); color: white; transform: translateY(-2px); box-shadow: 0 6px 18px rgba(133,100,4,0.3); }
    .btn-del { background: linear-gradient(135deg, #f8d7da, #f5b8bc); color: #721c24; box-shadow: 0 2px 8px rgba(114,28,36,0.12); }
    .btn-del:hover { background: linear-gradient(135deg, #721c24, #5a1520); color: white; transform: translateY(-2px); box-shadow: 0 6px 18px rgba(114,28,36,0.3); }

    /* EMPTY STATE */
    .empty-state { text-align: center; padding: 5rem 2rem; color: var(--muted); background: var(--white); border-radius: 22px; border: 1.5px dashed var(--warm); }
    .empty-state .empty-icon { font-size: 4rem; display: block; margin-bottom: 1rem; }
    .empty-state p { font-size: 1rem; font-weight: 500; }

    /* TOAST */
    .toast { position: fixed; bottom: 2rem; right: 2rem; background: var(--brown); color: white; padding: 0.9rem 1.6rem; border-radius: 14px; font-size: 0.88rem; font-weight: 600; box-shadow: 0 8px 30px rgba(0,0,0,0.25); transform: translateY(120px); opacity: 0; transition: all 0.35s cubic-bezier(.34,1.56,.64,1); z-index: 999; }
    .toast.show { transform: translateY(0); opacity: 1; }

    @media (max-width: 900px) { .stats-grid { grid-template-columns: repeat(2,1fr); } }
    @media(max-width:600px){ .vendors-grid{grid-template-columns:1fr;} .vendor-details{grid-template-columns:repeat(2,1fr);} nav{height:auto;flex-wrap:wrap;padding:0.6rem 4vw;gap:0.5rem;} .logo{font-size:1.2rem;} .nav-right,.nav-links{gap:0.4rem;flex-wrap:wrap;} .nav-link{padding:0.3rem 0.6rem;font-size:0.72rem;} .main{margin-top:100px !important;padding:1rem 3vw;} }
  </style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="logo">Trenzo<span>Kart</span></a>
  <div class="nav-right">
    <a href="dashboard.php" class="nav-link">← Dashboard</a>
    <a href="logout.php" class="nav-link">Logout</a>
  </div>
</nav>

<div class="main">
  <div class="page-header">
    <h1 class="page-title">🏪 Manage Vendors</h1>
    <p class="page-sub">Approve, block and manage vendor commissions</p>
  </div>

  <?php if (!empty($success)): ?>
    <div class="msg success">✅ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="msg error">❌ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card orange">
      <div class="stat-icon-wrap">🏪</div>
      <div><div class="stat-num"><?= $counts['all'] ?></div><div class="stat-label">Total Vendors</div></div>
    </div>
    <div class="stat-card yellow">
      <div class="stat-icon-wrap">⏳</div>
      <div><div class="stat-num"><?= $counts['pending'] ?></div><div class="stat-label">Pending Approval</div></div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon-wrap">✅</div>
      <div><div class="stat-num"><?= $counts['approved'] ?></div><div class="stat-label">Active Vendors</div></div>
    </div>
    <div class="stat-card red">
      <div class="stat-icon-wrap">🚫</div>
      <div><div class="stat-num"><?= $counts['blocked'] ?></div><div class="stat-label">Blocked Vendors</div></div>
    </div>
  </div>

  <!-- Filter Tabs -->
  <div class="filter-tabs">
    <?php
    $tabs = ['all' => '🏪 All', 'pending' => '⏳ Pending', 'approved' => '✅ Approved', 'blocked' => '🚫 Blocked'];
    foreach ($tabs as $key => $label):
    ?>
    <a href="vendors.php?filter=<?= $key ?>" class="filter-tab <?= $filter === $key ? 'active' : '' ?>">
      <?= $label ?> <span class="filter-count"><?= $counts[$key] ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Vendors -->
  <?php if (empty($vendors)): ?>
    <div class="empty-state">
      <span class="empty-icon">🏪</span>
      <p>No vendors found in this category!</p>
    </div>
  <?php else: ?>
  <div class="vendors-grid">
    <?php foreach ($vendors as $v):
      $prod_count  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM products WHERE vendor_id={$v['id']}"))['c'];
      $order_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE vendor_id={$v['id']}"))['c'];
      $revenue     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_amount) as t FROM orders WHERE vendor_id={$v['id']} AND status != 'cancelled'"))['t'] ?? 0;
    ?>
    <div class="vendor-card <?= $v['status'] ?>">
      <!-- Top -->
      <div class="vendor-top">
        <div class="vendor-avatar avatar-<?= $v['status'] ?>"><?= strtoupper(substr($v['name'], 0, 1)) ?></div>
        <div class="vendor-info">
          <div class="vendor-name"><?= htmlspecialchars($v['name']) ?></div>
          <div class="vendor-shop">🏪 <?= htmlspecialchars($v['shop_name']) ?></div>
          <div class="vendor-email">✉️ <?= htmlspecialchars($v['email']) ?></div>
        </div>
        <span class="status-badge badge-<?= $v['status'] ?>"><?= ucfirst($v['status']) ?></span>
      </div>

      <!-- Stats -->
      <div class="vendor-details">
        <div class="detail-item"><strong><?= $prod_count ?></strong><span>Products</span></div>
        <div class="detail-item"><strong><?= $order_count ?></strong><span>Orders</span></div>
        <div class="detail-item"><strong>₹<?= number_format($revenue, 0) ?></strong><span>Revenue</span></div>
        <div class="detail-item"><strong><?= $v['commission'] ?>%</strong><span>Commission</span></div>
      </div>

      <!-- Join Info -->
      <div class="vendor-join">
        <span class="vendor-join-item">📅 <?= date('d M Y', strtotime($v['created_at'])) ?></span>
        <?php if (!empty($v['phone'])): ?>
          <span class="vendor-join-item">📞 <?= htmlspecialchars($v['phone']) ?></span>
        <?php endif; ?>
      </div>



      <!-- Action Buttons -->
      <div class="vendor-actions">
        <?php if ($v['status'] !== 'approved'): ?>
          <a href="vendors.php?action=approve&id=<?= $v['id'] ?>&filter=<?= $filter ?>" class="action-btn btn-approve" onclick="return confirm('Approve this vendor?')">✅ Approve</a>
        <?php endif; ?>
        <?php if ($v['status'] !== 'blocked'): ?>
          <a href="vendors.php?action=block&id=<?= $v['id'] ?>&filter=<?= $filter ?>" class="action-btn btn-block" onclick="return confirm('Block this vendor?')">🚫 Block</a>
        <?php endif; ?>
        <a href="vendors.php?action=delete&id=<?= $v['id'] ?>&filter=<?= $filter ?>" class="action-btn btn-del" onclick="return confirm('Delete this vendor permanently?')">🗑️ Delete</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="toast" id="toast"></div>
<?php if (!empty($success) || !empty($error)): ?>
<script>
window.onload = () => {
  const t = document.getElementById('toast');
  t.textContent = '<?= !empty($success) ? "✅ " . $success : "❌ " . $error ?>';
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}
</script>
<?php endif; ?>
</body>
</html>