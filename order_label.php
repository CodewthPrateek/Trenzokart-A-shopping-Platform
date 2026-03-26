<?php
require 'config.php';

$order_id = intval($_GET['id'] ?? 0);
$token    = $_GET['token'] ?? '';

if (!$order_id) { die('Invalid order!'); }

// Simple token verify karo
$expected_token = md5('trenzokart_' . $order_id . '_label');
if ($token !== $expected_token) { die('Invalid access!'); }

// Fetch order details
$result = mysqli_query($conn, "
    SELECT o.*, 
           u.name as customer_name,
           u.email as customer_email,
           u.phone as customer_phone,
           GROUP_CONCAT(p.name SEPARATOR ', ') as product_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE o.id = '$order_id'
    GROUP BY o.id
");

$o = mysqli_fetch_assoc($result);
if (!$o) { die('Order not found!'); }

$phone   = preg_replace('/\D/', '', $o['phone'] ?? $o['customer_phone'] ?? '');
$name    = $o['full_name'] ?? $o['customer_name'];
$address = $o['address'] ?? 'N/A';
$amount  = number_format($o['total_amount'], 2);
$payment = strtoupper($o['payment_method'] ?? 'COD');
$product = $o['product_name'] ?? 'Product';
$date    = date('d M Y', strtotime($o['created_at']));
$status  = strtoupper($o['status']);

$wa_text = urlencode("🛍️ *TrenzoKart Delivery*\n\nOrder: #TK" . str_pad($order_id, 5, '0', STR_PAD_LEFT) . "\nHello {$name}! Your order is out for delivery.\n📦 Product: {$product}\n💰 Amount: ₹{$amount} ({$payment})\n📍 Address: {$address}\n\nPlease be available to receive your order. Thank you!");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0"/>
  <title>Order #TK<?= str_pad($order_id, 5, '0', STR_PAD_LEFT) ?> — Delivery Details</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --accent: #d4622a; --brown: #5c3d1e; --cream: #f5efe6; --warm: #e8d5b7; --dark: #1a0f02; --white: #fffdf8; --green: #28a745; --blue: #25D366; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: var(--cream); min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; padding: 0; }

    /* HEADER */
    .header { width: 100%; background: var(--dark); padding: 1rem 1.5rem; display: flex; align-items: center; justify-content: space-between; }
    .logo { font-family: Georgia, serif; font-size: 1.4rem; font-weight: 900; color: white; }
    .logo span { color: #e8a045; }
    .order-id-badge { background: var(--accent); color: white; font-size: 0.8rem; font-weight: 700; padding: 0.3rem 0.8rem; border-radius: 50px; }

    /* MAIN CARD */
    .card { width: 100%; max-width: 480px; margin: 1.5rem auto; padding: 0 1rem; }

    /* STATUS BANNER */
    .status-banner { background: var(--green); color: white; border-radius: 14px; padding: 0.8rem 1.2rem; display: flex; align-items: center; gap: 0.8rem; margin-bottom: 1rem; }
    .status-banner.cod { background: #e65100; }
    .status-icon { font-size: 1.8rem; }
    .status-text { font-size: 0.85rem; font-weight: 600; }
    .status-text strong { font-size: 1rem; display: block; }

    /* CUSTOMER CARD */
    .customer-card { background: var(--white); border-radius: 20px; overflow: hidden; box-shadow: 0 4px 24px rgba(92,61,30,0.1); margin-bottom: 1rem; }
    .customer-header { background: var(--brown); padding: 1rem 1.2rem; display: flex; align-items: center; gap: 0.8rem; }
    .avatar { width: 46px; height: 46px; border-radius: 50%; background: var(--accent); color: white; font-size: 1.3rem; font-weight: 900; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .customer-name { color: white; font-size: 1.1rem; font-weight: 700; }
    .customer-label { color: rgba(255,255,255,0.6); font-size: 0.75rem; }
    .customer-body { padding: 1.2rem; }
    .info-row { display: flex; align-items: flex-start; gap: 0.8rem; padding: 0.7rem 0; border-bottom: 1px solid var(--warm); }
    .info-row:last-child { border-bottom: none; }
    .info-icon { font-size: 1.2rem; flex-shrink: 0; margin-top: 0.1rem; }
    .info-content { flex: 1; }
    .info-label { font-size: 0.7rem; color: #999; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.2rem; }
    .info-value { font-size: 0.95rem; color: var(--dark); font-weight: 600; line-height: 1.4; }

    /* ACTION BUTTONS */
    .action-btns { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; margin-bottom: 1rem; }
    .btn-call { background: linear-gradient(135deg, #28a745, #1e7e34); color: white; border: none; border-radius: 14px; padding: 1rem; font-size: 1rem; font-weight: 700; cursor: pointer; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem; transition: all .2s; box-shadow: 0 4px 16px rgba(40,167,69,0.3); }
    .btn-call:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(40,167,69,0.4); }
    .btn-wa { background: linear-gradient(135deg, #25D366, #128C7E); color: white; border: none; border-radius: 14px; padding: 1rem; font-size: 1rem; font-weight: 700; cursor: pointer; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem; transition: all .2s; box-shadow: 0 4px 16px rgba(37,211,102,0.3); }
    .btn-wa:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(37,211,102,0.4); }
    .btn-icon { font-size: 1.3rem; }

    /* ORDER DETAILS */
    .order-card { background: var(--white); border-radius: 20px; padding: 1.2rem; box-shadow: 0 4px 24px rgba(92,61,30,0.08); margin-bottom: 1rem; }
    .order-card-title { font-size: 0.75rem; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.8rem; }
    .order-detail-row { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid var(--warm); }
    .order-detail-row:last-child { border-bottom: none; }
    .od-label { font-size: 0.82rem; color: #888; }
    .od-value { font-size: 0.88rem; font-weight: 700; color: var(--dark); text-align: right; max-width: 60%; }
    .cod-badge { background: #fff3e0; color: #e65100; border: 1.5px solid #ffcc80; padding: 0.2rem 0.7rem; border-radius: 50px; font-size: 0.78rem; font-weight: 700; }
    .paid-badge { background: #e8f5e9; color: #2e7d32; border: 1.5px solid #a5d6a7; padding: 0.2rem 0.7rem; border-radius: 50px; font-size: 0.78rem; font-weight: 700; }

    /* FOOTER */
    .footer { text-align: center; padding: 1rem; color: #aaa; font-size: 0.75rem; }

    /* PULSE animation on call btn */
    @keyframes pulse { 0%,100% { box-shadow: 0 4px 16px rgba(40,167,69,0.3); } 50% { box-shadow: 0 4px 28px rgba(40,167,69,0.6); } }
    .btn-call { animation: pulse 2s infinite; }
  </style>
</head>
<body>

<div class="header">
  <div class="logo">Trenzo<span>Kart</span></div>
  <div class="order-id-badge">#TK<?= str_pad($order_id, 5, '0', STR_PAD_LEFT) ?></div>
</div>

<div class="card">

  <!-- STATUS BANNER -->
  <div class="status-banner <?= $payment === 'COD' ? 'cod' : '' ?>">
    <div class="status-icon"><?= $payment === 'COD' ? '💵' : '✅' ?></div>
    <div class="status-text">
      <strong><?= $payment === 'COD' ? 'Cash on Delivery — Collect ₹'.$amount : 'Prepaid — Already Paid' ?></strong>
      Order Status: <?= $status ?>
    </div>
  </div>

  <!-- CUSTOMER CARD -->
  <div class="customer-card">
    <div class="customer-header">
      <div class="avatar"><?= strtoupper(substr($name, 0, 1)) ?></div>
      <div>
        <div class="customer-name"><?= htmlspecialchars($name) ?></div>
        <div class="customer-label">📦 Delivery Customer</div>
      </div>
    </div>
    <div class="customer-body">
      <div class="info-row">
        <div class="info-icon">📞</div>
        <div class="info-content">
          <div class="info-label">Phone Number</div>
          <div class="info-value"><?= htmlspecialchars($phone) ?></div>
        </div>
      </div>
      <div class="info-row">
        <div class="info-icon">📍</div>
        <div class="info-content">
          <div class="info-label">Delivery Address</div>
          <div class="info-value"><?= htmlspecialchars($address) ?></div>
        </div>
      </div>
      <div class="info-row">
        <div class="info-icon">✉️</div>
        <div class="info-content">
          <div class="info-label">Email</div>
          <div class="info-value"><?= htmlspecialchars($o['customer_email']) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- CALL + WHATSAPP BUTTONS -->
  <div class="action-btns">
    <a href="tel:<?= $phone ?>" class="btn-call">
      <span class="btn-icon">📞</span> Call Now
    </a>
    <a href="https://wa.me/91<?= $phone ?>?text=<?= $wa_text ?>" target="_blank" class="btn-wa">
      <span class="btn-icon">💬</span> WhatsApp
    </a>
  </div>

  <!-- ORDER DETAILS -->
  <div class="order-card">
    <div class="order-card-title">📦 Order Details</div>
    <div class="order-detail-row">
      <div class="od-label">Order ID</div>
      <div class="od-value">#TK<?= str_pad($order_id, 5, '0', STR_PAD_LEFT) ?></div>
    </div>
    <div class="order-detail-row">
      <div class="od-label">Product</div>
      <div class="od-value"><?= htmlspecialchars($product) ?></div>
    </div>
    <div class="order-detail-row">
      <div class="od-label">Order Date</div>
      <div class="od-value"><?= $date ?></div>
    </div>
    <div class="order-detail-row">
      <div class="od-label">Total Amount</div>
      <div class="od-value">₹<?= $amount ?></div>
    </div>
    <div class="order-detail-row">
      <div class="od-label">Payment</div>
      <div class="od-value">
        <span class="<?= $payment === 'COD' ? 'cod-badge' : 'paid-badge' ?>"><?= $payment ?></span>
      </div>
    </div>
    <?php if (!empty($o['tracking_number'])): ?>
    <div class="order-detail-row">
      <div class="od-label">Courier</div>
      <div class="od-value"><?= htmlspecialchars($o['courier_name']) ?></div>
    </div>
    <div class="order-detail-row">
      <div class="od-label">Tracking #</div>
      <div class="od-value"><?= htmlspecialchars($o['tracking_number']) ?></div>
    </div>
    <?php endif; ?>
  </div>

</div>

<div class="footer">TrenzoKart Delivery System • Scan QR on package to view details</div>

</body>
</html>