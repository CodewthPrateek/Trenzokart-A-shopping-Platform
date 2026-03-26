<?php
require '../config.php';
if (!isset($_SESSION['delivery_boy_id'])) { header("Location: ../delivery_login.php"); exit(); }
$db_id   = $_SESSION['delivery_boy_id'];
$db_name = $_SESSION['delivery_boy_name'];
$success = '';
$error   = '';

// PICKUP CONFIRM via scan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_pickup'])) {
    $order_id = intval($_POST['order_id']);
    $order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM orders WHERE id='$order_id'"));
    if (!$order) {
        echo json_encode(['status'=>'error','msg'=>'Order not found!']); exit();
    }
    if ($order['pickup_confirmed']) {
        echo json_encode(['status'=>'error','msg'=>'Already picked up!']); exit();
    }
    mysqli_query($conn, "UPDATE orders SET pickup_confirmed=1, pickup_at=NOW(), status='shipped', shipped_at=NOW(), delivery_boy_id='$db_id' WHERE id='$order_id'");
    mysqli_query($conn, "INSERT INTO order_tracking (order_id, status, location, description, updated_by, updated_by_id)
        VALUES ('$order_id', 'Picked Up', 'Delivery Boy', 'Package picked up by delivery boy — ".mysqli_real_escape_string($conn,$db_name)."', 'delivery_boy', '$db_id')");
    echo json_encode(['status'=>'success','msg'=>'Pickup confirmed for Order #'.str_pad($order_id,5,'0',STR_PAD_LEFT).'!','order_id'=>$order_id]); exit();
}

// ADD TRACKING UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tracking'])) {
    $order_id    = intval($_POST['order_id']);
    $location    = mysqli_real_escape_string($conn, trim($_POST['location']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $status = mysqli_real_escape_string($conn, trim($_POST['tracking_status']));
    // Handle custom status
    if ($status === 'Custom') {
        $custom_sel = trim($_POST['custom_status_select'] ?? '');
        $custom_txt = trim($_POST['custom_text'] ?? '');
        $status = mysqli_real_escape_string($conn, $custom_sel === 'other' ? $custom_txt : $custom_sel);
    }
    // Handle Delivery Completed = mark as delivered
    if ($status === 'Delivery Completed') {
        $_POST['mark_delivered'] = 1;
    }
    $is_delivered = isset($_POST['mark_delivered']) ? 1 : 0;
    if (empty($location)) { $error = "Location required!"; }
    else {
        $order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM orders WHERE id='$order_id'"));
        if (!$order) { $error = "Order not found!"; }
        else {
            mysqli_query($conn, "INSERT INTO order_tracking (order_id, status, location, description, updated_by, updated_by_id)
                VALUES ('$order_id', '$status', '$location', '$description', 'delivery_boy', '$db_id')");
            if ($is_delivered) {
                mysqli_query($conn, "UPDATE orders SET status='delivered', delivered_at=NOW() WHERE id='$order_id'");
                mysqli_query($conn, "INSERT INTO order_tracking (order_id, status, location, description, updated_by, updated_by_id)
                    VALUES ('$order_id', 'Delivered', '$location', 'Package successfully delivered ✅', 'delivery_boy', '$db_id')");
            }
            $success = "Tracking updated for Order #$order_id!";
        }
    }
}

// Fetch orders
$orders_result = mysqli_query($conn, "
    SELECT o.*, u.name as customer_name, u.phone as customer_phone,
           o.full_name, o.phone as order_phone, o.address,
           GROUP_CONCAT(p.name SEPARATOR ', ') as product_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE o.delivery_boy_id='$db_id' AND o.status NOT IN ('cancelled')
    GROUP BY o.id ORDER BY o.id DESC
");
$orders = [];
while ($row = mysqli_fetch_assoc($orders_result)) { $orders[] = $row; }
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
    body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--text);}
    nav{position:fixed;top:0;left:0;right:0;z-index:100;background:var(--dark);display:flex;align-items:center;justify-content:space-between;padding:0 5vw;height:65px;box-shadow:0 4px 20px rgba(0,0,0,0.3);}
    .logo{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:900;color:var(--white);text-decoration:none;}
    .logo span{color:var(--accent2);}
    .nav-right{display:flex;gap:0.8rem;align-items:center;flex-wrap:wrap;}
    .nav-name{color:rgba(255,255,255,0.8);font-size:0.88rem;font-weight:600;}
    .nav-link{padding:0.45rem 1.1rem;border:1.5px solid rgba(255,255,255,0.25);border-radius:50px;color:rgba(255,255,255,0.7);font-size:0.82rem;font-weight:600;text-decoration:none;transition:all .2s;}
    .nav-link:hover{border-color:var(--accent2);color:var(--accent2);}
    .main{margin-top:85px;padding:2rem 5vw;}
    .page-title{font-family:'Playfair Display',serif;font-size:1.8rem;color:var(--brown);margin-bottom:0.3rem;}
    .page-sub{color:var(--muted);font-size:0.88rem;margin-bottom:1.5rem;}
    .msg{padding:0.85rem 1.2rem;border-radius:12px;font-size:0.88rem;margin-bottom:1.5rem;font-weight:500;}
    .msg.success{background:#d4edda;color:#155724;border:1.5px solid #b8dfc4;}
    .msg.error{background:#f8d7da;color:#721c24;border:1.5px solid #f5b8bc;}

    /* ── SCAN BOX ── */
    .scan-section{background:var(--dark);border-radius:20px;padding:1.5rem;margin-bottom:2rem;color:white;}
    .scan-title{font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--accent2);margin-bottom:0.3rem;}
    .scan-sub{font-size:0.78rem;color:rgba(255,255,255,0.5);margin-bottom:1rem;}
    .scan-tabs{display:flex;gap:0.5rem;margin-bottom:1rem;}
    .scan-tab{flex:1;padding:0.6rem;border-radius:10px;text-align:center;font-size:0.82rem;font-weight:700;cursor:pointer;border:1.5px solid rgba(255,255,255,0.2);background:rgba(255,255,255,0.05);color:rgba(255,255,255,0.6);transition:all .2s;}
    .scan-tab.active{background:var(--accent);border-color:var(--accent);color:white;}
    /* QR Scanner */
    .qr-box{width:100%;max-width:320px;margin:0 auto;position:relative;}
    #qr-video{width:100%;border-radius:12px;display:block;border:2px solid var(--accent2);}
    .qr-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;}
    .qr-frame{width:55%;height:55%;border:3px solid var(--accent2);border-radius:8px;box-shadow:0 0 0 1000px rgba(0,0,0,0.4);}
    .scan-line{position:absolute;width:55%;height:2px;background:var(--accent2);animation:scanAnim 2s linear infinite;opacity:0.8;}
    @keyframes scanAnim{0%{top:22%}100%{top:78%}}
    .qr-start-btn{width:100%;padding:0.8rem;background:var(--accent2);color:var(--dark);border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.95rem;font-weight:700;cursor:pointer;margin-bottom:0.8rem;}
    .qr-stop-btn{width:100%;padding:0.6rem;background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.2);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.85rem;font-weight:600;cursor:pointer;display:none;}
    /* Manual input */
    .manual-box{display:none;}
    .manual-input-wrap{display:flex;gap:0.5rem;}
    .manual-input{flex:1;padding:0.75rem 1rem;border:1.5px solid rgba(255,255,255,0.2);border-radius:10px;background:rgba(255,255,255,0.08);color:white;font-family:'DM Sans',sans-serif;font-size:0.95rem;outline:none;}
    .manual-input::placeholder{color:rgba(255,255,255,0.3);}
    .manual-input:focus{border-color:var(--accent2);}
    .manual-btn{padding:0.75rem 1.2rem;background:var(--accent);color:white;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.9rem;font-weight:700;cursor:pointer;}
    /* Scan result */
    .scan-result{margin-top:1rem;padding:1rem;border-radius:12px;display:none;}
    .scan-result.success{background:rgba(40,167,69,0.15);border:1.5px solid rgba(40,167,69,0.4);}
    .scan-result.error{background:rgba(220,53,69,0.15);border:1.5px solid rgba(220,53,69,0.4);}
    .scan-result.loading{background:rgba(255,255,255,0.05);border:1.5px solid rgba(255,255,255,0.1);}
    .result-title{font-weight:700;font-size:1rem;margin-bottom:0.3rem;}
    .result-sub{font-size:0.82rem;opacity:0.7;}
    .result-ticker{display:flex;align-items:center;gap:0.5rem;margin-top:0.5rem;}
    .ticker-dot{width:8px;height:8px;border-radius:50%;background:var(--green);animation:pulse 1s infinite;}
    @keyframes pulse{0%,100%{opacity:1}50%{opacity:0.3}}
    .ticker-text{font-size:0.75rem;color:rgba(255,255,255,0.6);}

    /* ORDER CARDS */
    .orders-list{display:flex;flex-direction:column;gap:1.2rem;}
    .order-card{background:var(--white);border-radius:20px;overflow:hidden;box-shadow:0 3px 18px rgba(92,61,30,0.07);border:1.5px solid var(--warm);transition:all .3s;}
    .order-card.pickup-done{border-color:#28a745;}
    .order-strip{height:5px;}
    .strip-dispatched{background:linear-gradient(90deg,var(--accent2),#f0b429);}
    .strip-shipped{background:linear-gradient(90deg,#6f42c1,#9b6ee8);}
    .strip-delivered{background:linear-gradient(90deg,#28a745,#48c774);}
    .order-top{padding:1.2rem 1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.8rem;border-bottom:1px solid var(--warm);}
    .order-id{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;color:var(--brown);}
    .badges{display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;}
    .status-badge{padding:0.3rem 0.9rem;border-radius:50px;font-size:0.75rem;font-weight:700;}
    .s-shipped{background:#e9d8fd;color:#44337a;}
    .s-dispatched{background:#fff0d6;color:#854d00;}
    .s-delivered{background:#d4edda;color:#155724;}
    .pickup-badge{background:#d4edda;color:#155724;padding:0.25rem 0.7rem;border-radius:50px;font-size:0.72rem;font-weight:700;border:1px solid #b8dfc4;}
    .customer-section{padding:1.2rem 1.5rem;display:grid;grid-template-columns:1fr 1fr;gap:1rem;border-bottom:1px solid var(--warm);background:#fafafa;}
    .info-item label{font-size:0.7rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.3px;display:block;margin-bottom:0.2rem;}
    .info-item span{font-size:0.88rem;color:var(--text);font-weight:600;}
    .info-item.full{grid-column:span 2;}
    .call-btn{display:inline-flex;align-items:center;gap:0.4rem;padding:0.35rem 0.9rem;background:var(--green);color:white;border-radius:50px;font-size:0.8rem;font-weight:700;text-decoration:none;margin-top:0.4rem;}
    .tracking-section{padding:1rem 1.5rem;border-bottom:1px solid var(--warm);}
    .tracking-title{font-size:0.8rem;font-weight:700;color:var(--brown);margin-bottom:0.7rem;}
    .tracking-timeline{display:flex;flex-direction:column;gap:0.5rem;}
    .tracking-step{display:flex;gap:0.8rem;align-items:flex-start;}
    .t-dot{width:10px;height:10px;border-radius:50%;background:var(--accent);flex-shrink:0;margin-top:0.3rem;}
    .t-dot.delivered{background:var(--green);}
    .t-dot.pickup{background:#6f42c1;}
    .t-content{flex:1;}
    .t-location{font-weight:700;font-size:0.85rem;color:var(--text);}
    .t-desc{font-size:0.75rem;color:var(--muted);}
    .t-time{font-size:0.7rem;color:var(--muted);}
    .update-section{padding:1.2rem 1.5rem;background:var(--cream);}
    .update-title{font-size:0.82rem;font-weight:700;color:var(--brown);margin-bottom:0.8rem;}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;margin-bottom:0.8rem;}
    .form-group{margin-bottom:0.8rem;}
    .form-group label{display:block;font-size:0.78rem;font-weight:600;color:var(--brown);margin-bottom:0.3rem;}
    .form-group input,.form-group select{width:100%;padding:0.65rem 0.9rem;border:1.5px solid var(--warm);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.88rem;color:var(--text);background:var(--white);outline:none;transition:all .2s;}
    .form-group input:focus,.form-group select:focus{border-color:var(--accent);}
    .deliver-check{display:flex;align-items:center;gap:0.6rem;padding:0.7rem 1rem;background:#d4edda;border-radius:10px;border:1.5px solid #b8dfc4;cursor:pointer;margin-bottom:0.8rem;}
    .deliver-check input{width:auto;cursor:pointer;}
    .deliver-check label{color:#155724;font-weight:700;font-size:0.88rem;cursor:pointer;margin:0;}
    .btn-update{width:100%;padding:0.8rem;background:var(--accent);color:white;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.92rem;font-weight:700;cursor:pointer;transition:all .2s;}
    .btn-update:hover{background:#c0551f;transform:translateY(-1px);}
    .empty-state{text-align:center;padding:4rem 2rem;color:var(--muted);background:var(--white);border-radius:22px;border:1.5px dashed var(--warm);}

    /* TOAST */
    .toast{position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%) translateY(100px);background:var(--dark);color:white;padding:0.9rem 1.8rem;border-radius:50px;font-size:0.9rem;font-weight:700;box-shadow:0 8px 30px rgba(0,0,0,0.3);transition:all .4s;z-index:999;opacity:0;white-space:nowrap;}
    .toast.show{transform:translateX(-50%) translateY(0);opacity:1;}
    .toast.success-toast{background:#155724;border:2px solid #28a745;}
    .toast.error-toast{background:#721c24;border:2px solid #dc3545;}

    /* RESPONSIVE */
    @media(max-width:600px){
      nav{height:auto;flex-wrap:wrap;padding:0.6rem 4vw;gap:0.5rem;}
      .logo{font-size:1.3rem;}.nav-right{gap:0.4rem;}.nav-link{padding:0.3rem 0.6rem;font-size:0.72rem;}
      .main{margin-top:110px;padding:1rem 4vw;}
      .customer-section{grid-template-columns:1fr;}
      .info-item.full{grid-column:span 1;}
      .form-row{grid-template-columns:1fr;}
    }
  </style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="logo">Trenzo<span>Kart</span></a>
  <div class="nav-right">
    <span class="nav-name">🚚 <?= htmlspecialchars($db_name) ?></span>
    <a href="../delivery_logout.php" class="nav-link">Logout</a>
  </div>
</nav>

<div class="main">
  <h1 class="page-title">🚚 Delivery Dashboard</h1>
  <p class="page-sub">Orders scan karo ya ID enter karo — pickup confirm karo</p>

  <?php if (!empty($success)): ?><div class="msg success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if (!empty($error)): ?><div class="msg error">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- ── SCAN SECTION ── -->
  <div class="scan-section">
    <div class="scan-title">📦 Pickup Confirm Karo</div>
    <div class="scan-sub">QR scan karo ya Order ID manually enter karo</div>

    <div class="scan-tabs">
      <div class="scan-tab active" id="tab-qr" onclick="switchTab('qr')">📷 QR Scan</div>
      <div class="scan-tab" id="tab-manual" onclick="switchTab('manual')">⌨️ Manual ID</div>
    </div>

    <!-- QR Scanner -->
    <div id="qr-section">
      <button class="qr-start-btn" id="startBtn" onclick="startScan()">📷 Camera Start Karo</button>
      <div class="qr-box" id="qrBox" style="display:none;">
        <video id="qr-video" autoplay playsinline muted></video>
        <div class="qr-overlay">
          <div class="qr-frame"></div>
          <div class="scan-line"></div>
        </div>
      </div>
      <button class="qr-stop-btn" id="stopBtn" onclick="stopScan()">✕ Camera Band Karo</button>
    </div>

    <!-- Manual Input -->
    <div class="manual-box" id="manual-section">
      <div class="manual-input-wrap">
        <input type="number" class="manual-input" id="manualOrderId" placeholder="Order ID enter karo (e.g. 16)" min="1"/>
        <button class="manual-btn" onclick="confirmPickup(document.getElementById('manualOrderId').value)">✅ Confirm</button>
      </div>
    </div>

    <!-- Result -->
    <div class="scan-result" id="scanResult">
      <div class="result-title" id="resultTitle"></div>
      <div class="result-sub" id="resultSub"></div>
      <div class="result-ticker" id="resultTicker" style="display:none;">
        <div class="ticker-dot"></div>
        <div class="ticker-text">Sabko update ho raha hai — User, Vendor, Admin...</div>
      </div>
    </div>
  </div>

  <!-- ── ORDERS LIST ── -->
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
        <div style="display:flex;align-items:center;gap:0.7rem;flex-wrap:wrap;">
          <span class="order-id">#TK<?= str_pad($o['id'],5,'0',STR_PAD_LEFT) ?></span>
          <span class="status-badge s-<?= $o['status'] ?>"><?= strtoupper($o['status']) ?></span>
          <?php if ($o['pickup_confirmed']): ?>
            <span class="pickup-badge" id="pickup-badge-<?= $o['id'] ?>">📦 Pickup Done ✅</span>
          <?php else: ?>
            <span class="pickup-badge" id="pickup-badge-<?= $o['id'] ?>" style="display:none;background:#fff3cd;color:#856404;border-color:#ffc107;">⏳ Pickup Pending</span>
          <?php endif; ?>
        </div>
        <span style="color:var(--muted);font-size:0.82rem;">📅 <?= date('d M Y', strtotime($o['created_at'])) ?></span>
      </div>

      <div class="customer-section">
        <div class="info-item"><label>Customer</label><span><?= htmlspecialchars($name) ?></span></div>
        <div class="info-item"><label>Phone</label><span><?= htmlspecialchars($phone) ?></span><br><a href="tel:<?= $phone ?>" class="call-btn">📞 Call</a></div>
        <div class="info-item full"><label>Address</label><span><?= htmlspecialchars($o['address']??'N/A') ?></span></div>
        <div class="info-item"><label>Product</label><span><?= htmlspecialchars($o['product_name']??'Product') ?></span></div>
        <div class="info-item"><label>Amount</label><span>₹<?= number_format($o['total_amount'],2) ?> <span style="color:var(--accent);font-size:0.75rem;">(<?= strtoupper($o['payment_method']??'COD') ?>)</span></span></div>
      </div>

      <?php if (!empty($track_rows)): ?>
      <div class="tracking-section">
        <div class="tracking-title">📍 Tracking History</div>
        <div class="tracking-timeline" id="tracking-<?= $o['id'] ?>">
          <?php foreach ($track_rows as $t): ?>
          <div class="tracking-step">
            <div class="t-dot <?= $t['status']==='Delivered'?'delivered':($t['status']==='Picked Up'?'pickup':'') ?>"></div>
            <div class="t-content">
              <div class="t-location"><?= htmlspecialchars($t['location']) ?> — <span style="color:var(--accent);font-size:0.75rem;"><?= htmlspecialchars($t['status']) ?></span></div>
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
        // Get last tracking status to suggest next step
        $last_track = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM order_tracking WHERE order_id='{$o['id']}' ORDER BY created_at DESC LIMIT 1"));
        $last_status = $last_track['status'] ?? '';
        $next_steps = [
          ''                   => 'In Transit',
          'Picked Up'          => 'In Transit',
          'In Transit'         => 'At Hub',
          'At Hub'             => 'Out for Delivery',
          'Out for Delivery'   => 'Delivery Completed',
          'Delivery Attempt Failed' => 'Out for Delivery',
          'Address Not Found'  => 'Out for Delivery',
          'Customer Not Available' => 'Out for Delivery',
          'Returned to Hub'    => 'Out for Delivery',
        ];
        $suggested_next = $next_steps[$last_status] ?? 'In Transit';
        $all_steps = ['In Transit','At Hub','Out for Delivery','Delivery Completed','Custom'];
      ?>
      <div class="update-section">
        <div class="update-title">📝 Add Tracking Update</div>
        <?php if ($suggested_next && $suggested_next !== 'Delivered'): ?>
        <div style="background:#e8f5e9;border:1.5px solid #a5d6a7;border-radius:10px;padding:0.6rem 0.9rem;margin-bottom:0.8rem;font-size:0.82rem;color:#155724;font-weight:600;">
          💡 Next suggested step: <strong><?= $suggested_next ?></strong>
        </div>
        <?php endif; ?>
        <form method="POST">
          <input type="hidden" name="add_tracking" value="1"/>
          <input type="hidden" name="order_id" value="<?= $o['id'] ?>"/>
          <div class="form-row">
            <div class="form-group">
              <label>Current Location *</label>
              <input type="text" name="location" placeholder="e.g. Saharanpur Hub" required/>
            </div>
            <div class="form-group">
              <label>Status</label>
              <select name="tracking_status" id="status_<?= $o['id'] ?>" onchange="toggleCustom(<?= $o['id'] ?>, this.value)">
                <option value="In Transit"          <?= $suggested_next==='In Transit'?'selected':'' ?>>🚚 In Transit</option>
                <option value="At Hub"              <?= $suggested_next==='At Hub'?'selected':'' ?>>🏭 At Hub</option>
                <option value="Out for Delivery"    <?= $suggested_next==='Out for Delivery'?'selected':'' ?>>📬 Out for Delivery</option>
                <option value="Delivery Completed"  <?= $suggested_next==='Delivery Completed'?'selected':'' ?>>✅ Delivery Completed</option>
                <option value="Custom">⚙️ Custom...</option>
              </select>
            </div>
          </div>
          <!-- Custom status input -->
          <div class="form-group" id="custom_wrap_<?= $o['id'] ?>" style="display:none;">
            <label>Custom Status *</label>
            <select name="custom_status_select" id="custom_sel_<?= $o['id'] ?>" onchange="toggleCustomInput(<?= $o['id'] ?>, this.value)">
              <option value="">-- Select Custom --</option>
              <option value="Delivery Attempt Failed">⚠️ Delivery Attempt Failed</option>
              <option value="Address Not Found">📍 Address Not Found</option>
              <option value="Customer Not Available">📵 Customer Not Available</option>
              <option value="Returned to Hub">🔄 Returned to Hub</option>
              <option value="other">✏️ Type your own...</option>
            </select>
            <input type="text" id="custom_input_<?= $o['id'] ?>" name="custom_text"
                   placeholder="Type custom status..." style="margin-top:0.5rem;display:none;
                   width:100%;padding:0.65rem 0.9rem;border:1.5px solid var(--warm);border-radius:10px;
                   font-family:'DM Sans',sans-serif;font-size:0.88rem;"/>
          </div>
          <div class="form-group">
            <label>Description (optional)</label>
            <input type="text" name="description" placeholder="e.g. Package at sorting hub"/>
          </div>
          <div class="deliver-check">
            <input type="checkbox" name="mark_delivered" id="del_<?= $o['id'] ?>"/>
            <label for="del_<?= $o['id'] ?>">✅ Mark as Delivered — Final delivery done!</label>
          </div>
          <button type="submit" class="btn-update">📍 Update Tracking →</button>
        </form>
      </div>
      <?php else: ?>
      <div style="padding:1rem 1.5rem;background:#d4edda;text-align:center;">
        <span style="color:#155724;font-weight:700;">✅ Delivered Successfully!</span>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="toast" id="toast"></div>

<!-- jsQR library for QR scanning -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsqr/1.4.0/jsQR.min.js"></script>
<script>
function toggleCustom(oid, val) {
  const wrap = document.getElementById('custom_wrap_' + oid);
  if (wrap) wrap.style.display = val === 'Custom' ? 'block' : 'none';
}
function toggleCustomInput(oid, val) {
  const inp = document.getElementById('custom_input_' + oid);
  if (inp) { inp.style.display = val === 'other' ? 'block' : 'none'; inp.required = val === 'other'; }
}
let stream = null;
let scanning = false;
let animFrame = null;

// Switch scan tab
function switchTab(tab) {
  document.getElementById('tab-qr').classList.toggle('active', tab==='qr');
  document.getElementById('tab-manual').classList.toggle('active', tab==='manual');
  document.getElementById('qr-section').style.display = tab==='qr' ? 'block' : 'none';
  document.getElementById('manual-section').style.display = tab==='manual' ? 'block' : 'none';
  if (tab !== 'qr') stopScan();
}

// Start QR camera
async function startScan() {
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    const video = document.getElementById('qr-video');
    video.srcObject = stream;
    await video.play();
    document.getElementById('qrBox').style.display = 'block';
    document.getElementById('startBtn').style.display = 'none';
    document.getElementById('stopBtn').style.display = 'block';
    scanning = true;
    scanFrame();
  } catch(e) {
    showResult('error', '❌ Camera access nahi mila!', 'Browser settings mein camera allow karo ya Manual ID use karo');
  }
}

// Stop camera
function stopScan() {
  scanning = false;
  if (animFrame) cancelAnimationFrame(animFrame);
  if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
  document.getElementById('qrBox').style.display = 'none';
  document.getElementById('startBtn').style.display = 'block';
  document.getElementById('stopBtn').style.display = 'none';
}

// Scan frames
function scanFrame() {
  if (!scanning) return;
  const video = document.getElementById('qr-video');
  if (video.readyState === video.HAVE_ENOUGH_DATA) {
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    const imageData = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height);
    const code = jsQR(imageData.data, imageData.width, imageData.height);
    if (code) {
      stopScan();
      // Extract order ID from QR text
      let orderId = code.data;
      // Support formats: "16", "#TK00016", "TK00016", plain number
      orderId = orderId.replace(/[^0-9]/g, '');
      if (orderId) {
        confirmPickup(parseInt(orderId));
      } else {
        showResult('error', '❌ Invalid QR!', 'Yeh TrenzoKart order QR nahi hai');
      }
      return;
    }
  }
  animFrame = requestAnimationFrame(scanFrame);
}

// Confirm pickup via AJAX
function confirmPickup(orderId) {
  if (!orderId || orderId <= 0) {
    showResult('error', '❌ Invalid Order ID!', 'Sahi order ID enter karo');
    return;
  }
  showResult('loading', '⏳ Processing...', 'Order verify ho raha hai');

  const fd = new FormData();
  fd.append('confirm_pickup', '1');
  fd.append('order_id', orderId);

  fetch('dashboard.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'success') {
        showResult('success', '✅ ' + data.msg, 'Pickup confirmed! User, Vendor, Admin sabko update ho gaya');
        // Update UI instantly
        updateOrderCard(orderId);
        showToast('✅ Pickup Confirmed!', 'success');
        // Auto poll for 10 seconds to show real-time
        startRealTimePoll(orderId);
      } else {
        showResult('error', '❌ ' + data.msg, 'Dobara try karo');
        showToast('❌ ' + data.msg, 'error');
      }
    })
    .catch(() => {
      showResult('error', '❌ Network Error!', 'Internet check karo');
    });
}

// Show result box
function showResult(type, title, sub) {
  const box = document.getElementById('scanResult');
  const ticker = document.getElementById('resultTicker');
  box.className = 'scan-result ' + type;
  box.style.display = 'block';
  document.getElementById('resultTitle').textContent = title;
  document.getElementById('resultSub').textContent = sub;
  ticker.style.display = type === 'success' ? 'flex' : 'none';
}

// Update order card UI instantly
function updateOrderCard(orderId) {
  const card = document.getElementById('order-card-' + orderId);
  if (!card) return;
  card.classList.add('pickup-done');
  const badge = document.getElementById('pickup-badge-' + orderId);
  if (badge) {
    badge.style.display = 'inline-flex';
    badge.style.background = '#d4edda';
    badge.style.color = '#155724';
    badge.style.borderColor = '#b8dfc4';
    badge.textContent = '📦 Pickup Done ✅';
  }
  // Add tracking entry to UI
  const timeline = document.getElementById('tracking-' + orderId);
  if (timeline) {
    const now = new Date().toLocaleString('en-IN', {day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
    const step = document.createElement('div');
    step.className = 'tracking-step';
    step.innerHTML = `<div class="t-dot pickup"></div>
      <div class="t-content">
        <div class="t-location">Delivery Boy — <span style="color:var(--accent);font-size:0.75rem;">Picked Up</span></div>
        <div class="t-desc">Package picked up by delivery boy</div>
        <div class="t-time">🕐 ${now}</div>
      </div>`;
    timeline.appendChild(step);
    // Show tracking wrap if was hidden
    const wrap = document.getElementById('tracking-wrap-' + orderId);
    if (wrap) wrap.style.display = 'block';
  }
}

// Real-time poll — 2 sec interval for 10 seconds
function startRealTimePoll(orderId) {
  let count = 0;
  const ticker = document.getElementById('resultTicker');
  const msgs = [
    'User ko update ho gaya ✅',
    'Vendor ko update ho gaya ✅',
    'Admin ko update ho gaya ✅',
    'Sab sync ho gaye! 🎉'
  ];
  const interval = setInterval(() => {
    count++;
    if (count <= msgs.length) {
      document.querySelector('.ticker-text').textContent = msgs[count-1];
    }
    if (count >= 4) {
      clearInterval(interval);
      setTimeout(() => {
        document.getElementById('scanResult').style.display = 'none';
      }, 2000);
    }
  }, 2000);
}

// Toast notification
function showToast(msg, type) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast show ' + (type === 'success' ? 'success-toast' : 'error-toast');
  setTimeout(() => t.className = 'toast', 3000);
}

// Manual ID — Enter key support
document.getElementById('manualOrderId').addEventListener('keypress', function(e) {
  if (e.key === 'Enter') confirmPickup(this.value);
});
</script>
</body>
</html>