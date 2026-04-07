<?php
require '../config.php';
if (!isset($_SESSION['delivery_boy_id'])) { header("Location: ../delivery_login.php"); exit(); }
$db_id   = $_SESSION['delivery_boy_id'];
$db_name = $_SESSION['delivery_boy_name'];
$success = '';
$error   = '';

$db_profile = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM delivery_boys WHERE id='$db_id'"));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_pickup'])) {
    $order_id = intval($_POST['order_id']);
    $order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM orders WHERE id='$order_id'"));
    if (!$order) { echo json_encode(['status'=>'error','msg'=>'Order not found!']); exit(); }
    if ($order['pickup_confirmed']) { echo json_encode(['status'=>'error','msg'=>'Already picked up!']); exit(); }
    mysqli_query($conn, "UPDATE orders SET pickup_confirmed=1, pickup_at=NOW(), status='shipped', shipped_at=NOW(), delivery_boy_id='$db_id' WHERE id='$order_id'");
    mysqli_query($conn, "INSERT INTO order_tracking (order_id, status, location, description, updated_by, updated_by_id) VALUES ('$order_id', 'Picked Up', 'Delivery Boy', 'Package picked up by delivery boy — ".mysqli_real_escape_string($conn,$db_name)."', 'delivery_boy', '$db_id')");
    echo json_encode(['status'=>'success','msg'=>'Pickup confirmed for Order #'.str_pad($order_id,5,'0',STR_PAD_LEFT).'!','order_id'=>$order_id]); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tracking'])) {
    $order_id    = intval($_POST['order_id']);
    $location    = mysqli_real_escape_string($conn, trim($_POST['location']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $status      = mysqli_real_escape_string($conn, trim($_POST['tracking_status']));
    if ($status === 'Custom') {
        $custom_sel = trim($_POST['custom_status_select'] ?? '');
        $custom_txt = trim($_POST['custom_text'] ?? '');
        $status = mysqli_real_escape_string($conn, $custom_sel === 'other' ? $custom_txt : $custom_sel);
    }
    if ($status === 'Delivery Completed') { $_POST['mark_delivered'] = 1; }
    $is_delivered = isset($_POST['mark_delivered']) ? 1 : 0;
    if (empty($location)) { $error = "Location required!"; }
    else {
        $order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM orders WHERE id='$order_id'"));
        if (!$order) { $error = "Order not found!"; }
        else {
            mysqli_query($conn, "INSERT INTO order_tracking (order_id, status, location, description, updated_by, updated_by_id) VALUES ('$order_id', '$status', '$location', '$description', 'delivery_boy', '$db_id')");
            if ($is_delivered) {
                mysqli_query($conn, "UPDATE orders SET status='delivered', delivered_at=NOW() WHERE id='$order_id'");
                mysqli_query($conn, "INSERT INTO order_tracking (order_id, status, location, description, updated_by, updated_by_id) VALUES ('$order_id', 'Delivered', '$location', 'Package successfully delivered ✅', 'delivery_boy', '$db_id')");
            }
            $success = "Tracking updated for Order #$order_id!";
        }
    }
}

$orders_result = mysqli_query($conn, "
    SELECT o.*, u.name as customer_name, u.phone as customer_phone,
           o.full_name, o.phone as order_phone, o.address,
           GROUP_CONCAT(p.name SEPARATOR ', ') as product_name
    FROM orders o JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE o.delivery_boy_id='$db_id' AND o.status NOT IN ('cancelled')
    GROUP BY o.id ORDER BY o.id DESC
");
$orders = [];
while ($row = mysqli_fetch_assoc($orders_result)) { $orders[] = $row; }
$total_orders    = count($orders);
$delivered_count = count(array_filter($orders, fn($o) => $o['status'] === 'delivered'));
$pending_count   = $total_orders - $delivered_count;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — Delivery Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{--cream:#f5efe6;--warm:#e8d5b7;--brown:#5c3d1e;--accent:#d4622a;--accent2:#e8a045;--text:#2d1a0a;--muted:#8a6a4a;--white:#fffdf8;--dark:#1a0f02;--green:#28a745;}
    body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--text);overflow-x:hidden;}

    /* NAV */
    nav{position:fixed;top:0;left:0;right:0;z-index:100;background:var(--dark);display:flex;align-items:center;justify-content:space-between;padding:0 5vw;height:64px;box-shadow:0 4px 20px rgba(0,0,0,0.35);}
    .logo{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:900;color:var(--white);text-decoration:none;flex-shrink:0;}
    .logo span{color:var(--accent2);}
    .nav-right{display:flex;gap:0.8rem;align-items:center;}
    .nav-name{color:rgba(255,255,255,0.8);font-size:0.85rem;font-weight:600;white-space:nowrap;}
    .nav-link{padding:0.42rem 1rem;border:1.5px solid rgba(255,255,255,0.22);border-radius:50px;color:rgba(255,255,255,0.75);font-size:0.82rem;font-weight:600;text-decoration:none;transition:all .2s;white-space:nowrap;}
    .nav-link:hover{border-color:var(--accent2);color:var(--accent2);}
    .nav-profile-btn{width:36px;height:36px;border-radius:50%;background:var(--accent2);color:var(--dark);font-weight:900;font-size:1rem;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s;}
    .nav-profile-btn:hover{transform:scale(1.08);}

    /* HAMBURGER */
    .hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;background:none;border:none;padding:6px;flex-shrink:0;}
    .hamburger span{display:block;width:24px;height:2.5px;background:rgba(255,255,255,0.85);border-radius:4px;transition:all .3s;}
    .hamburger.open span:nth-child(1){transform:translateY(7.5px) rotate(45deg);}
    .hamburger.open span:nth-child(2){opacity:0;transform:scaleX(0);}
    .hamburger.open span:nth-child(3){transform:translateY(-7.5px) rotate(-45deg);}

    /* OVERLAY — only covers area LEFT of sidebar, NOT the sidebar itself */
    .nav-overlay{
      display:none;
      position:fixed;
      top:0; left:0; bottom:0;
      right:265px; /* sidebar width = 260px + small gap */
      background:rgba(0,0,0,0.5);
      z-index:500;
      cursor:pointer;
    }
    .nav-overlay.open{display:block;}

    /* ── PROFILE MODAL ── */
    .profile-overlay {
      visibility:hidden; opacity:0;
      position:fixed; inset:0;
      background:rgba(0,0,0,0.55);
      z-index:900;              /* nav(100) + sidebar(600) + overlay(500) sab se upar */
      display:flex;             /* hamesha flex — sirf visibility toggle karenge */
      align-items:center;
      justify-content:center;
      padding:1rem;
      transition:opacity .25s, visibility .25s;
    }
    .profile-overlay.open {
      visibility:visible;
      opacity:1;
    }
    .profile-modal {
      background:var(--white); border-radius:22px; padding:2rem;
      width:100%; max-width:430px;
      box-shadow:0 24px 64px rgba(0,0,0,0.28);
      position:relative;
      max-height:90vh; overflow-y:auto;
      transform:translateY(30px);
      transition:transform .3s ease;
    }
    .profile-overlay.open .profile-modal { transform:translateY(0); }
    @keyframes modalIn { from{transform:translateY(30px);opacity:0} to{transform:translateY(0);opacity:1} }
    .profile-close {
      position:absolute; top:1rem; right:1rem; background:var(--warm); border:none;
      border-radius:50%; width:34px; height:34px; cursor:pointer; font-size:1.1rem;
      display:flex; align-items:center; justify-content:center; color:var(--brown);
      transition:all .2s;
    }
    .profile-close:hover { background:var(--accent); color:white; }
    .profile-avatar {
      width:76px; height:76px; border-radius:50%;
      background:linear-gradient(135deg,var(--accent),var(--accent2));
      display:flex; align-items:center; justify-content:center; font-size:2.2rem;
      font-weight:900; color:white; margin:0 auto 1rem; font-family:'Playfair Display',serif;
      box-shadow:0 6px 20px rgba(212,98,42,0.35);
    }
    .profile-name { font-family:'Playfair Display',serif; font-size:1.4rem; color:var(--brown); text-align:center; margin-bottom:0.2rem; font-weight:700; }
    .profile-role { text-align:center; font-size:0.8rem; color:var(--muted); margin-bottom:1.3rem; }
    .profile-info-grid { display:grid; grid-template-columns:1fr 1fr; gap:0.8rem; }
    .profile-info-item { background:var(--cream); border-radius:12px; padding:0.8rem 1rem; }
    .profile-info-item label { font-size:0.68rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.4px; display:block; margin-bottom:0.25rem; }
    .profile-info-item span { font-size:0.88rem; font-weight:600; color:var(--text); word-break:break-all; }
    .profile-info-item.full { grid-column:span 2; }
    .profile-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:0.6rem; margin-top:1rem; }
    .p-stat { background:var(--dark); border-radius:12px; padding:0.8rem; text-align:center; }
    .p-stat-num { font-family:'Playfair Display',serif; font-size:1.3rem; font-weight:700; color:var(--accent2); }
    .p-stat-label { font-size:0.7rem; color:rgba(255,255,255,0.5); margin-top:0.1rem; }
    .profile-logout {
      width:100%; margin-top:1.2rem; padding:0.75rem; background:none;
      border:1.5px solid #e53e3e; border-radius:10px; color:#e53e3e;
      font-family:'DM Sans',sans-serif; font-size:0.88rem; font-weight:700;
      cursor:pointer; transition:all .2s;
    }
    .profile-logout:hover { background:#e53e3e; color:white; }
    /* MAIN */
    .main{margin-top:64px;padding:2rem 5vw;}
    .page-title{font-family:'Playfair Display',serif;font-size:1.75rem;color:var(--brown);margin-bottom:0.2rem;}
    .page-sub{color:var(--muted);font-size:0.85rem;margin-bottom:1.5rem;}
    .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.8rem;}
    .stat-card{background:var(--white);border-radius:16px;padding:1.2rem 1rem;text-align:center;box-shadow:0 2px 12px rgba(92,61,30,0.07);border:1.5px solid var(--warm);}
    .stat-num{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:700;color:var(--brown);}
    .stat-label{font-size:0.75rem;color:var(--muted);font-weight:600;margin-top:0.2rem;}
    .msg{padding:0.8rem 1.2rem;border-radius:12px;font-size:0.87rem;margin-bottom:1.2rem;font-weight:500;}
    .msg.success{background:#d4edda;color:#155724;border:1.5px solid #b8dfc4;}
    .msg.error{background:#f8d7da;color:#721c24;border:1.5px solid #f5b8bc;}

    /* SCAN SECTION */
    .scan-section{background:var(--dark);border-radius:20px;padding:1.5rem;margin-bottom:2rem;color:white;}
    .scan-title{font-family:'Playfair Display',serif;font-size:1.15rem;color:var(--accent2);margin-bottom:0.25rem;}
    .scan-sub{font-size:0.76rem;color:rgba(255,255,255,0.45);margin-bottom:1rem;}
    .scan-tabs{display:flex;gap:0.5rem;margin-bottom:1rem;}
    .scan-tab{flex:1;padding:0.55rem;border-radius:10px;text-align:center;font-size:0.82rem;font-weight:700;cursor:pointer;border:1.5px solid rgba(255,255,255,0.18);background:rgba(255,255,255,0.05);color:rgba(255,255,255,0.55);transition:all .2s;}
    .scan-tab.active{background:var(--accent);border-color:var(--accent);color:white;}
    .qr-box{width:100%;max-width:300px;margin:0 auto;position:relative;}
    #qr-video{width:100%;border-radius:12px;display:block;border:2px solid var(--accent2);}
    .qr-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;}
    .qr-frame{width:55%;height:55%;border:3px solid var(--accent2);border-radius:8px;box-shadow:0 0 0 1000px rgba(0,0,0,0.4);}
    .scan-line{position:absolute;width:55%;height:2px;background:var(--accent2);animation:scanAnim 2s linear infinite;opacity:0.8;}
    @keyframes scanAnim{0%{top:22%}100%{top:78%}}
    .qr-start-btn{width:100%;padding:0.75rem;background:var(--accent2);color:var(--dark);border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.92rem;font-weight:700;cursor:pointer;margin-bottom:0.7rem;}
    .qr-stop-btn{width:100%;padding:0.55rem;background:rgba(255,255,255,0.08);color:white;border:1px solid rgba(255,255,255,0.18);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.83rem;font-weight:600;cursor:pointer;display:none;}
    .manual-box{display:none;}
    .manual-input-wrap{display:flex;gap:0.5rem;}
    .manual-input{flex:1;padding:0.7rem 1rem;border:1.5px solid rgba(255,255,255,0.18);border-radius:10px;background:rgba(255,255,255,0.07);color:white;font-family:'DM Sans',sans-serif;font-size:0.92rem;outline:none;}
    .manual-input::placeholder{color:rgba(255,255,255,0.28);}
    .manual-input:focus{border-color:var(--accent2);}
    .manual-btn{padding:0.7rem 1.1rem;background:var(--accent);color:white;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.88rem;font-weight:700;cursor:pointer;}
    .scan-result{margin-top:1rem;padding:1rem;border-radius:12px;display:none;}
    .scan-result.success{background:rgba(40,167,69,0.15);border:1.5px solid rgba(40,167,69,0.35);}
    .scan-result.error{background:rgba(220,53,69,0.15);border:1.5px solid rgba(220,53,69,0.35);}
    .scan-result.loading{background:rgba(255,255,255,0.04);border:1.5px solid rgba(255,255,255,0.1);}
    .result-title{font-weight:700;font-size:0.95rem;margin-bottom:0.25rem;}
    .result-sub{font-size:0.8rem;opacity:0.65;}
    .result-ticker{display:flex;align-items:center;gap:0.5rem;margin-top:0.5rem;}
    .ticker-dot{width:8px;height:8px;border-radius:50%;background:var(--green);animation:pulse 1s infinite;}
    @keyframes pulse{0%,100%{opacity:1}50%{opacity:0.3}}
    .ticker-text{font-size:0.74rem;color:rgba(255,255,255,0.55);}

    /* ORDER CARDS */
    .orders-list{display:flex;flex-direction:column;gap:1.2rem;}
    .order-card{background:var(--white);border-radius:20px;overflow:hidden;box-shadow:0 3px 16px rgba(92,61,30,0.07);border:1.5px solid var(--warm);}
    .order-card.pickup-done{border-color:#28a745;}
    .order-strip{height:5px;}
    .strip-dispatched{background:linear-gradient(90deg,var(--accent2),#f0b429);}
    .strip-shipped{background:linear-gradient(90deg,#6f42c1,#9b6ee8);}
    .strip-delivered{background:linear-gradient(90deg,#28a745,#48c774);}
    .order-top{padding:1.1rem 1.4rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.7rem;border-bottom:1px solid var(--warm);}
    .order-id{font-family:'Playfair Display',serif;font-size:1.05rem;font-weight:700;color:var(--brown);}
    .badges{display:flex;gap:0.4rem;flex-wrap:wrap;align-items:center;}
    .status-badge{padding:0.28rem 0.85rem;border-radius:50px;font-size:0.73rem;font-weight:700;}
    .s-shipped{background:#e9d8fd;color:#44337a;}
    .s-dispatched{background:#fff0d6;color:#854d00;}
    .s-delivered{background:#d4edda;color:#155724;}
    .pickup-badge{background:#d4edda;color:#155724;padding:0.24rem 0.68rem;border-radius:50px;font-size:0.7rem;font-weight:700;border:1px solid #b8dfc4;}
    .customer-section{padding:1.1rem 1.4rem;display:grid;grid-template-columns:1fr 1fr;gap:0.9rem;border-bottom:1px solid var(--warm);background:#fafafa;}
    .info-item label{font-size:0.68rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.3px;display:block;margin-bottom:0.18rem;}
    .info-item span{font-size:0.86rem;color:var(--text);font-weight:600;}
    .info-item.full{grid-column:span 2;}
    .call-btn{display:inline-flex;align-items:center;gap:0.35rem;padding:0.32rem 0.85rem;background:var(--green);color:white;border-radius:50px;font-size:0.78rem;font-weight:700;text-decoration:none;margin-top:0.35rem;}
    .tracking-section{padding:1rem 1.4rem;border-bottom:1px solid var(--warm);}
    .tracking-title{font-size:0.78rem;font-weight:700;color:var(--brown);margin-bottom:0.65rem;}
    .tracking-timeline{display:flex;flex-direction:column;gap:0.45rem;}
    .tracking-step{display:flex;gap:0.75rem;align-items:flex-start;}
    .t-dot{width:9px;height:9px;border-radius:50%;background:var(--accent);flex-shrink:0;margin-top:0.3rem;}
    .t-dot.delivered{background:var(--green);}
    .t-dot.pickup{background:#6f42c1;}
    .t-content{flex:1;}
    .t-location{font-weight:700;font-size:0.83rem;color:var(--text);}
    .t-desc{font-size:0.73rem;color:var(--muted);}
    .t-time{font-size:0.68rem;color:var(--muted);}
    .update-section{padding:1.1rem 1.4rem;background:var(--cream);}
    .update-title{font-size:0.8rem;font-weight:700;color:var(--brown);margin-bottom:0.75rem;}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;}
    .form-group{margin-bottom:0.75rem;}
    .form-group label{display:block;font-size:0.76rem;font-weight:600;color:var(--brown);margin-bottom:0.28rem;}
    .form-group input,.form-group select{width:100%;padding:0.62rem 0.88rem;border:1.5px solid var(--warm);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.86rem;color:var(--text);background:var(--white);outline:none;transition:all .2s;}
    .form-group input:focus,.form-group select:focus{border-color:var(--accent);}
    .deliver-check{display:flex;align-items:center;gap:0.55rem;padding:0.65rem 0.9rem;background:#d4edda;border-radius:10px;border:1.5px solid #b8dfc4;cursor:pointer;margin-bottom:0.75rem;}
    .deliver-check input{width:auto;cursor:pointer;}
    .deliver-check label{color:#155724;font-weight:700;font-size:0.86rem;cursor:pointer;margin:0;}
    .btn-update{width:100%;padding:0.78rem;background:var(--accent);color:white;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.9rem;font-weight:700;cursor:pointer;transition:all .2s;}
    .btn-update:hover{background:#c0551f;transform:translateY(-1px);}
    .empty-state{text-align:center;padding:4rem 2rem;color:var(--muted);background:var(--white);border-radius:22px;border:1.5px dashed var(--warm);}
    .toast{position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%) translateY(100px);background:var(--dark);color:white;padding:0.85rem 1.7rem;border-radius:50px;font-size:0.88rem;font-weight:700;box-shadow:0 8px 28px rgba(0,0,0,0.3);transition:all .4s;z-index:9999;opacity:0;white-space:nowrap;}
    .toast.show{transform:translateX(-50%) translateY(0);opacity:1;}
    .toast.success-toast{background:#155724;border:2px solid #28a745;}
    .toast.error-toast{background:#721c24;border:2px solid #dc3545;}

    /* RESPONSIVE */
    @media(max-width:900px){
      .hamburger{display:flex;}
      /* Sidebar */
      .nav-right{
        position:fixed;top:0;right:-270px;width:260px;height:100vh;
        background:#1a0f02;flex-direction:column;align-items:stretch;
        padding:75px 1.4rem 2rem;gap:0.5rem;
        z-index:600; /* overlay(500) se upar */
        box-shadow:-8px 0 30px rgba(0,0,0,0.5);
        transition:right .3s ease;overflow-y:auto;
      }
      .nav-right.open{right:0;}
      .nav-name{font-size:0.92rem;padding:0.5rem 0;border-bottom:1px solid rgba(255,255,255,0.1);width:100%;}
      .nav-link{width:100%;border-radius:10px;padding:0.75rem 1rem;font-size:0.9rem;display:block;text-align:left;}
      /* Mobile sidebar mein profile btn — avatar + text */
      .nav-profile-btn{
        width:100%;border-radius:10px;height:46px;
        justify-content:flex-start;padding-left:1rem;
        gap:0.6rem;font-size:0.9rem;
        background:rgba(232,160,69,0.15);
        border:1.5px solid rgba(232,160,69,0.3);
        color:var(--accent2);
      }
      .nav-profile-btn::after { content: ' My Profile'; font-size:0.88rem; font-weight:600; }
    }
    @media(max-width:680px){
      .stats-row{grid-template-columns:repeat(3,1fr);gap:0.6rem;}
      .stat-card{padding:0.9rem 0.5rem;}
      .stat-num{font-size:1.5rem;}
      .customer-section{grid-template-columns:1fr;}
      .info-item.full{grid-column:span 1;}
      .form-row{grid-template-columns:1fr;}
      .profile-info-grid{grid-template-columns:1fr;}
      .profile-info-item.full{grid-column:span 1;}
    }
    @media(max-width:480px){
      nav{padding:0 4vw;height:58px;}
      .logo{font-size:1.3rem;}
      .main{margin-top:58px;padding:1.2rem 4vw;}
      .page-title{font-size:1.4rem;}
      .order-top{padding:0.9rem 1rem;}
      .customer-section{padding:0.9rem 1rem;}
      .tracking-section{padding:0.8rem 1rem;}
      .update-section{padding:0.9rem 1rem;}
      /* Overlay adjusts to smaller sidebar */
      .nav-overlay{right:260px;}
    }
    @media(max-width:360px){
      .stats-row{grid-template-columns:1fr 1fr;}
      .nav-right{width:240px;right:-250px;}
      .nav-overlay{right:240px;}
    }
  </style>
</head>
<body>

<!--
  OVERLAY FIX: right: 260px (sidebar width)
  Overlay SIRF left wala area cover karta hai — sidebar ke upar bilkul nahi
  Isliye sidebar ke links directly clickable hain, koi interference nahi
-->
<div class="nav-overlay" id="navOverlay" onclick="closeNav()"></div>

<nav>
  <a href="dashboard.php" class="logo">Trenzo<span>Kart</span></a>
  <!-- Hamburger — sirf HTML onclick, koi addEventListener nahi (double fire prevent) -->
  <button class="hamburger" id="hamburger" onclick="toggleNav()" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
  <div class="nav-right" id="navRight">
    <span class="nav-name">🚚 <?= htmlspecialchars($db_name) ?></span>
    <button class="nav-profile-btn" onclick="openProfile()" title="My Profile">
      <?= strtoupper(substr($db_name,0,1)) ?>
    </button>
    <a href="../delivery_logout.php" class="nav-link">🚪 Logout</a>
  </div>
</nav>

<!-- PROFILE MODAL -->
<div class="profile-overlay" id="profileOverlay" onclick="if(event.target===this)closeProfile()">
  <div class="profile-modal">
    <button class="profile-close" onclick="closeProfile()">✕</button>
    <div class="profile-avatar"><?= strtoupper(substr($db_profile['name'] ?? $db_name, 0, 1)) ?></div>
    <div class="profile-name"><?= htmlspecialchars($db_profile['name'] ?? $db_name) ?></div>
    <div class="profile-role">🚚 Delivery Partner &nbsp;·&nbsp; TrenzoKart</div>
    <div class="profile-info-grid">
      <div class="profile-info-item">
        <label>Delivery Boy ID</label>
        <span>#DB<?= str_pad($db_id,4,'0',STR_PAD_LEFT) ?></span>
      </div>
      <div class="profile-info-item">
        <label>Status</label>
        <span style="color:var(--green);font-weight:700;">● <?= ucfirst($db_profile['status'] ?? 'Active') ?></span>
      </div>
      <div class="profile-info-item">
        <label>Phone</label>
        <span><?= !empty($db_profile['phone']) ? htmlspecialchars($db_profile['phone']) : 'N/A' ?></span>
      </div>
      <div class="profile-info-item">
        <label>Vehicle No.</label>
        <span><?= !empty($db_profile['vehicle_number']) ? htmlspecialchars($db_profile['vehicle_number']) : (!empty($db_profile['vehicle']) ? htmlspecialchars($db_profile['vehicle']) : 'N/A') ?></span>
      </div>
      <div class="profile-info-item full">
        <label>Email</label>
        <span><?= !empty($db_profile['email']) ? htmlspecialchars($db_profile['email']) : 'N/A' ?></span>
      </div>
      <div class="profile-info-item full">
        <label>Area / Zone</label>
        <span><?= !empty($db_profile['area']) ? htmlspecialchars($db_profile['area']) : (!empty($db_profile['zone']) ? htmlspecialchars($db_profile['zone']) : 'N/A') ?></span>
      </div>
      <?php if (!empty($db_profile['created_at'])): ?>
      <div class="profile-info-item full">
        <label>Joined On</label>
        <span><?= date('d M Y', strtotime($db_profile['created_at'])) ?></span>
      </div>
      <?php endif; ?>
    </div>
    <div class="profile-stats">
      <div class="p-stat">
        <div class="p-stat-num"><?= $total_orders ?></div>
        <div class="p-stat-label">Total</div>
      </div>
      <div class="p-stat">
        <div class="p-stat-num" style="color:#48c774;"><?= $delivered_count ?></div>
        <div class="p-stat-label">Delivered</div>
      </div>
      <div class="p-stat">
        <div class="p-stat-num" style="color:var(--accent2);"><?= $pending_count ?></div>
        <div class="p-stat-label">Pending</div>
      </div>
    </div>
    <button class="profile-logout" onclick="window.location.href='../delivery_logout.php'">🚪 Logout</button>
  </div>
</div>

<div class="main">
  <h1 class="page-title">🚚 Delivery Dashboard</h1>
  <p class="page-sub">Orders scan karo ya ID enter karo — pickup confirm karo</p>

  <div class="stats-row">
    <div class="stat-card"><div class="stat-num"><?= $total_orders ?></div><div class="stat-label">Total Orders</div></div>
    <div class="stat-card"><div class="stat-num" style="color:var(--green);"><?= $delivered_count ?></div><div class="stat-label">Delivered</div></div>
    <div class="stat-card"><div class="stat-num" style="color:var(--accent2);"><?= $pending_count ?></div><div class="stat-label">Pending</div></div>
  </div>

  <?php if (!empty($success)): ?><div class="msg success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if (!empty($error)):   ?><div class="msg error">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="scan-section">
    <div class="scan-title">📦 Pickup Confirm Karo</div>
    <div class="scan-sub">QR scan karo ya Order ID manually enter karo</div>
    <div class="scan-tabs">
      <div class="scan-tab active" id="tab-qr" onclick="switchTab('qr')">📷 QR Scan</div>
      <div class="scan-tab" id="tab-manual" onclick="switchTab('manual')">⌨️ Manual ID</div>
    </div>
    <div id="qr-section">
      <button class="qr-start-btn" id="startBtn" onclick="startScan()">📷 Camera Start Karo</button>
      <div class="qr-box" id="qrBox" style="display:none;">
        <video id="qr-video" autoplay playsinline muted></video>
        <div class="qr-overlay"><div class="qr-frame"></div><div class="scan-line"></div></div>
      </div>
      <button class="qr-stop-btn" id="stopBtn" onclick="stopScan()">✕ Camera Band Karo</button>
    </div>
    <div class="manual-box" id="manual-section">
      <div class="manual-input-wrap">
        <input type="number" class="manual-input" id="manualOrderId" placeholder="Order ID enter karo (e.g. 16)" min="1"/>
        <button class="manual-btn" onclick="confirmPickup(document.getElementById('manualOrderId').value)">✅ Confirm</button>
      </div>
    </div>
    <div class="scan-result" id="scanResult">
      <div class="result-title" id="resultTitle"></div>
      <div class="result-sub" id="resultSub"></div>
      <div class="result-ticker" id="resultTicker" style="display:none;">
        <div class="ticker-dot"></div>
        <div class="ticker-text">Sabko update ho raha hai...</div>
      </div>
    </div>
  </div>

  <?php if (empty($orders)): ?>
    <div class="empty-state"><span style="font-size:3.5rem;display:block;margin-bottom:1rem;">📭</span><p>Koi active order nahi!</p></div>
  <?php else: ?>
  <div class="orders-list" id="ordersList">
    <?php foreach ($orders as $o):
      $phone = preg_replace('/\D/', '', $o['order_phone'] ?? $o['customer_phone'] ?? '');
      $name  = $o['full_name'] ?? $o['customer_name'];
      $track_result = mysqli_query($conn, "SELECT * FROM order_tracking WHERE order_id='{$o['id']}' ORDER BY created_at ASC");
      $track_rows = [];
      while ($t = mysqli_fetch_assoc($track_result)) { $track_rows[] = $t; }
    ?>
    <div class="order-card <?= $o['pickup_confirmed'] ? 'pickup-done' : '' ?>" id="order-card-<?= $o['id'] ?>">
      <div class="order-strip strip-<?= $o['status'] ?>"></div>
      <div class="order-top">
        <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;">
          <span class="order-id">#TK<?= str_pad($o['id'],5,'0',STR_PAD_LEFT) ?></span>
          <span class="status-badge s-<?= $o['status'] ?>"><?= strtoupper($o['status']) ?></span>
          <?php if ($o['pickup_confirmed']): ?>
            <span class="pickup-badge" id="pickup-badge-<?= $o['id'] ?>">📦 Pickup Done ✅</span>
          <?php else: ?>
            <span class="pickup-badge" id="pickup-badge-<?= $o['id'] ?>" style="display:none;"></span>
          <?php endif; ?>
        </div>
        <span style="color:var(--muted);font-size:0.8rem;">📅 <?= date('d M Y', strtotime($o['created_at'])) ?></span>
      </div>
      <div class="customer-section">
        <div class="info-item"><label>Customer</label><span><?= htmlspecialchars($name) ?></span></div>
        <div class="info-item"><label>Phone</label><span><?= htmlspecialchars($phone) ?></span><br><a href="tel:<?= $phone ?>" class="call-btn">📞 Call</a></div>
        <div class="info-item full"><label>Address</label><span><?= htmlspecialchars($o['address']??'N/A') ?></span></div>
        <div class="info-item"><label>Product</label><span><?= htmlspecialchars($o['product_name']??'Product') ?></span></div>
        <div class="info-item"><label>Amount</label><span>₹<?= number_format($o['total_amount'],2) ?> <span style="color:var(--accent);font-size:0.72rem;">(<?= strtoupper($o['payment_method']??'COD') ?>)</span></span></div>
      </div>
      <?php if (!empty($track_rows)): ?>
      <div class="tracking-section">
        <div class="tracking-title">📍 Tracking History</div>
        <div class="tracking-timeline" id="tracking-<?= $o['id'] ?>">
          <?php foreach ($track_rows as $t): ?>
          <div class="tracking-step">
            <div class="t-dot <?= $t['status']==='Delivered'?'delivered':($t['status']==='Picked Up'?'pickup':'') ?>"></div>
            <div class="t-content">
              <div class="t-location"><?= htmlspecialchars($t['location']) ?> — <span style="color:var(--accent);font-size:0.72rem;"><?= htmlspecialchars($t['status']) ?></span></div>
              <?php if (!empty($t['description'])): ?><div class="t-desc"><?= htmlspecialchars($t['description']) ?></div><?php endif; ?>
              <div class="t-time">🕐 <?= date('d M Y, h:i A', strtotime($t['created_at'])) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
      <div class="tracking-section" id="tracking-wrap-<?= $o['id'] ?>" style="display:none;">
        <div class="tracking-title">📍 Tracking History</div>
        <div class="tracking-timeline" id="tracking-<?= $o['id'] ?>"></div>
      </div>
      <?php endif; ?>
      <?php if ($o['status'] !== 'delivered'): ?>
      <?php
        $last_track  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM order_tracking WHERE order_id='{$o['id']}' ORDER BY created_at DESC LIMIT 1"));
        $last_status = $last_track['status'] ?? '';
        $next_steps  = [''=> 'In Transit','Picked Up'=>'In Transit','In Transit'=>'At Hub','At Hub'=>'Out for Delivery','Out for Delivery'=>'Delivery Completed','Delivery Attempt Failed'=>'Out for Delivery','Address Not Found'=>'Out for Delivery','Customer Not Available'=>'Out for Delivery','Returned to Hub'=>'Out for Delivery'];
        $suggested_next = $next_steps[$last_status] ?? 'In Transit';
      ?>
      <div class="update-section">
        <div class="update-title">📝 Add Tracking Update</div>
        <?php if ($suggested_next): ?>
        <div style="background:#e8f5e9;border:1.5px solid #a5d6a7;border-radius:10px;padding:0.55rem 0.9rem;margin-bottom:0.75rem;font-size:0.8rem;color:#155724;font-weight:600;">💡 Suggested next: <strong><?= $suggested_next ?></strong></div>
        <?php endif; ?>
        <form method="POST">
          <input type="hidden" name="add_tracking" value="1"/>
          <input type="hidden" name="order_id" value="<?= $o['id'] ?>"/>
          <div class="form-row">
            <div class="form-group"><label>Current Location *</label><input type="text" name="location" placeholder="e.g. Saharanpur Hub" required/></div>
            <div class="form-group">
              <label>Status</label>
              <select name="tracking_status" id="status_<?= $o['id'] ?>" onchange="toggleCustom(<?= $o['id'] ?>, this.value)">
                <option value="In Transit" <?= $suggested_next==='In Transit'?'selected':'' ?>>🚚 In Transit</option>
                <option value="At Hub" <?= $suggested_next==='At Hub'?'selected':'' ?>>🏭 At Hub</option>
                <option value="Out for Delivery" <?= $suggested_next==='Out for Delivery'?'selected':'' ?>>📬 Out for Delivery</option>
                <option value="Delivery Completed" <?= $suggested_next==='Delivery Completed'?'selected':'' ?>>✅ Delivery Completed</option>
                <option value="Custom">⚙️ Custom...</option>
              </select>
            </div>
          </div>
          <div class="form-group" id="custom_wrap_<?= $o['id'] ?>" style="display:none;">
            <label>Custom Status *</label>
            <select name="custom_status_select" id="custom_sel_<?= $o['id'] ?>" onchange="toggleCustomInput(<?= $o['id'] ?>, this.value)">
              <option value="">-- Select --</option>
              <option value="Delivery Attempt Failed">⚠️ Delivery Attempt Failed</option>
              <option value="Address Not Found">📍 Address Not Found</option>
              <option value="Customer Not Available">📵 Customer Not Available</option>
              <option value="Returned to Hub">🔄 Returned to Hub</option>
              <option value="other">✏️ Type your own...</option>
            </select>
            <input type="text" id="custom_input_<?= $o['id'] ?>" name="custom_text" placeholder="Type custom status..." style="margin-top:0.5rem;display:none;width:100%;padding:0.62rem 0.88rem;border:1.5px solid var(--warm);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.86rem;"/>
          </div>
          <div class="form-group"><label>Description (optional)</label><input type="text" name="description" placeholder="e.g. Package at sorting hub"/></div>
          <div class="deliver-check">
            <input type="checkbox" name="mark_delivered" id="del_<?= $o['id'] ?>"/>
            <label for="del_<?= $o['id'] ?>">✅ Mark as Delivered — Final delivery done!</label>
          </div>
          <button type="submit" class="btn-update">📍 Update Tracking →</button>
        </form>
      </div>
      <?php else: ?>
      <div style="padding:1rem 1.4rem;background:#d4edda;text-align:center;"><span style="color:#155724;font-weight:700;">✅ Delivered Successfully!</span></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="toast" id="toast"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jsqr/1.4.0/jsQR.min.js"></script>
<script>
// ════════════════════════════════════════════════
// HAMBURGER SIDEBAR
// FIX: Overlay right:260px — sirf left area cover
// Sidebar z-index:600 > overlay z-index:500
// Koi stopPropagation nahi — links naturally click honge
// ════════════════════════════════════════════════
function toggleNav() {
  var panel = document.getElementById('navRight');
  var ham   = document.getElementById('hamburger');
  var ov    = document.getElementById('navOverlay');
  var isOpen = panel.classList.toggle('open');
  ham.classList.toggle('open');
  ov.classList.toggle('open');
  document.body.style.overflow = isOpen ? 'hidden' : '';
}
function closeNav() {
  document.getElementById('navRight').classList.remove('open');
  document.getElementById('hamburger').classList.remove('open');
  document.getElementById('navOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

// ════════════════════════════════════════════════
// PROFILE MODAL
// ════════════════════════════════════════════════
function openProfile() {
  closeNav(); // mobile sidebar band karo pehle
  document.getElementById('profileOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeProfile() {
  document.getElementById('profileOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

// ════════════════════════════════════════════════
// CUSTOM STATUS
// ════════════════════════════════════════════════
function toggleCustom(oid, val) {
  var wrap = document.getElementById('custom_wrap_' + oid);
  if (wrap) wrap.style.display = val === 'Custom' ? 'block' : 'none';
}
function toggleCustomInput(oid, val) {
  var inp = document.getElementById('custom_input_' + oid);
  if (inp) { inp.style.display = val === 'other' ? 'block' : 'none'; inp.required = val === 'other'; }
}

// ════════════════════════════════════════════════
// QR SCANNER
// ════════════════════════════════════════════════
var stream = null, scanning = false, animFrame = null;

function switchTab(tab) {
  document.getElementById('tab-qr').classList.toggle('active', tab==='qr');
  document.getElementById('tab-manual').classList.toggle('active', tab==='manual');
  document.getElementById('qr-section').style.display     = tab==='qr'     ? 'block' : 'none';
  document.getElementById('manual-section').style.display = tab==='manual' ? 'block' : 'none';
  if (tab !== 'qr') stopScan();
}

async function startScan() {
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    var video = document.getElementById('qr-video');
    video.srcObject = stream;
    await video.play();
    document.getElementById('qrBox').style.display   = 'block';
    document.getElementById('startBtn').style.display = 'none';
    document.getElementById('stopBtn').style.display  = 'block';
    scanning = true;
    scanFrame();
  } catch(e) {
    showResult('error','❌ Camera access nahi mila!','Browser settings mein camera allow karo ya Manual ID use karo');
  }
}
function stopScan() {
  scanning = false;
  if (animFrame) cancelAnimationFrame(animFrame);
  if (stream) { stream.getTracks().forEach(function(t){ t.stop(); }); stream = null; }
  document.getElementById('qrBox').style.display    = 'none';
  document.getElementById('startBtn').style.display  = 'block';
  document.getElementById('stopBtn').style.display   = 'none';
}
function scanFrame() {
  if (!scanning) return;
  var video = document.getElementById('qr-video');
  if (video.readyState === video.HAVE_ENOUGH_DATA) {
    var canvas = document.createElement('canvas');
    canvas.width = video.videoWidth; canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    var imageData = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height);
    var code = jsQR(imageData.data, imageData.width, imageData.height);
    if (code) {
      stopScan();
      var orderId = code.data.replace(/[^0-9]/g, '');
      if (orderId) confirmPickup(parseInt(orderId));
      else showResult('error','❌ Invalid QR!','Yeh TrenzoKart order QR nahi hai');
      return;
    }
  }
  animFrame = requestAnimationFrame(scanFrame);
}

function confirmPickup(orderId) {
  if (!orderId || orderId <= 0) { showResult('error','❌ Invalid Order ID!','Sahi order ID enter karo'); return; }
  showResult('loading','⏳ Processing...','Order verify ho raha hai');
  var fd = new FormData();
  fd.append('confirm_pickup','1'); fd.append('order_id', orderId);
  fetch('dashboard.php', { method:'POST', body:fd })
    .then(function(r){ return r.json(); })
    .then(function(data) {
      if (data.status === 'success') {
        showResult('success','✅ ' + data.msg,'Pickup confirmed! Sabko update ho gaya');
        updateOrderCard(orderId);
        showToast('✅ Pickup Confirmed!','success');
        startRealTimePoll();
      } else {
        showResult('error','❌ ' + data.msg,'Dobara try karo');
        showToast('❌ ' + data.msg,'error');
      }
    })
    .catch(function(){ showResult('error','❌ Network Error!','Internet check karo'); });
}

function showResult(type, title, sub) {
  var box = document.getElementById('scanResult');
  var ticker = document.getElementById('resultTicker');
  box.className = 'scan-result ' + type;
  box.style.display = 'block';
  document.getElementById('resultTitle').textContent = title;
  document.getElementById('resultSub').textContent   = sub;
  ticker.style.display = type === 'success' ? 'flex' : 'none';
}

function updateOrderCard(orderId) {
  var card = document.getElementById('order-card-' + orderId);
  if (!card) return;
  card.classList.add('pickup-done');
  var badge = document.getElementById('pickup-badge-' + orderId);
  if (badge) { badge.style.display='inline-flex'; badge.textContent='📦 Pickup Done ✅'; }
  var timeline = document.getElementById('tracking-' + orderId);
  if (timeline) {
    var now = new Date().toLocaleString('en-IN',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
    var step = document.createElement('div');
    step.className = 'tracking-step';
    step.innerHTML = '<div class="t-dot pickup"></div><div class="t-content"><div class="t-location">Delivery Boy — <span style="color:var(--accent);font-size:0.72rem;">Picked Up</span></div><div class="t-desc">Package picked up by delivery boy</div><div class="t-time">🕐 ' + now + '</div></div>';
    timeline.appendChild(step);
    var wrap = document.getElementById('tracking-wrap-' + orderId);
    if (wrap) wrap.style.display = 'block';
  }
}

function startRealTimePoll() {
  var msgs = ['User ko update ho gaya ✅','Vendor ko update ho gaya ✅','Admin ko update ho gaya ✅','Sab sync ho gaye! 🎉'];
  var count = 0;
  var interval = setInterval(function() {
    var el = document.querySelector('.ticker-text');
    if (el && count < msgs.length) el.textContent = msgs[count];
    count++;
    if (count >= msgs.length) { clearInterval(interval); setTimeout(function(){ document.getElementById('scanResult').style.display='none'; }, 2000); }
  }, 2000);
}

function showToast(msg, type) {
  var t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast show ' + (type==='success' ? 'success-toast' : 'error-toast');
  setTimeout(function(){ t.className = 'toast'; }, 3000);
}

document.getElementById('manualOrderId').addEventListener('keypress', function(e) {
  if (e.key === 'Enter') confirmPickup(this.value);
});
</script>
</body>
</html>