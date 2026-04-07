<?php
require 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Save refund details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_refund'])) {
    $rid    = intval($_POST['return_id']);
    $method = $_POST['refund_method'] === 'upi' ? 'upi' : 'bank';
    $conn->begin_transaction();
    if ($method === 'upi') {
        $upi = mysqli_real_escape_string($conn, trim($_POST['refund_upi']));
        mysqli_query($conn, "UPDATE return_requests SET refund_method='upi', refund_upi='$upi' WHERE id='$rid' AND user_id='$user_id'");
    } else {
        $bank   = mysqli_real_escape_string($conn, trim($_POST['refund_bank_name']));
        $acc    = mysqli_real_escape_string($conn, trim($_POST['refund_account_no']));
        $ifsc   = mysqli_real_escape_string($conn, trim($_POST['refund_ifsc']));
        $holder = mysqli_real_escape_string($conn, trim($_POST['refund_holder']));
        mysqli_query($conn, "UPDATE return_requests SET refund_method='bank', refund_bank_name='$bank', refund_account_no='$acc', refund_ifsc='$ifsc', refund_holder='$holder' WHERE id='$rid' AND user_id='$user_id'");
    }
    $conn->commit();
    header("Location: my_orders.php?success=refund_saved"); exit();
}

$orders = [];
$result = mysqli_query($conn, "SELECT * FROM orders WHERE user_id='$user_id' ORDER BY id DESC");
while ($row = mysqli_fetch_assoc($result)) {
    $track = mysqli_query($conn, "SELECT * FROM order_tracking WHERE order_id='{$row['id']}' ORDER BY created_at ASC");
    $row['tracking'] = [];
    while ($t = mysqli_fetch_assoc($track)) { $row['tracking'][] = $t; }
    $ret = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM return_requests WHERE order_id='{$row['id']}' AND user_id='$user_id' LIMIT 1"));
    $row['return_request'] = $ret;
    $orders[] = $row;
}

$cart_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(quantity) as t FROM cart WHERE user_id='$user_id'"))['t'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — My Orders</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{--cream:#f5efe6;--warm:#e8d5b7;--brown:#5c3d1e;--accent:#d4622a;--accent2:#e8a045;--text:#2d1a0a;--muted:#8a6a4a;--white:#fffdf8;}
    body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--text);}
    nav{position:fixed;top:0;left:0;right:0;z-index:100;background:rgba(245,239,230,0.97);backdrop-filter:blur(12px);border-bottom:1px solid var(--warm);display:flex;align-items:center;justify-content:space-between;padding:0 5vw;height:64px;box-shadow:0 2px 16px rgba(92,61,30,0.08);}
    .logo{font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;color:var(--brown);text-decoration:none;}
    .logo span{color:var(--accent);}
    .nav-right{display:flex;align-items:center;gap:0.8rem;}
    .nav-btn{display:flex;align-items:center;gap:0.4rem;padding:0.45rem 0.9rem;border-radius:50px;font-family:'DM Sans',sans-serif;font-size:0.82rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all .2s;border:1.5px solid var(--warm);color:var(--brown);background:var(--white);}
    .nav-btn:hover{background:var(--accent);color:white;border-color:var(--accent);}
    .main{margin-top:80px;padding:1.5rem 5vw;max-width:860px;margin-left:auto;margin-right:auto;}
    h1{font-family:'Playfair Display',serif;font-size:1.8rem;color:var(--brown);margin-bottom:0.2rem;}
    .subtitle{color:var(--muted);font-size:0.88rem;margin-bottom:1.5rem;}
    .success-msg{background:#d4edda;color:#155724;border:1.5px solid #b8dfc4;padding:0.8rem 1.2rem;border-radius:10px;font-size:0.88rem;font-weight:600;margin-bottom:1.2rem;}
    .order-card{background:var(--white);border-radius:18px;padding:1.2rem 1.5rem;margin-bottom:1.2rem;box-shadow:0 3px 18px rgba(92,61,30,0.07);border:1.5px solid transparent;transition:border-color .2s;}
    .order-card:hover{border-color:var(--warm);}
    .order-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:0.8rem;flex-wrap:wrap;gap:0.5rem;}
    .order-id{font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;color:var(--brown);}
    .order-date{font-size:0.75rem;color:var(--muted);}
    .badges{display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;}
    .status-badge{padding:0.25rem 0.8rem;border-radius:50px;font-size:0.72rem;font-weight:700;text-transform:uppercase;}
    .s-pending{background:#fff3cd;color:#856404;}
    .s-confirmed{background:#d4edda;color:#155724;}
    .s-shipped{background:#cce5ff;color:#004085;}
    .s-delivered{background:#d1ecf1;color:#0c5460;}
    .s-cancelled{background:#f8d7da;color:#721c24;}
    .order-details{display:grid;grid-template-columns:repeat(3,1fr);gap:0.8rem;padding:0.8rem;background:var(--cream);border-radius:10px;margin-bottom:0.8rem;}
    @media(max-width:500px){.order-details{grid-template-columns:1fr 1fr;}}
    .detail-block .label{font-size:0.68rem;color:var(--muted);text-transform:uppercase;font-weight:700;margin-bottom:0.2rem;}
    .detail-block .value{font-size:0.85rem;font-weight:600;color:var(--text);}
    .detail-block .value.amount{font-family:'Playfair Display',serif;font-size:1rem;color:var(--brown);}
    .btn-cancel{padding:0.3rem 0.8rem;background:#fff0f0;color:#dc3545;border:1.5px solid #f5c6cb;border-radius:50px;font-size:0.75rem;font-weight:700;cursor:pointer;transition:all .2s;font-family:'DM Sans',sans-serif;}
    .btn-cancel:hover{background:#dc3545;color:white;border-color:#dc3545;}
    .btn-return{padding:0.3rem 0.8rem;background:#fff3e0;color:#e65100;border:1.5px solid #ffcc80;border-radius:50px;font-size:0.75rem;font-weight:700;cursor:pointer;transition:all .2s;font-family:'DM Sans',sans-serif;}
    .btn-return:hover{background:#e65100;color:white;}

    /* TRACKING */
    .tracking-section{margin-top:0.8rem;padding-top:0.8rem;border-top:1px solid var(--warm);}
    .tracking-header{font-size:0.8rem;font-weight:700;color:var(--brown);margin-bottom:0.8rem;}
    .tracking-steps{display:flex;flex-direction:column;gap:0;}
    .tracking-step{display:flex;gap:0.8rem;align-items:flex-start;padding-bottom:0.8rem;}
    .tracking-step:last-child{padding-bottom:0;}
    .step-left{display:flex;flex-direction:column;align-items:center;flex-shrink:0;}
    .step-dot{width:13px;height:13px;border-radius:50%;border:2.5px solid var(--warm);background:var(--cream);flex-shrink:0;}
    .step-dot.done{background:#28a745;border-color:#28a745;}
    .step-dot.active{background:var(--accent);border-color:var(--accent);width:15px;height:15px;box-shadow:0 0 0 3px rgba(212,98,42,0.2);}
    .step-line{width:2px;flex:1;background:var(--warm);min-height:16px;}
    .step-line.done{background:#28a745;}
    .step-content{flex:1;}
    .step-title{font-weight:700;font-size:0.85rem;color:var(--text);}
    .step-title.active{color:var(--accent);}
    .step-title.done{color:#28a745;}
    .step-time{font-size:0.7rem;color:var(--muted);margin-top:0.1rem;}

    /* RETURN TRACKING */
    .return-track{background:var(--cream);border-radius:14px;padding:1rem;margin-top:0.8rem;border:1.5px solid var(--warm);}
    .return-track-title{font-size:0.82rem;font-weight:700;color:var(--brown);margin-bottom:0.8rem;display:flex;align-items:center;gap:0.4rem;}
    .ret-steps{display:flex;flex-direction:column;gap:0;}
    .ret-step{display:flex;gap:0.8rem;align-items:flex-start;padding-bottom:0.7rem;}
    .ret-step:last-child{padding-bottom:0;}
    .ret-dot{width:12px;height:12px;border-radius:50%;flex-shrink:0;margin-top:2px;}
    .ret-dot.done{background:#28a745;}
    .ret-dot.active{background:var(--accent);}
    .ret-dot.pending{background:var(--warm);border:2px solid var(--muted);}
    .ret-line{width:2px;flex:1;min-height:14px;}
    .ret-line.done{background:#28a745;}
    .ret-line.pending{background:var(--warm);}
    .ret-content .ret-title{font-size:0.82rem;font-weight:700;}
    .ret-content .ret-sub{font-size:0.72rem;color:var(--muted);margin-top:0.1rem;}
    .ret-done{color:#28a745;}
    .ret-active{color:var(--accent);}
    .ret-pending{color:var(--muted);}

    /* REFUND FORM */
    .refund-form-box{background:#fff5f0;border:1.5px solid var(--warm);border-radius:14px;padding:1rem;margin-top:0.8rem;}
    .refund-form-title{font-size:0.82rem;font-weight:700;color:var(--brown);margin-bottom:0.8rem;}
    .method-tabs{display:flex;gap:0.5rem;margin-bottom:0.8rem;}
    .method-tab{flex:1;padding:0.5rem;border:1.5px solid var(--warm);border-radius:8px;text-align:center;cursor:pointer;font-size:0.82rem;font-weight:600;color:var(--muted);transition:all .2s;}
    .method-tab.active{background:var(--accent);color:white;border-color:var(--accent);}
    .form-group{margin-bottom:0.7rem;}
    .form-group label{display:block;font-size:0.75rem;font-weight:600;color:var(--brown);margin-bottom:0.3rem;}
    .form-group input{width:100%;padding:0.6rem 0.8rem;border:1.5px solid var(--warm);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:0.88rem;color:var(--text);background:var(--white);outline:none;transition:all .2s;}
    .form-group input:focus{border-color:var(--accent);}
    .btn-save-refund{width:100%;padding:0.7rem;background:var(--accent);color:white;border:none;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:0.88rem;font-weight:700;cursor:pointer;transition:all .2s;}
    .btn-save-refund:hover{background:#c0551f;}

    /* MODAL */
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:500;display:none;align-items:center;justify-content:center;padding:1rem;backdrop-filter:blur(4px);}
    .modal-overlay.open{display:flex;}
    .modal{background:var(--white);border-radius:20px;padding:1.5rem;width:100%;max-width:460px;animation:slideUp .3s ease;}
    @keyframes slideUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
    .modal-title{font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--brown);margin-bottom:1.2rem;padding-bottom:0.7rem;border-bottom:1.5px solid var(--warm);display:flex;justify-content:space-between;align-items:center;}
    .modal-close{background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--muted);}
    .reason-grid{display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:0.8rem;}
    .reason-btn{padding:0.5rem 0.6rem;border:1.5px solid var(--warm);border-radius:8px;background:var(--cream);cursor:pointer;font-size:0.78rem;font-weight:600;color:var(--muted);text-align:center;transition:all .2s;}
    .reason-btn:hover,.reason-btn.sel{background:var(--accent);border-color:var(--accent);color:white;}
    .btn-submit-return{width:100%;padding:0.75rem;background:var(--accent);color:white;border:none;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:0.9rem;font-weight:700;cursor:pointer;}
    .empty-state{text-align:center;padding:3rem 1rem;color:var(--muted);}
    .empty-state span{font-size:3.5rem;display:block;margin-bottom:1rem;}
    .empty-state a{color:white;text-decoration:none;font-weight:600;display:inline-block;margin-top:1rem;padding:0.6rem 1.5rem;background:var(--accent);border-radius:50px;font-size:0.88rem;}
    .toast{position:fixed;bottom:1.5rem;right:1.5rem;background:var(--brown);color:white;padding:0.7rem 1.3rem;border-radius:10px;font-size:0.85rem;font-weight:500;box-shadow:0 6px 20px rgba(0,0,0,0.2);transform:translateY(80px);opacity:0;transition:all .3s;z-index:999;}
    .toast.show{transform:translateY(0);opacity:1;}
  
    /* HAMBURGER */
    .hamburger { display: none; flex-direction: column; gap: 5px; cursor: pointer; background: none; border: none; padding: 6px; z-index: 201; }
    .hamburger span { display: block; width: 24px; height: 2.5px; background: var(--brown); border-radius: 4px; transition: all 0.3s; }
    .hamburger.open span:nth-child(1) { transform: translateY(7.5px) rotate(45deg); }
    .hamburger.open span:nth-child(2) { opacity: 0; }
    .hamburger.open span:nth-child(3) { transform: translateY(-7.5px) rotate(-45deg); }
    .nav-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 199; }
    .nav-overlay.open { display: block; }

    @media(max-width:768px){
      .hamburger{display:flex;}
      .nav-right{position:fixed;top:0;right:-260px;width:240px;height:100vh;background:var(--white);flex-direction:column;align-items:flex-start;padding:80px 1.2rem 2rem;gap:0.6rem;z-index:200;box-shadow:-8px 0 24px rgba(92,61,30,0.15);transition:right 0.3s;overflow-y:auto;}
      .nav-right.open{right:0;}
      .nav-btn{width:100%;border-radius:10px;padding:0.7rem 1rem;}
      .main{padding:1rem 4vw;}
      .order-card{padding:1rem;}
    }
    @media(max-width:500px){
      .order-details{grid-template-columns:1fr 1fr;}
      .modal{padding:1rem;}
    }

</style>
</head>
<body>
<div class="nav-overlay" id="navOverlay" onclick="closeNav()"></div>
<nav>
  <a href="index.php" class="logo">Trenzo<span>Kart</span></a>
  <button class="hamburger" id="hamburger" onclick="toggleNav()" aria-label="Menu"><span></span><span></span><span></span></button>
    <div class="nav-right" id="navRight">
    <a href="index.php" class="nav-btn">🏠 Home</a>
    <a href="cart.php" class="nav-btn">🛒 Cart</a>
    <a href="profile.php" class="nav-btn">👤 Profile</a>
  </div>
</nav>

<div class="main">
  <h1>📦 My Orders</h1>
  <p class="subtitle">Hello <?= htmlspecialchars($user_name) ?>! Apne orders track karo.</p>

  <?php if (isset($_GET['success']) && $_GET['success'] === 'refund_saved'): ?>
    <div class="success-msg">✅ Refund details save ho gayi! Admin process karega.</div>
  <?php endif; ?>

  <?php if (empty($orders)): ?>
    <div class="empty-state">
      <span>📭</span><p>Koi order nahi!</p>
      <a href="index.php">Shopping Start Karo →</a>
    </div>
  <?php else: ?>
    <?php foreach ($orders as $order):
      $status     = $order['status'] ?? 'pending';
      $date       = date('d M Y, h:i A', strtotime($order['created_at']));
      $ret        = $order['return_request'];
      $can_cancel = false; $cancel_note = '';
      if ($status === 'pending') { $can_cancel = true; }
      elseif ($status === 'confirmed' && !empty($order['confirmed_at'])) {
        $hrs = (time() - strtotime($order['confirmed_at'])) / 3600;
        if ($hrs <= 24) { $can_cancel = true; $cancel_note = "⏰ ~".round(24-$hrs)." hr left"; }
      }
      $can_return = false; $return_days_left = 0;
      if ($status === 'delivered' && !empty($order['delivered_at']) && !$ret) {
        $days = (time() - strtotime($order['delivered_at'])) / 86400;
        if ($days <= 10) { $can_return = true; $return_days_left = max(0, round(10-$days)); }
      }
      // Refund form dikhao? pickup done + 24hrs passed
      $show_refund_form = false;
      if ($ret && $ret['status'] === 'approved' && $ret['pickup_completed'] && empty($ret['refund_method'])) {
        $hrs_since_pickup = (time() - strtotime($ret['pickup_at'])) / 3600;
        if ($hrs_since_pickup >= 24) { $show_refund_form = true; }
      }
    ?>
    <div class="order-card" id="order-<?= $order['id'] ?>">
      <div class="order-top">
        <div>
          <div class="order-id">#TK<?= str_pad($order['id'],5,'0',STR_PAD_LEFT) ?></div>
          <div class="order-date"><?= $date ?></div>
        </div>
        <div class="badges">
          <span class="status-badge s-<?= $status ?>" id="badge-<?= $order['id'] ?>"><?= ucfirst($status) ?></span>
          <?php if ($ret): ?>
            <span class="status-badge" style="background:<?= $ret['status']==='pending'?'#fff3e0':($ret['status']==='approved'?'#d4edda':'#f8d7da') ?>;color:<?= $ret['status']==='pending'?'#e65100':($ret['status']==='approved'?'#155724':'#721c24') ?>;">↩ <?= ucfirst($ret['status']) ?></span>
          <?php endif; ?>
          <?php if ($can_cancel): ?><button class="btn-cancel" onclick="cancelOrder(<?= $order['id'] ?>)">✕ Cancel</button><?php endif; ?>
          <?php if ($can_return): ?><button class="btn-return" onclick="openReturn(<?= $order['id'] ?>)">↩ Return</button><?php endif; ?>
        </div>
      </div>
      <?php if ($cancel_note): ?><div style="font-size:0.72rem;color:var(--muted);margin-bottom:0.5rem;"><?= $cancel_note ?></div><?php endif; ?>
      <?php if ($can_return): ?><div style="font-size:0.72rem;color:#e65100;margin-bottom:0.5rem;">↩ Return window: <?= $return_days_left ?> day(s) left</div><?php endif; ?>

      <div class="order-details">
        <div class="detail-block"><div class="label">Amount</div><div class="value amount">₹<?= number_format($order['total_amount'],2) ?></div></div>
        <div class="detail-block"><div class="label">Payment</div><div class="value"><?= strtoupper($order['payment_method']??'COD') ?></div></div>
        <div class="detail-block"><div class="label">Address</div><div class="value" style="font-size:0.78rem;"><?= htmlspecialchars($order['address']??'N/A') ?></div></div>
      </div>

      <!-- ORDER TRACKING -->
      <?php
      $delivered_label = '🎉 Delivered';
      if (!empty($order['courier_name']) && $status === 'delivered') {
          $delivered_label = '🎉 Delivered by ' . htmlspecialchars($order['courier_name']);
      }
      $base = ['pending'=>['📋','Order Placed',$order['created_at']],'confirmed'=>['✅','Confirmed',$order['confirmed_at']??null],'dispatched'=>['📦','Dispatched',$order['dispatched_at']??null],'shipped'=>['🚚','Shipped',$order['shipped_at']??null],'delivered'=>[$delivered_label,'',$order['delivered_at']??null]];
      $statuses = array_keys($base); $cur = array_search($status,$statuses); if($cur===false)$cur=0;
      ?>
      <div class="tracking-section">
        <div class="tracking-header">📍 Order Tracking</div>
        <div class="tracking-steps">
          <?php foreach ($base as $s => $info):
            $si = array_search($s,$statuses);
            if($si>$cur && $status==='cancelled') continue;
            // dot: past=green, current=orange(except delivered=green), future=grey
            if ($si < $cur) $dot_class = 'done';
            elseif ($si === $cur && $status !== 'delivered') $dot_class = 'active';
            elseif ($si === $cur && $status === 'delivered') $dot_class = 'done';
            else $dot_class = '';
            // line: green if past step
            $line_class = ($si < $cur) ? 'done' : '';
            $last = $s==='delivered';
          ?>
          <div class="tracking-step">
            <div class="step-left">
              <div class="step-dot <?= $dot_class ?>"></div>
              <?php if(!$last):?><div class="step-line <?= $line_class ?>"></div><?php endif;?>
            </div>
            <div class="step-content">
              <div class="step-title <?= $dot_class ?>"><?= $s==='delivered' ? $info[0] : $info[0].' '.$info[1] ?></div>
              <?php if(!empty($info[2])): ?><div class="step-time">🕐 <?= date('d M Y, h:i A',strtotime($info[2])) ?></div>
              <?php elseif($si>$cur): ?><div class="step-time" style="color:#ccc;">Pending...</div><?php endif;?>
            </div>
          </div>
          <?php endforeach;?>
          <?php
          $track_icons = [
            'Picked Up'              => ['📦','done'],
            'In Transit'             => ['🚚','done'],
            'At Hub'                 => ['🏭','done'],
            'Out for Delivery'       => ['📬','done'],
            'Delivery Completed'     => ['✅','done'],
            'Delivery Attempt Failed'=> ['⚠️','done'],
            'Address Not Found'      => ['📍','done'],
            'Customer Not Available' => ['📵','done'],
            'Returned to Hub'        => ['🔄','done'],
            'Delivered'              => ['✅','done'],
          ];
          $total_tracks = count($order['tracking']);
          foreach ($order['tracking'] as $ti => $t):
            $is_last_track = ($ti === $total_tracks - 1);
            $icon = $track_icons[$t['status']][0] ?? '📍';
            // All steps green — last step orange only if not delivered
            if ($is_last_track && $t['status'] !== 'Delivered' && $status !== 'delivered') {
              $cls = 'active'; // orange - currently here
            } else {
              $cls = 'done'; // green - completed
            }
          ?>
          <div class="tracking-step">
            <div class="step-left">
              <div class="step-dot <?= $cls ?>"></div>
              <?php if(!$is_last_track): ?><div class="step-line <?= $cls==='done'?'done':'' ?>"></div><?php endif;?>
            </div>
            <div class="step-content">
              <div class="step-title <?= $cls ?>"><?= $icon ?> <?= htmlspecialchars($t['status']) ?></div>
              <div class="step-time">📍 <?= htmlspecialchars($t['location']) ?> • 🕐 <?= date('d M Y, h:i A',strtotime($t['created_at'])) ?></div>
            </div>
          </div>
          <?php endforeach;?>
        </div>
      </div>

      <!-- RETURN TRACKING -->
      <?php if ($ret): ?>
      <div class="return-track">
        <div class="return-track-title">↩ Return & Refund Tracking</div>
        <div class="ret-steps">
          <?php
          $ret_steps = [
            ['↩ Return Requested', date('d M Y',strtotime($ret['created_at'])), 'done'],
            ['🔍 Under Review', '', $ret['status']!=='pending'?'done':'active'],
            [$ret['status']==='approved'?'✅ Return Approved':'❌ Return '.ucfirst($ret['status']), $ret['status']!=='pending'?date('d M Y',strtotime($ret['updated_at']??$ret['created_at'])):'' , $ret['status']!=='pending'?'done':'pending'],
            ['📦 Pickup Scheduled', '', $ret['status']==='approved'&&!$ret['pickup_completed']?'active':'pending'],
            ['🚚 Pickup Completed', $ret['pickup_completed']?date('d M Y',strtotime($ret['pickup_at'])):'', $ret['pickup_completed']?'done':'pending'],
            ['💳 Refund Details Submitted', !empty($ret['refund_method'])?'Submitted':'', !empty($ret['refund_method'])?'done':'pending'],
            ['💰 Refund Processed', $ret['refund_status']==='processed'?date('d M Y',strtotime($ret['refund_at'])):'', $ret['refund_status']==='processed'?'done':'pending'],
          ];
          foreach ($ret_steps as $i => $step):
            $is_last = $i === count($ret_steps)-1;
          ?>
          <div class="ret-step">
            <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0;">
              <div class="ret-dot <?= $step[2] ?>"></div>
              <?php if(!$is_last): ?><div class="ret-line <?= $step[2]==='done'?'done':'pending' ?>"></div><?php endif; ?>
            </div>
            <div class="ret-content">
              <div class="ret-title ret-<?= $step[2] ?>"><?= $step[0] ?></div>
              <?php if(!empty($step[1])): ?><div class="ret-sub"><?= $step[1] ?></div><?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- REFUND FORM -->
        <?php if ($show_refund_form): ?>
        <div class="refund-form-box" style="margin-top:1rem;">
          <div class="refund-form-title">💳 Refund Details Bharo — ₹<?= number_format($order['total_amount'],2) ?> wapas aayega</div>
          <form method="POST" id="refund-form-<?= $ret['id'] ?>">
            <input type="hidden" name="save_refund" value="1"/>
            <input type="hidden" name="return_id" value="<?= $ret['id'] ?>"/>
            <div class="method-tabs">
              <div class="method-tab active" id="tab-upi-<?= $ret['id'] ?>" onclick="switchMethod('upi',<?= $ret['id'] ?>)">📱 UPI</div>
              <div class="method-tab" id="tab-bank-<?= $ret['id'] ?>" onclick="switchMethod('bank',<?= $ret['id'] ?>)">🏦 Bank Transfer</div>
            </div>
            <input type="hidden" name="refund_method" id="method-<?= $ret['id'] ?>" value="upi"/>
            <div id="upi-fields-<?= $ret['id'] ?>">
              <div class="form-group"><label>UPI ID *</label><input type="text" name="refund_upi" placeholder="yourname@upi" required/></div>
            </div>
            <div id="bank-fields-<?= $ret['id'] ?>" style="display:none;">
              <div class="form-group"><label>Account Holder Name *</label><input type="text" name="refund_holder" placeholder="Name as per bank"/></div>
              <div class="form-group"><label>Account Number *</label><input type="text" name="refund_account_no" placeholder="Account number"/></div>
              <div class="form-group"><label>IFSC Code *</label><input type="text" name="refund_ifsc" placeholder="HDFC0001234"/></div>
              <div class="form-group"><label>Bank Name *</label><input type="text" name="refund_bank_name" placeholder="HDFC, SBI etc."/></div>
            </div>
            <button type="submit" class="btn-save-refund">💾 Save & Submit →</button>
          </form>
        </div>
        <?php elseif (!empty($ret['refund_method']) && $ret['refund_status'] !== 'processed'): ?>
        <div style="background:#e8f5e9;border:1.5px solid #a5d6a7;border-radius:10px;padding:0.7rem 1rem;margin-top:0.8rem;font-size:0.82rem;color:#2e7d32;font-weight:600;">
          ✅ Refund details submitted — Admin process karega jald hi!
          <?php if ($ret['refund_method']==='upi'): ?>
            <br><span style="font-weight:400;">UPI: <?= htmlspecialchars($ret['refund_upi']) ?></span>
          <?php else: ?>
            <br><span style="font-weight:400;">Bank: <?= htmlspecialchars($ret['refund_bank_name']) ?> — A/C: <?= htmlspecialchars($ret['refund_account_no']) ?></span>
          <?php endif; ?>
        </div>
        <?php elseif ($ret['refund_status']==='processed'): ?>
        <div style="background:#d4edda;border:1.5px solid #b8dfc4;border-radius:10px;padding:0.7rem 1rem;margin-top:0.8rem;font-size:0.85rem;color:#155724;font-weight:700;">
          💰 Refund Successful! ₹<?= number_format($order['total_amount'],2) ?> processed on <?= date('d M Y',strtotime($ret['refund_at'])) ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- RETURN MODAL -->
<div class="modal-overlay" id="returnModal">
  <div class="modal">
    <div class="modal-title">↩ Return Request<button class="modal-close" onclick="closeReturn()">✕</button></div>
    <div style="background:#fff3e0;border:1px solid #ffcc80;border-radius:10px;padding:0.8rem;margin-bottom:1rem;font-size:0.8rem;color:#7c4700;">
      📋 7 working days • Unused & original packaging • Refund in 5-7 days
    </div>
    <form id="returnForm">
      <input type="hidden" id="ret_order_id" name="order_id"/>
      <div class="reason-grid">
        <div class="reason-btn" onclick="selReason('Wrong product',this)">📦 Wrong Product</div>
        <div class="reason-btn" onclick="selReason('Damaged/defective',this)">💔 Damaged</div>
        <div class="reason-btn" onclick="selReason('Size/fit issue',this)">👕 Size Issue</div>
        <div class="reason-btn" onclick="selReason('Not as described',this)">🖼️ Not Described</div>
        <div class="reason-btn" onclick="selReason('Changed my mind',this)">🤔 Changed Mind</div>
        <div class="reason-btn" onclick="selReason('Late delivery',this)">⏰ Late Delivery</div>
      </div>
      <input type="hidden" id="selReason" name="reason"/>
      <div class="form-group"><label>Additional Details</label><textarea name="description" style="width:100%;padding:0.6rem 0.8rem;border:1.5px solid var(--warm);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:0.85rem;resize:vertical;min-height:70px;background:var(--cream);outline:none;" placeholder="Issue describe karo..."></textarea></div>
      <button type="button" class="btn-submit-return" onclick="submitReturn()">📤 Submit Return →</button>
    </form>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
function cancelOrder(id) {
  if (!confirm('Cancel this order?')) return;
  fetch('cancel_order.php?order_id='+id).then(r=>r.json()).then(data=>{
    if (data.status==='success') {
      document.getElementById('badge-'+id).textContent='Cancelled';
      document.getElementById('badge-'+id).className='status-badge s-cancelled';
      const card=document.getElementById('order-'+id);
      const btn=card.querySelector('.btn-cancel'); if(btn) btn.remove();
      showToast('✅ Order cancelled!');
    } else showToast('❌ '+data.msg);
  });
}

function openReturn(id) {
  document.getElementById('ret_order_id').value=id;
  document.getElementById('selReason').value='';
  document.querySelectorAll('.reason-btn').forEach(b=>b.classList.remove('sel'));
  document.getElementById('returnModal').classList.add('open');
}
function closeReturn() { document.getElementById('returnModal').classList.remove('open'); }
function selReason(r,el) { document.querySelectorAll('.reason-btn').forEach(b=>b.classList.remove('sel')); el.classList.add('sel'); document.getElementById('selReason').value=r; }
function submitReturn() {
  const oid=document.getElementById('ret_order_id').value;
  const reason=document.getElementById('selReason').value;
  const desc=document.querySelector('#returnForm textarea').value;
  if (!reason) { showToast('❌ Reason select karo!'); return; }
  fetch('return_request.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`order_id=${oid}&reason=${encodeURIComponent(reason)}&description=${encodeURIComponent(desc)}`})
  .then(r=>r.json()).then(data=>{
    if (data.status==='success') { closeReturn(); showToast('✅ Return submitted!'); setTimeout(()=>location.reload(),1500); }
    else showToast('❌ '+data.msg);
  });
}
document.getElementById('returnModal').addEventListener('click',function(e){ if(e.target===this) closeReturn(); });

function switchMethod(m, rid) {
  document.getElementById('method-'+rid).value=m;
  document.getElementById('tab-upi-'+rid).classList.toggle('active',m==='upi');
  document.getElementById('tab-bank-'+rid).classList.toggle('active',m==='bank');
  document.getElementById('upi-fields-'+rid).style.display=m==='upi'?'block':'none';
  document.getElementById('bank-fields-'+rid).style.display=m==='bank'?'block':'none';
  // Toggle required
  document.querySelectorAll('#upi-fields-'+rid+' input').forEach(i=>i.required=m==='upi');
  document.querySelectorAll('#bank-fields-'+rid+' input').forEach(i=>i.required=m==='bank');
}

function showToast(msg) {
  const t=document.getElementById('toast'); t.textContent=msg; t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'),3000);
}
</script>

  <script>
  function toggleNav() {
    document.getElementById('navRight').classList.toggle('open');
    var h = document.getElementById('hamburger');
    if(h) h.classList.toggle('open');
    document.getElementById('navOverlay').classList.toggle('open');
    document.body.style.overflow = document.getElementById('navRight').classList.contains('open') ? 'hidden' : '';
  }
  function closeNav() {
    document.getElementById('navRight').classList.remove('open');
    var h = document.getElementById('hamburger');
    if(h) h.classList.remove('open');
    document.getElementById('navOverlay').classList.remove('open');
    document.body.style.overflow = '';
  }
  </script>
</body>
</html>