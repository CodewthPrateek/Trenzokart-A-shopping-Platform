<?php
require '../config.php';
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
$success = '';

// Approve
if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    mysqli_query($conn, "UPDATE delivery_boys SET status='active' WHERE id='$id'");
    $success = "Delivery boy approved!";
}
// Reject/Delete
if (isset($_GET['reject'])) {
    $id = intval($_GET['reject']);
    mysqli_query($conn, "UPDATE delivery_boys SET status='inactive' WHERE id='$id'");
    $success = "Delivery boy rejected!";
}
// Delete permanently
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM delivery_boys WHERE id='$id'");
    $success = "Delivery boy deleted!";
}

$filter = $_GET['status'] ?? 'all';
$where  = "1=1";
if ($filter !== 'all') { $where = "status='" . mysqli_real_escape_string($conn, $filter) . "'"; }

$result = mysqli_query($conn, "SELECT * FROM delivery_boys WHERE $where ORDER BY created_at DESC");
$boys   = [];
while ($row = mysqli_fetch_assoc($result)) { $boys[] = $row; }

$counts = [
    'all'      => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM delivery_boys"))['c'],
    'inactive' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM delivery_boys WHERE status='inactive'"))['c'],
    'active'   => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM delivery_boys WHERE status='active'"))['c'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart Admin — Delivery Boys</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{--cream:#f5efe6;--warm:#e8d5b7;--brown:#5c3d1e;--accent:#d4622a;--accent2:#e8a045;--text:#2d1a0a;--muted:#8a6a4a;--white:#fffdf8;--dark:#1a0f02;}
    body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--text);}
    nav{position:fixed;top:0;left:0;right:0;z-index:100;background:var(--dark);display:flex;align-items:center;justify-content:space-between;padding:0 5vw;height:65px;box-shadow:0 4px 20px rgba(0,0,0,0.3);}
    .logo{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:900;color:var(--white);text-decoration:none;}
    .logo span{color:var(--accent2);}
    .nav-right{display:flex;gap:0.8rem;flex-wrap:wrap;align-items:center;}
    .nav-link{padding:0.45rem 1.1rem;border:1.5px solid rgba(255,255,255,0.25);border-radius:50px;color:rgba(255,255,255,0.7);font-size:0.82rem;font-weight:600;text-decoration:none;transition:all .2s;}
    .nav-link:hover{border-color:var(--accent2);color:var(--accent2);}
    .main{margin-top:85px;padding:2.5rem 5vw;}
    .page-title{font-family:'Playfair Display',serif;font-size:2rem;color:var(--brown);margin-bottom:0.3rem;}
    .page-sub{color:var(--muted);font-size:0.9rem;margin-bottom:2rem;}
    .msg{padding:0.85rem 1.2rem;border-radius:12px;font-size:0.88rem;margin-bottom:1.5rem;font-weight:500;background:#d4edda;color:#155724;border:1.5px solid #b8dfc4;}

    /* PENDING ALERT */
    .pending-alert{background:linear-gradient(135deg,#fff3cd,#ffe69c);border:1.5px solid #ffc107;border-radius:14px;padding:1rem 1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;}
    .pending-dot{width:10px;height:10px;border-radius:50%;background:#ffc107;animation:pulse 1s infinite;flex-shrink:0;}
    @keyframes pulse{0%,100%{opacity:1}50%{opacity:0.3}}
    .pending-text{font-size:0.9rem;font-weight:700;color:#856404;}
    .pending-sub{font-size:0.78rem;color:#a07000;}

    .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:2rem;}
    .stat-card{background:var(--white);border-radius:16px;padding:1.2rem;text-align:center;box-shadow:0 2px 12px rgba(92,61,30,0.07);text-decoration:none;display:block;border:2px solid transparent;transition:all .2s;}
    .stat-card:hover,.stat-card.active{border-color:var(--accent);transform:translateY(-2px);}
    .stat-num{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:900;color:var(--brown);}
    .stat-label{font-size:0.75rem;color:var(--muted);font-weight:600;margin-top:0.2rem;}

    .filter-row{display:flex;gap:0.5rem;margin-bottom:1.5rem;flex-wrap:wrap;}
    .filter-tab{padding:0.45rem 1.1rem;border-radius:50px;font-size:0.82rem;font-weight:600;cursor:pointer;border:1.5px solid var(--warm);background:var(--white);color:var(--muted);text-decoration:none;transition:all .2s;}
    .filter-tab.active,.filter-tab:hover{background:var(--accent);border-color:var(--accent);color:white;}

    /* DELIVERY BOY CARDS */
    .boys-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.2rem;}
    .boy-card{background:var(--white);border-radius:20px;overflow:hidden;box-shadow:0 3px 18px rgba(92,61,30,0.07);border:1.5px solid var(--warm);transition:all .2s;}
    .boy-card:hover{transform:translateY(-3px);box-shadow:0 8px 28px rgba(92,61,30,0.12);}
    .boy-card.pending{border-color:#ffc107;}
    .boy-strip{height:4px;}
    .strip-active{background:linear-gradient(90deg,#28a745,#48c774);}
    .strip-inactive{background:linear-gradient(90deg,#ffc107,#ffca2c);}
    .boy-body{padding:1.2rem;}
    .boy-top{display:flex;align-items:center;gap:0.8rem;margin-bottom:1rem;}
    .boy-avatar{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;color:white;font-weight:700;font-family:'Playfair Display',serif;}
    .boy-name{font-weight:700;font-size:1rem;color:var(--text);}
    .boy-company{font-size:0.75rem;color:var(--muted);margin-top:0.1rem;}
    .status-badge{padding:0.2rem 0.7rem;border-radius:50px;font-size:0.7rem;font-weight:700;margin-left:auto;flex-shrink:0;}
    .s-active{background:#d4edda;color:#155724;}
    .s-inactive{background:#fff3cd;color:#856404;}
    .boy-details{display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:1rem;}
    .detail-item label{font-size:0.65rem;font-weight:700;color:var(--muted);text-transform:uppercase;display:block;margin-bottom:0.1rem;}
    .detail-item span{font-size:0.82rem;color:var(--text);font-weight:500;}
    .boy-actions{display:flex;gap:0.5rem;flex-wrap:wrap;}
    .btn-approve{padding:0.5rem 1.1rem;background:linear-gradient(135deg,#28a745,#1e7e34);color:white;border:none;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:0.82rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .2s;}
    .btn-approve:hover{transform:translateY(-1px);}
    .btn-reject{padding:0.5rem 1.1rem;background:linear-gradient(135deg,#ffc107,#e6a800);color:#1a0f02;border:none;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:0.82rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .2s;}
    .btn-delete{padding:0.5rem 1.1rem;background:linear-gradient(135deg,#dc3545,#b02a37);color:white;border:none;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:0.82rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .2s;}
    .btn-call{padding:0.5rem 0.9rem;background:#e8f5e9;color:#2e7d32;border:1.5px solid #a5d6a7;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:0.82rem;font-weight:700;text-decoration:none;transition:all .2s;}
    .btn-call:hover{background:#2e7d32;color:white;}
    .empty-state{text-align:center;padding:4rem;color:var(--muted);background:var(--white);border-radius:22px;border:1.5px dashed var(--warm);}
    .joined-date{font-size:0.7rem;color:var(--muted);margin-top:0.5rem;text-align:right;}

    @media(max-width:768px){
      nav{height:auto;flex-wrap:wrap;padding:0.6rem 4vw;gap:0.5rem;}
      .logo{font-size:1.3rem;}.nav-right{gap:0.4rem;}.nav-link{padding:0.3rem 0.6rem;font-size:0.72rem;}
      .main{margin-top:110px;padding:1.5rem 4vw;}
      .stats-row{grid-template-columns:repeat(3,1fr);}
      .boys-grid{grid-template-columns:1fr;}
    }
    @media(max-width:400px){.stats-row{grid-template-columns:repeat(2,1fr);}}
  
    /* HAMBURGER */
    .hamburger { display: none; flex-direction: column; gap: 5px; cursor: pointer; background: none; border: none; padding: 6px; z-index: 201; }
    .hamburger span { display: block; width: 24px; height: 2.5px; background: rgba(255,255,255,0.85); border-radius: 4px; transition: all 0.3s; }
    .hamburger.open span:nth-child(1) { transform: translateY(7.5px) rotate(45deg); }
    .hamburger.open span:nth-child(2) { opacity: 0; }
    .hamburger.open span:nth-child(3) { transform: translateY(-7.5px) rotate(-45deg); }
    .nav-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 199; }
    .nav-overlay.open { display: block; }
    @media(max-width:900px){
      .hamburger{display:flex;}
      .nav-right{position:fixed;top:0;right:-280px;width:260px;height:100vh;background:#1a0f02;flex-direction:column;align-items:flex-start;padding:80px 1.5rem 2rem;gap:0.5rem;z-index:200;box-shadow:-8px 0 30px rgba(0,0,0,0.5);transition:right 0.3s;overflow-y:auto;}
      .nav-right.open{right:0;}
      .btn-logout,.nav-link{width:100%;border-radius:10px;padding:0.7rem 1rem;font-size:0.9rem;display:block;text-align:left;}
      .admin-badge,.shop-badge,.admin-name-wrap,.nav-name{display:none;}
    }
    @media(max-width:600px){
      .stats-grid,.stats-row{grid-template-columns:repeat(2,1fr)!important;}
      .main,.content{padding:1rem 3vw!important;margin-top:65px!important;}
      .orders-table-wrap,.table-wrap{overflow-x:auto;}
      table{min-width:500px;}
    }

</style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="logo">Trenzo<span>Kart</span> <span style="font-size:0.75rem;color:var(--accent2);font-weight:400;font-family:'DM Sans',sans-serif;">Admin</span></a>
  <button class="hamburger" id="hamburger" onclick="toggleNav()" aria-label="Menu"><span></span><span></span><span></span></button>
  <div class="nav-right" id="navRight">
    <a href="dashboard.php" class="nav-link">Dashboard</a>
    <a href="vendors.php" class="nav-link">Vendors</a>
    <a href="returns.php" class="nav-link">↩ Returns</a>
    <a href="summary.php" class="nav-link">📊 Summary</a>
    <a href="logout.php" class="nav-link">Logout</a>
  </div>
</nav>

<div class="main">
  <h1 class="page-title">🚚 Delivery Boys</h1>
  <p class="page-sub">Nayi registrations approve karo — active/inactive manage karo</p>

  <?php if (!empty($success)): ?><div class="msg">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

  <!-- Pending alert -->
  <?php if ($counts['inactive'] > 0): ?>
  <div class="pending-alert">
    <div class="pending-dot"></div>
    <div>
      <div class="pending-text">⚠️ <?= $counts['inactive'] ?> Delivery Boy<?= $counts['inactive']>1?'s':'' ?> Approval Pending!</div>
      <div class="pending-sub">Nayi registrations hain — approve ya reject karo</div>
    </div>
  </div>
  <?php endif; ?>

  <!-- STATS -->
  <div class="stats-row">
    <a href="delivery_boys.php?status=all"      class="stat-card <?= $filter==='all'?'active':'' ?>"><div class="stat-num"><?= $counts['all'] ?></div><div class="stat-label">🚚 Total</div></a>
    <a href="delivery_boys.php?status=active"   class="stat-card <?= $filter==='active'?'active':'' ?>"><div class="stat-num" style="color:#28a745;"><?= $counts['active'] ?></div><div class="stat-label">✅ Active</div></a>
    <a href="delivery_boys.php?status=inactive" class="stat-card <?= $filter==='inactive'?'active':'' ?>"><div class="stat-num" style="color:#e65100;"><?= $counts['inactive'] ?></div><div class="stat-label">⏳ Pending</div></a>
  </div>

  <!-- FILTER -->
  <div class="filter-row">
    <a href="delivery_boys.php?status=all"      class="filter-tab <?= $filter==='all'?'active':'' ?>">🚚 All</a>
    <a href="delivery_boys.php?status=inactive" class="filter-tab <?= $filter==='inactive'?'active':'' ?>">⏳ Pending Approval</a>
    <a href="delivery_boys.php?status=active"   class="filter-tab <?= $filter==='active'?'active':'' ?>">✅ Active</a>
  </div>

  <?php if (empty($boys)): ?>
    <div class="empty-state"><span style="font-size:3.5rem;display:block;margin-bottom:1rem;">🚚</span><p>Koi delivery boy nahi!</p></div>
  <?php else: ?>
  <div class="boys-grid">
    <?php foreach ($boys as $b): ?>
    <div class="boy-card <?= $b['status']==='inactive'?'pending':'' ?>">
      <div class="boy-strip strip-<?= $b['status'] ?>"></div>
      <div class="boy-body">
        <div class="boy-top">
          <div class="boy-avatar"><?= strtoupper(substr($b['name'],0,1)) ?></div>
          <div>
            <div class="boy-name"><?= htmlspecialchars($b['name']) ?></div>
            <div class="boy-company"><?= htmlspecialchars($b['company']??'Company not specified') ?></div>
          </div>
          <span class="status-badge s-<?= $b['status'] ?>"><?= $b['status']==='active'?'✅ Active':'⏳ Pending' ?></span>
        </div>

        <div class="boy-details">
          <div class="detail-item"><label>Phone</label><span><?= htmlspecialchars($b['phone']) ?></span></div>
          <div class="detail-item"><label>Email</label><span><?= htmlspecialchars($b['email']??'—') ?></span></div>
          <div class="detail-item"><label>Vehicle No.</label><span><?= htmlspecialchars($b['vehicle_no']??'—') ?></span></div>
          <div class="detail-item"><label>Company</label><span><?= htmlspecialchars($b['company']??'—') ?></span></div>
        </div>

        <div class="boy-actions">
          <?php if ($b['status'] === 'inactive'): ?>
            <a href="delivery_boys.php?approve=<?= $b['id'] ?>" class="btn-approve" onclick="return confirm('Approve karna hai?')">✅ Approve</a>
            <a href="delivery_boys.php?delete=<?= $b['id'] ?>"  class="btn-delete"  onclick="return confirm('Delete karna hai?')">🗑️ Delete</a>
          <?php else: ?>
            <a href="delivery_boys.php?reject=<?= $b['id'] ?>"  class="btn-reject"  onclick="return confirm('Deactivate karna hai?')">⏸️ Deactivate</a>
            <a href="delivery_boys.php?delete=<?= $b['id'] ?>"  class="btn-delete"  onclick="return confirm('Delete karna hai?')">🗑️ Delete</a>
          <?php endif; ?>
          <a href="tel:<?= preg_replace('/\D/','',$b['phone']) ?>" class="btn-call">📞 Call</a>
        </div>
        <div class="joined-date">📅 Joined: <?= date('d M Y', strtotime($b['created_at'])) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

  <script>
  function toggleNav() {
    var nr = document.getElementById('navRight');
    var h = document.getElementById('hamburger');
    var ov = document.getElementById('navOverlay');
    if(nr) nr.classList.toggle('open');
    if(h) h.classList.toggle('open');
    if(ov) ov.classList.toggle('open');
    document.body.style.overflow = nr && nr.classList.contains('open') ? 'hidden' : '';
  }
  function closeNav() {
    var nr = document.getElementById('navRight');
    var h = document.getElementById('hamburger');
    var ov = document.getElementById('navOverlay');
    if(nr) nr.classList.remove('open');
    if(h) h.classList.remove('open');
    if(ov) ov.classList.remove('open');
    document.body.style.overflow = '';
  }
  </script>
</body>
</html>