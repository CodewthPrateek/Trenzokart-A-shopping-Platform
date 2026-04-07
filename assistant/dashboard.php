<?php
require '../config.php';
if (!isset($_SESSION['assistant_id'])) { header("Location: ../assistant_login.php"); exit(); }
$ast_id   = $_SESSION['assistant_id'];
$ast_name = $_SESSION['assistant_name'];
$success  = ''; $error = '';

// Handle return approve/reject
if (isset($_GET['action']) && isset($_GET['id'])) {
    $rid    = intval($_GET['id']);
    $action = $_GET['action'] === 'approve' ? 'approved' : 'rejected';
    mysqli_query($conn, "UPDATE return_requests SET status='$action', handled_by='assistant', handler_id='$ast_id' WHERE id='$rid'");
    $success = "Return request " . ($action === 'approved' ? 'approved!' : 'rejected!');
}

$filter = $_GET['status'] ?? 'all';
$where  = "1=1";
if ($filter !== 'all') { $where = "rr.status='" . mysqli_real_escape_string($conn, $filter) . "'"; }

$result = mysqli_query($conn, "
    SELECT rr.*, o.total_amount, o.address,
           u.name as customer_name, u.email as customer_email,
           o.full_name as order_name, o.phone as order_phone
    FROM return_requests rr
    JOIN orders o ON rr.order_id = o.id
    JOIN users u ON rr.user_id = u.id
    WHERE $where
    ORDER BY rr.created_at DESC
");
$returns = [];
while ($row = mysqli_fetch_assoc($result)) { $returns[] = $row; }

$counts = [
    'all'      => count($returns),
    'pending'  => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM return_requests WHERE status='pending'"))['c'],
    'approved' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM return_requests WHERE status='approved'"))['c'],
    'rejected' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM return_requests WHERE status='rejected'"))['c'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — Assistant Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{--cream:#f5efe6;--warm:#e8d5b7;--brown:#5c3d1e;--accent:#d4622a;--accent2:#e8a045;--text:#2d1a0a;--muted:#8a6a4a;--white:#fffdf8;--dark:#1a0f02;}
    body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--text);}
    nav{position:fixed;top:0;left:0;right:0;z-index:100;background:var(--dark);display:flex;align-items:center;justify-content:space-between;padding:0 5vw;height:65px;box-shadow:0 4px 20px rgba(0,0,0,0.3);}
    .logo{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:900;color:var(--white);text-decoration:none;}
    .logo span{color:var(--accent2);}
    .nav-right{display:flex;gap:0.8rem;align-items:center;flex-wrap:wrap;}
    .nav-name{color:rgba(255,255,255,0.8);font-size:0.88rem;font-weight:600;}
    .nav-link{padding:0.45rem 1.1rem;border:1.5px solid rgba(255,255,255,0.25);border-radius:50px;color:rgba(255,255,255,0.7);font-size:0.82rem;font-weight:600;text-decoration:none;transition:all .2s;}
    .nav-link:hover{border-color:var(--accent2);color:var(--accent2);}
    .main{margin-top:85px;padding:2.5rem 5vw;}
    .page-title{font-family:'Playfair Display',serif;font-size:2rem;color:var(--brown);margin-bottom:0.3rem;}
    .page-sub{color:var(--muted);font-size:0.9rem;margin-bottom:2rem;}
    .msg{padding:0.85rem 1.2rem;border-radius:12px;font-size:0.88rem;margin-bottom:1.5rem;font-weight:500;}
    .msg.success{background:#d4edda;color:#155724;border:1.5px solid #b8dfc4;}
    .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;}
    .stat-card{background:var(--white);border-radius:16px;padding:1.2rem;text-align:center;box-shadow:0 2px 12px rgba(92,61,30,0.07);}
    .stat-num{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:900;color:var(--brown);}
    .stat-label{font-size:0.75rem;color:var(--muted);font-weight:600;margin-top:0.2rem;}
    .filter-row{display:flex;gap:0.5rem;margin-bottom:1.5rem;flex-wrap:wrap;}
    .filter-tab{padding:0.45rem 1.1rem;border-radius:50px;font-size:0.82rem;font-weight:600;cursor:pointer;border:1.5px solid var(--warm);background:var(--white);color:var(--muted);text-decoration:none;transition:all .2s;}
    .filter-tab.active,.filter-tab:hover{background:var(--accent);border-color:var(--accent);color:white;}
    .return-card{background:var(--white);border-radius:20px;padding:1.5rem;margin-bottom:1rem;box-shadow:0 3px 18px rgba(92,61,30,0.07);border:1.5px solid var(--warm);}
    .return-top{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.8rem;margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid var(--warm);}
    .return-order-id{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;color:var(--brown);}
    .return-date{font-size:0.78rem;color:var(--muted);margin-top:0.2rem;}
    .status-badge{padding:0.3rem 0.9rem;border-radius:50px;font-size:0.78rem;font-weight:700;}
    .s-pending{background:#fff3e0;color:#e65100;}
    .s-approved{background:#d4edda;color:#155724;}
    .s-rejected{background:#f8d7da;color:#721c24;}
    .return-body{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;}
    .info-item label{font-size:0.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.3px;display:block;margin-bottom:0.2rem;}
    .info-item span{font-size:0.9rem;color:var(--text);font-weight:500;}
    .reason-box{background:#fff3e0;border:1.5px solid #ffcc80;border-radius:10px;padding:0.8rem 1rem;margin-bottom:1rem;}
    .reason-label{font-size:0.72rem;font-weight:700;color:#e65100;text-transform:uppercase;margin-bottom:0.3rem;}
    .reason-text{font-size:0.9rem;color:var(--text);font-weight:600;}
    .desc-text{font-size:0.82rem;color:var(--muted);margin-top:0.2rem;}
    .action-row{display:flex;gap:0.8rem;flex-wrap:wrap;}
    .btn-approve{padding:0.55rem 1.4rem;background:linear-gradient(135deg,#28a745,#1e7e34);color:white;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.88rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .2s;}
    .btn-approve:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(40,167,69,0.4);}
    .btn-reject{padding:0.55rem 1.4rem;background:linear-gradient(135deg,#dc3545,#b02a37);color:white;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.88rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .2s;}
    .btn-reject:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(220,53,69,0.4);}
    .btn-call{padding:0.55rem 1.1rem;background:#e8f5e9;color:#2e7d32;border:1.5px solid #a5d6a7;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.88rem;font-weight:700;text-decoration:none;transition:all .2s;}
    .btn-call:hover{background:#2e7d32;color:white;}
    .empty-state{text-align:center;padding:4rem 2rem;color:var(--muted);background:var(--white);border-radius:22px;border:1.5px dashed var(--warm);}
    .empty-state span{font-size:3.5rem;display:block;margin-bottom:1rem;}

    /* RESPONSIVE */
    @media(max-width:600px){
      nav{height:auto;flex-wrap:wrap;padding:0.6rem 4vw;gap:0.5rem;}
      .logo{font-size:1.3rem;}
      .nav-right{gap:0.4rem;}
      .nav-name{font-size:0.78rem;}
      .nav-link{padding:0.35rem 0.7rem;font-size:0.75rem;}
      .main{margin-top:110px;padding:1.5rem 4vw;}
      .page-title{font-size:1.5rem;}
      .stats-row{grid-template-columns:repeat(2,1fr);gap:0.7rem;}
      .stat-card{padding:1rem;}
      .stat-num{font-size:1.4rem;}
      .return-body{grid-template-columns:1fr;}
      .return-card{padding:1.2rem;}
      .action-row{gap:0.5rem;}
      .btn-approve,.btn-reject,.btn-call{padding:0.5rem 1rem;font-size:0.82rem;}
    }
  
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
  <a href="dashboard.php" class="logo">Trenzo<span>Kart</span></a>
  <button class="hamburger" id="hamburger" onclick="toggleNav()" aria-label="Menu"><span></span><span></span><span></span></button>
  <div class="nav-right" id="navRight">
    <span class="nav-name">🤝 <?= htmlspecialchars($ast_name) ?></span>
    <span style="color:rgba(255,255,255,0.4);font-size:0.75rem;"><?= htmlspecialchars($_SESSION['assistant_sid']) ?></span>
    <a href="../assistant_logout.php" class="nav-link">Logout</a>
  </div>
</nav>

<div class="main">
  <h1 class="page-title">↩ Return Requests</h1>
  <p class="page-sub">Approve or reject customer return requests</p>

  <?php if (!empty($success)): ?><div class="msg success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

  <div class="stats-row">
    <div class="stat-card"><div class="stat-num"><?= $counts['all'] ?></div><div class="stat-label">Total</div></div>
    <div class="stat-card"><div class="stat-num" style="color:#e65100;"><?= $counts['pending'] ?></div><div class="stat-label">⏳ Pending</div></div>
    <div class="stat-card"><div class="stat-num" style="color:#28a745;"><?= $counts['approved'] ?></div><div class="stat-label">✅ Approved</div></div>
    <div class="stat-card"><div class="stat-num" style="color:#dc3545;"><?= $counts['rejected'] ?></div><div class="stat-label">❌ Rejected</div></div>
  </div>

  <div class="filter-row">
    <a href="dashboard.php?status=all"      class="filter-tab <?= $filter==='all'?'active':'' ?>">All</a>
    <a href="dashboard.php?status=pending"  class="filter-tab <?= $filter==='pending'?'active':'' ?>">⏳ Pending</a>
    <a href="dashboard.php?status=approved" class="filter-tab <?= $filter==='approved'?'active':'' ?>">✅ Approved</a>
    <a href="dashboard.php?status=rejected" class="filter-tab <?= $filter==='rejected'?'active':'' ?>">❌ Rejected</a>
  </div>

  <?php if (empty($returns)): ?>
    <div class="empty-state"><span>↩</span><p>No return requests found!</p></div>
  <?php else: ?>
    <?php foreach ($returns as $r):
      $cname  = $r['order_name'] ?? $r['customer_name'];
      $cphone = preg_replace('/\D/', '', $r['order_phone'] ?? '');
    ?>
    <div class="return-card">
      <div class="return-top">
        <div>
          <div class="return-order-id">#TK<?= str_pad($r['order_id'],5,'0',STR_PAD_LEFT) ?></div>
          <div class="return-date">📅 <?= date('d M Y, h:i A', strtotime($r['created_at'])) ?></div>
        </div>
        <span class="status-badge s-<?= $r['status'] ?>"><?= strtoupper($r['status']) ?></span>
      </div>
      <div class="return-body">
        <div class="info-item"><label>Customer</label><span><?= htmlspecialchars($cname) ?></span></div>
        <div class="info-item"><label>Phone</label><span><?= htmlspecialchars($cphone ?: 'N/A') ?></span></div>
        <div class="info-item"><label>Amount</label><span>₹<?= number_format($r['total_amount'],2) ?></span></div>
        <div class="info-item"><label>Address</label><span style="font-size:0.82rem;"><?= htmlspecialchars($r['address']??'N/A') ?></span></div>
      </div>
      <div class="reason-box">
        <div class="reason-label">Return Reason</div>
        <div class="reason-text"><?= htmlspecialchars($r['reason']) ?></div>
        <?php if (!empty($r['description'])): ?><div class="desc-text"><?= htmlspecialchars($r['description']) ?></div><?php endif; ?>
      </div>
      <?php if ($r['status'] === 'pending'): ?>
      <div class="action-row">
        <a href="dashboard.php?action=approve&id=<?= $r['id'] ?>" class="btn-approve" onclick="return confirm('Approve return?')">✅ Approve</a>
        <a href="dashboard.php?action=reject&id=<?= $r['id'] ?>"  class="btn-reject"  onclick="return confirm('Reject return?')">❌ Reject</a>
        <?php if ($cphone): ?><a href="tel:<?= $cphone ?>" class="btn-call">📞 Call</a><?php endif; ?>
      </div>
      <?php else: ?>
        <div style="font-size:0.85rem;font-weight:600;color:<?= $r['status']==='approved'?'#155724':'#721c24' ?>;">
          <?= $r['status']==='approved' ? '✅ Return Approved' : '❌ Return Rejected' ?>
          <?php if (!empty($r['updated_at'])): ?>
            <span style="color:var(--muted);font-weight:400;margin-left:0.5rem;">on <?= date('d M Y', strtotime($r['updated_at'])) ?></span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
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