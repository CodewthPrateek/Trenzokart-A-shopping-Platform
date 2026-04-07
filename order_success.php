<?php
require 'config.php';
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$order_id  = intval($_GET['order_id'] ?? 0);
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE id=? AND user_id=?");
mysqli_stmt_bind_param($stmt, 'ii', $order_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$order  = mysqli_fetch_assoc($result);

if (!$order) {
  header("Location: index.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TrenzoKart — Order Placed!</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --cream: #f5efe6; --warm: #e8d5b7; --brown: #5c3d1e; --accent: #d4622a; --accent2: #e8a045; --text: #2d1a0a; --muted: #8a6a4a; --white: #fffdf8; }
    body { font-family: 'DM Sans', sans-serif; background: var(--cream); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
    .success-card { background: var(--white); border-radius: 24px; padding: 3rem; max-width: 500px; width: 100%; text-align: center; box-shadow: 0 24px 80px rgba(92, 61, 30, 0.15); animation: fadeUp 0.6s ease forwards; }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
    .success-icon { width: 90px; height: 90px; background: #d4edda; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3rem; margin: 0 auto 1.5rem; animation: pop 0.5s ease 0.3s both; }
    @keyframes pop { from { transform: scale(0); } to { transform: scale(1); } }
    h1 { font-family: 'Playfair Display', serif; font-size: 2rem; color: var(--brown); margin-bottom: 0.5rem; }
    .subtitle { color: var(--muted); font-size: 0.95rem; margin-bottom: 2rem; line-height: 1.6; }
    .order-details { background: var(--cream); border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem; text-align: left; }
    .detail-row { display: flex; justify-content: space-between; margin-bottom: 0.8rem; font-size: 0.9rem; }
    .detail-row:last-child { margin-bottom: 0; }
    .detail-label { color: var(--muted); }
    .detail-value { font-weight: 600; color: var(--text); }
    .status-badge { background: #d4edda; color: #155724; padding: 0.2rem 0.6rem; border-radius: 50px; font-size: 0.78rem; font-weight: 700; }
    .btn-home { display: inline-block; width: 100%; padding: 0.9rem; background: var(--accent); color: white; border: none; border-radius: 12px; font-family: 'DM Sans', sans-serif; font-size: 1rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: all .2s; margin-bottom: 1rem; }
    .btn-home:hover { background: #c0551f; transform: translateY(-2px); }
    .logo { font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 900; color: var(--brown); margin-bottom: 2rem; display: block; }
    .logo span { color: var(--accent); }
    @media(max-width:500px){
      body { padding: 1rem; align-items: flex-start; padding-top: 2rem; }
      .success-card { padding: 1.8rem 1.3rem; border-radius: 16px; }
      h1 { font-size: 1.6rem; }
      .detail-row { flex-direction: column; gap: 0.2rem; }
      .detail-value { text-align: left !important; max-width: 100% !important; }
    }
  </style>
</head>
<body>
  <div class="success-card">
    <a href="index.php" class="logo">Trenzo<span>Kart</span></a>
    <div class="success-icon">🎉</div>
    <h1>Order Placed!</h1>
    <p class="subtitle">Thank you <?= htmlspecialchars($user_name) ?>! Your order has been placed successfully and will be delivered soon.</p>
    <div class="order-details">
      <div class="detail-row"><span class="detail-label">Order ID</span><span class="detail-value">#TK<?= str_pad($order_id, 5, '0', STR_PAD_LEFT) ?></span></div>
      <div class="detail-row"><span class="detail-label">Total Amount</span><span class="detail-value">₹<?= number_format($order['total_amount'], 2) ?></span></div>
      <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value"><span class="status-badge">✓ Confirmed</span></span></div>
      <div class="detail-row"><span class="detail-label">Estimated Delivery</span><span class="detail-value">3-5 Business Days</span></div>
      <div class="detail-row"><span class="detail-label">Payment Method</span><span class="detail-value"><?= htmlspecialchars(strtoupper($order['payment_method'] ?? 'COD')) ?></span></div>
      <div class="detail-row"><span class="detail-label">Delivery Address</span><span class="detail-value" style="font-size:0.82rem;text-align:right;max-width:200px"><?= htmlspecialchars($order['address'] ?? '') ?></span></div>
    </div>
    <a href="index.php" class="btn-home">Continue Shopping →</a>
  </div>
</body>
</html>