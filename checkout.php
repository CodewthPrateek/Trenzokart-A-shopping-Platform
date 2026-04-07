<?php
require 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$sql = "SELECT cart.id, cart.quantity, products.name, products.price, products.category 
        FROM cart 
        JOIN products ON cart.product_id = products.id 
        WHERE cart.user_id = '$user_id'";
$result = mysqli_query($conn, $sql);
$items  = [];
$total  = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
    $total  += $row['price'] * $row['quantity'];
}

if (empty($items)) { header("Location: cart.php"); exit(); }

$delivery    = $total >= 499 ? 0 : 49;
$grand_total = $total + $delivery;

// Fetch saved addresses
$saved_addresses = [];
$addr_result = mysqli_query($conn, "SELECT * FROM user_addresses WHERE user_id='$user_id' ORDER BY is_default DESC, id DESC");
while ($row = mysqli_fetch_assoc($addr_result)) { $saved_addresses[] = $row; }
$default_addr = !empty($saved_addresses) ? $saved_addresses[0] : null;

// UPI ID
$upi_id     = "prateekverma@upi"; // CHANGE THIS to your UPI ID
$upi_name   = "TrenzoKart";
$upi_qr_url = "upi://pay?pa=" . urlencode($upi_id) . "&pn=" . urlencode($upi_name) . "&am=" . $grand_total . "&cu=INR&tn=TrenzoKart+Order";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — Checkout</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --cream: #f5efe6; --warm: #e8d5b7; --brown: #5c3d1e; --accent: #d4622a; --accent2: #e8a045; --text: #2d1a0a; --muted: #8a6a4a; --white: #fffdf8; --dark: #1a0f02; }
    body { font-family: "DM Sans", sans-serif; background: var(--cream); color: var(--text); }
    nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; background: rgba(245,239,230,0.96); backdrop-filter: blur(12px); border-bottom: 1px solid var(--warm); display: flex; align-items: center; justify-content: space-between; padding: 0 5vw; height: 68px; }
    .logo { font-family: "Playfair Display", serif; font-size: 1.8rem; font-weight: 900; color: var(--brown); text-decoration: none; }
    .logo span { color: var(--accent); }
    .back-btn { display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: 50px; background: var(--warm); color: var(--brown); text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: all .2s; }
    .back-btn:hover { background: var(--accent); color: white; }
    .main { margin-top: 68px; padding: 3rem 5vw; }
    .steps { display: flex; align-items: center; justify-content: center; gap: 0; margin-bottom: 3rem; }
    .step { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 600; color: var(--muted); }
    .step.active { color: var(--accent); }
    .step.done { color: var(--brown); }
    .step-num { width: 32px; height: 32px; border-radius: 50%; background: var(--warm); color: var(--muted); display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700; }
    .step.active .step-num { background: var(--accent); color: white; }
    .step.done .step-num { background: var(--brown); color: white; }
    .step-line { width: 60px; height: 2px; background: var(--warm); margin: 0 0.5rem; }
    .step-line.done { background: var(--brown); }
    h1 { font-family: "Playfair Display", serif; font-size: 2rem; color: var(--brown); margin-bottom: 2rem; }
    .checkout-layout { display: grid; grid-template-columns: 1fr 360px; gap: 2rem; }
    .address-form { background: var(--white); border-radius: 20px; padding: 2rem; box-shadow: 0 4px 20px rgba(92,61,30,0.06); }
    .form-title { font-family: "Playfair Display", serif; font-size: 1.3rem; color: var(--brown); margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1.5px solid var(--warm); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-size: 0.82rem; font-weight: 600; color: var(--brown); margin-bottom: 0.4rem; }
    .form-group input, .form-group select { width: 100%; padding: 0.75rem 1rem; border: 1.5px solid var(--warm); border-radius: 10px; font-family: "DM Sans", sans-serif; font-size: 0.92rem; color: var(--text); background: var(--cream); outline: none; transition: border-color .2s, box-shadow .2s; }
    .form-group input:focus, .form-group select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(212,98,42,0.12); background: var(--white); }
    .payment-title { font-family: "Playfair Display", serif; font-size: 1.3rem; color: var(--brown); margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1.5px solid var(--warm); }
    .payment-options { display: flex; flex-direction: column; gap: 0.8rem; }
    .payment-option { display: flex; align-items: center; gap: 0.8rem; padding: 1rem; border-radius: 12px; border: 1.5px solid var(--warm); cursor: pointer; transition: all .2s; background: var(--cream); }
    .payment-option:hover { border-color: var(--accent); background: #fff5f0; }
    .payment-option input[type="radio"] { accent-color: var(--accent); width: 16px; height: 16px; }
    .payment-option.selected { border-color: var(--accent); background: #fff5f0; }
    .payment-icon { font-size: 1.5rem; }
    .payment-label { font-weight: 600; color: var(--text); font-size: 0.9rem; }
    .payment-desc { font-size: 0.75rem; color: var(--muted); }

    /* UPI SECTION */
    .upi-section { display: none; margin-top: 1.2rem; background: #f0f9ff; border: 1.5px solid #bee3f8; border-radius: 14px; padding: 1.5rem; }
    .upi-section.show { display: block; }
    .upi-title { font-weight: 700; color: #2b6cb0; font-size: 0.9rem; margin-bottom: 1rem; }
    .upi-apps { display: flex; gap: 0.8rem; flex-wrap: wrap; margin-bottom: 1.2rem; }
    .upi-app { display: flex; flex-direction: column; align-items: center; gap: 0.3rem; padding: 0.6rem 1rem; background: white; border: 1.5px solid #bee3f8; border-radius: 10px; font-size: 0.75rem; font-weight: 600; color: #2b6cb0; cursor: pointer; transition: all .2s; }
    .upi-app:hover { border-color: #2b6cb0; background: #ebf8ff; }
    .upi-app span { font-size: 1.8rem; }
    .upi-id-box { background: white; border: 1.5px dashed #2b6cb0; border-radius: 10px; padding: 0.9rem 1.2rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.2rem; }
    .upi-id-text { font-weight: 700; color: #2b6cb0; font-size: 0.95rem; }
    .copy-btn { padding: 0.3rem 0.8rem; background: #2b6cb0; color: white; border: none; border-radius: 6px; font-size: 0.78rem; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; }
    .upi-qr-wrap { text-align: center; margin-bottom: 1rem; }
    .upi-qr-wrap canvas, .upi-qr-wrap img { width: 160px !important; height: 160px !important; border: 1.5px solid #bee3f8; border-radius: 12px; padding: 8px; background: white; }
    .upi-qr-label { font-size: 0.78rem; color: #4a5568; margin-top: 0.4rem; }
    .upi-confirm { margin-top: 1rem; }
    .upi-confirm label { font-size: 0.82rem; font-weight: 600; color: var(--brown); display: block; margin-bottom: 0.4rem; }
    .upi-confirm input { width: 100%; padding: 0.7rem 1rem; border: 1.5px solid var(--warm); border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.88rem; background: var(--cream); outline: none; }
    .upi-confirm input:focus { border-color: var(--accent); background: white; }

    /* CARD SECTION */
    .card-section { display: none; margin-top: 1.2rem; background: #f7fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 1.5rem; }
    .card-section.show { display: block; }
    .card-title { font-weight: 700; color: #2d3748; font-size: 0.9rem; margin-bottom: 1rem; }
    .card-logos { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
    .card-logo { padding: 0.25rem 0.6rem; background: white; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.8rem; font-weight: 700; color: #4a5568; }
    .card-form .form-group { margin-bottom: 0.9rem; }
    .card-form label { font-size: 0.8rem; font-weight: 600; color: #4a5568; display: block; margin-bottom: 0.3rem; }
    .card-form input { width: 100%; padding: 0.7rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.9rem; color: var(--text); background: white; outline: none; transition: border-color .2s; }
    .card-form input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(212,98,42,0.1); }
    .card-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; }
    .secure-note { display: flex; align-items: center; gap: 0.4rem; font-size: 0.75rem; color: #718096; margin-top: 0.5rem; }

    /* SAVED ADDRESS */
    .saved-addr-card { padding: 0.9rem 1rem; border: 1.5px solid var(--warm); border-radius: 12px; cursor: pointer; transition: all .2s; background: var(--cream); }
    .saved-addr-card:hover { border-color: var(--accent); background: #fff5f0; }
    .saved-addr-card.selected { border-color: var(--accent); background: #fff5f0; }
    .saved-addr-card .addr-check { display: none; width: 20px; height: 20px; background: var(--accent); color: white; border-radius: 50%; font-size: 0.7rem; align-items: center; justify-content: center; font-weight: 700; }
    .saved-addr-card.selected .addr-check { display: flex; }

    /* ORDER SUMMARY */
    .order-summary { background: var(--white); border-radius: 20px; padding: 2rem; height: fit-content; box-shadow: 0 4px 20px rgba(92,61,30,0.08); position: sticky; top: 88px; }
    .summary-title { font-family: "Playfair Display", serif; font-size: 1.3rem; color: var(--brown); margin-bottom: 1.5rem; }
    .summary-items { margin-bottom: 1.5rem; }
    .summary-item { display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0; border-bottom: 1px solid rgba(232,213,183,0.5); font-size: 0.88rem; }
    .summary-item:last-child { border-bottom: none; }
    .summary-item-name { color: var(--text); font-weight: 500; flex: 1; }
    .summary-item-qty { color: var(--muted); font-size: 0.78rem; margin: 0 0.5rem; }
    .summary-item-price { color: var(--brown); font-weight: 600; }
    .summary-row { display: flex; justify-content: space-between; margin-bottom: 0.6rem; font-size: 0.9rem; color: var(--muted); }
    .summary-total { display: flex; justify-content: space-between; font-weight: 700; font-size: 1.1rem; color: var(--text); border-top: 1.5px solid var(--warm); padding-top: 1rem; margin-top: 0.5rem; }
    .free-delivery { color: #2d8a4e; font-weight: 600; }
    .btn-place-order { width: 100%; padding: 1rem; background: var(--accent); color: white; border: none; border-radius: 12px; font-family: "DM Sans", sans-serif; font-size: 1rem; font-weight: 700; cursor: pointer; margin-top: 1.5rem; transition: all .2s; }
    .btn-place-order:hover { background: #c0551f; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(212,98,42,0.35); }
    .secure-badge { display: flex; align-items: center; justify-content: center; gap: 0.4rem; margin-top: 1rem; font-size: 0.78rem; color: var(--muted); }

    @media (max-width: 768px) {
      .checkout-layout { grid-template-columns: 1fr; }
      .form-row { grid-template-columns: 1fr; }
      .card-row { grid-template-columns: 1fr; }
      .main { padding: 2rem 4vw; }
      .order-summary { position: static; }
      .steps { flex-wrap: wrap; justify-content: center; }
      .step-line { width: 30px; }
      h1 { font-size: 1.6rem; }
    }
    @media (max-width: 480px) {
      nav { height: auto; flex-wrap: wrap; padding: 0.6rem 4vw; gap: 0.5rem; }
      .logo { font-size: 1.4rem; }
      .main { margin-top: 90px; padding: 1.5rem 4vw; }
      .step span { display: none; }
      .address-form { padding: 1.2rem; }
      .upi-apps { gap: 0.5rem; }
      .upi-app { padding: 0.4rem 0.6rem; font-size: 0.7rem; }
    }
  </style>
</head>
<body>
<nav>
  <a href="index.php" class="logo">Trenzo<span>Kart</span></a>
  <a href="cart.php" class="back-btn">← Back to Cart</a>
</nav>

<div class="main">
  <div class="steps">
    <div class="step done"><div class="step-num">✓</div><span>Cart</span></div>
    <div class="step-line done"></div>
    <div class="step active"><div class="step-num">2</div><span>Address & Payment</span></div>
    <div class="step-line"></div>
    <div class="step"><div class="step-num">3</div><span>Order Confirmed</span></div>
  </div>

  <h1>📦 Delivery Details</h1>

  <form action="place_order.php" method="POST" id="checkoutForm">
    <input type="hidden" name="total_amount" value="<?= $grand_total ?>"/>
    <input type="hidden" name="payment" id="paymentMethod" value="cod"/>
    <input type="hidden" name="upi_txn_id" id="upiTxnId" value=""/>
    <input type="hidden" name="card_last4" id="cardLast4" value=""/>

    <div class="checkout-layout">
      <!-- LEFT -->
      <div>
        <!-- ADDRESS -->
        <div class="address-form">
          <div class="form-title">🏠 Delivery Address</div>

          <?php if (!empty($saved_addresses)): ?>
          <div style="margin-bottom:1.5rem;">
            <div style="font-size:0.82rem;font-weight:600;color:var(--brown);margin-bottom:0.8rem;">📍 Saved Addresses</div>
            <div style="display:flex;flex-direction:column;gap:0.6rem;" id="savedAddrList">
              <?php foreach ($saved_addresses as $i => $addr): ?>
              <div class="saved-addr-card <?= $addr['is_default'] ? 'selected' : '' ?>" onclick="selectAddress(<?= $i ?>, this)"
                   data-index="<?= $i ?>"
                   data-name="<?= htmlspecialchars($addr['full_name']) ?>"
                   data-phone="<?= htmlspecialchars($addr['phone']) ?>"
                   data-addr1="<?= htmlspecialchars($addr['address1']) ?>"
                   data-addr2="<?= htmlspecialchars($addr['address2'] ?? '') ?>"
                   data-city="<?= htmlspecialchars($addr['city']) ?>"
                   data-state="<?= htmlspecialchars($addr['state']) ?>"
                   data-pincode="<?= htmlspecialchars($addr['pincode']) ?>">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                  <div>
                    <span style="background:var(--brown);color:white;font-size:0.7rem;font-weight:700;padding:0.1rem 0.5rem;border-radius:50px;"><?= htmlspecialchars($addr['label']) ?></span>
                    <?php if ($addr['is_default']): ?><span style="color:var(--accent);font-size:0.7rem;font-weight:600;margin-left:0.4rem;">⭐ Default</span><?php endif; ?>
                  </div>
                  <div class="addr-check">✓</div>
                </div>
                <div style="font-weight:700;font-size:0.9rem;margin-top:0.4rem;"><?= htmlspecialchars($addr['full_name']) ?></div>
                <div style="font-size:0.78rem;color:var(--muted);">📞 <?= htmlspecialchars($addr['phone']) ?></div>
                <div style="font-size:0.78rem;color:var(--text);margin-top:0.2rem;"><?= htmlspecialchars($addr['address1']) ?>, <?= htmlspecialchars($addr['city']) ?> — <?= htmlspecialchars($addr['pincode']) ?></div>
              </div>
              <?php endforeach; ?>
              <div class="saved-addr-card" onclick="selectAddress(-1, this)" id="newAddrCard">
                <div style="font-weight:600;font-size:0.88rem;color:var(--accent);">+ Enter a new address</div>
              </div>
            </div>
          </div>
          <div id="newAddrFields" style="display:none;">
          <?php else: ?>
          <div id="newAddrFields">
          <?php endif; ?>

          <div class="form-row">
            <div class="form-group">
              <label>Full Name *</label>
              <input type="text" name="full_name" id="field_name" placeholder="Enter full name" value="<?= htmlspecialchars($default_addr['full_name'] ?? $user_name) ?>" required/>
            </div>
            <div class="form-group">
              <label>Phone Number *</label>
              <input type="tel" name="phone" id="field_phone" placeholder="10-digit mobile number" value="<?= htmlspecialchars($default_addr['phone'] ?? '') ?>" pattern="[0-9]{10}" maxlength="10" required/>
            </div>
          </div>
          <div class="form-group">
            <label>Address Line 1 *</label>
            <input type="text" name="address1" id="field_addr1" placeholder="House no., Building, Street" value="<?= htmlspecialchars($default_addr['address1'] ?? '') ?>" required/>
          </div>
          <div class="form-group">
            <label>Address Line 2</label>
            <input type="text" name="address2" id="field_addr2" placeholder="Area, Colony, Landmark (optional)" value="<?= htmlspecialchars($default_addr['address2'] ?? '') ?>"/>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>City *</label>
              <input type="text" name="city" id="field_city" placeholder="Enter city" value="<?= htmlspecialchars($default_addr['city'] ?? '') ?>" required/>
            </div>
            <div class="form-group">
              <label>Pincode *</label>
              <input type="text" name="pincode" id="field_pincode" placeholder="6-digit pincode" value="<?= htmlspecialchars($default_addr['pincode'] ?? '') ?>" pattern="[0-9]{6}" maxlength="6" required/>
            </div>
          </div>
          <div class="form-group">
            <label>State *</label>
            <select name="state" id="field_state" required>
              <option value="">Select State</option>
              <?php
              $states = ['Andhra Pradesh','Delhi','Gujarat','Haryana','Karnataka','Kerala','Madhya Pradesh','Maharashtra','Punjab','Rajasthan','Tamil Nadu','Telangana','Uttar Pradesh','West Bengal','Other'];
              foreach ($states as $st):
                $sel = ($default_addr['state'] ?? '') === $st ? 'selected' : '';
              ?>
              <option <?= $sel ?>><?= $st ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          </div><!-- end newAddrFields -->
        </div>

        <!-- PAYMENT -->
        <div class="address-form" style="margin-top:1.5rem;">
          <div class="payment-title">💳 Payment Method</div>
          <div class="payment-options">

            <!-- COD -->
            <label class="payment-option selected" onclick="selectPayment('cod',this)">
              <input type="radio" name="pay_type" value="cod" checked/>
              <span class="payment-icon">💵</span>
              <div>
                <div class="payment-label">Cash on Delivery</div>
                <div class="payment-desc">Pay when your order arrives</div>
              </div>
            </label>

            <!-- UPI -->
            <label class="payment-option" onclick="selectPayment('upi',this)">
              <input type="radio" name="pay_type" value="upi"/>
              <span class="payment-icon">📱</span>
              <div>
                <div class="payment-label">UPI Payment</div>
                <div class="payment-desc">Google Pay, PhonePe, Paytm, any UPI</div>
              </div>
            </label>

            <!-- UPI DETAILS -->
            <div class="upi-section" id="upiSection">
              <div class="upi-title">📱 Pay via any UPI App</div>
              <div class="upi-apps">
                <div class="upi-app"><span>📗</span>Google Pay</div>
                <div class="upi-app"><span>💜</span>PhonePe</div>
                <div class="upi-app"><span>🔵</span>Paytm</div>
                <div class="upi-app"><span>🏦</span>BHIM</div>
                <div class="upi-app"><span>📱</span>Any UPI</div>
              </div>
              <div class="upi-id-box">
                <div>
                  <div style="font-size:0.72rem;color:#4a5568;margin-bottom:0.2rem;">UPI ID</div>
                  <div class="upi-id-text"><?= $upi_id ?></div>
                </div>
                <button type="button" class="copy-btn" onclick="copyUPI()">Copy</button>
              </div>
              <!-- ✅ QR Code -->
              <div class="upi-qr-wrap">
                <div id="upiQR"></div>
                <div class="upi-qr-label">Scan QR to pay ₹<?= number_format($grand_total, 2) ?></div>
              </div>
              <div class="upi-confirm">
                <label>Enter UPI Transaction ID (after payment) *</label>
                <input type="text" id="upiTxnInput" placeholder="e.g. 123456789012" oninput="document.getElementById('upiTxnId').value=this.value"/>
              </div>
            </div>

            <!-- CARD -->
            <label class="payment-option" onclick="selectPayment('card',this)">
              <input type="radio" name="pay_type" value="card"/>
              <span class="payment-icon">💳</span>
              <div>
                <div class="payment-label">Credit / Debit Card</div>
                <div class="payment-desc">Visa, Mastercard, RuPay</div>
              </div>
            </label>

            <!-- CARD DETAILS -->
            <div class="card-section" id="cardSection">
              <div class="card-title">💳 Enter Card Details</div>
              <div class="card-logos">
                <span class="card-logo">VISA</span>
                <span class="card-logo">Mastercard</span>
                <span class="card-logo">RuPay</span>
              </div>
              <div class="card-form">
                <div class="form-group">
                  <label>Card Number</label>
                  <input type="text" id="cardNumber" placeholder="1234 5678 9012 3456" maxlength="19" oninput="formatCard(this)"/>
                </div>
                <div class="form-group">
                  <label>Card Holder Name</label>
                  <input type="text" id="cardName" placeholder="Name on card"/>
                </div>
                <div class="card-row">
                  <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="text" id="cardExpiry" placeholder="MM/YY" maxlength="5" oninput="formatExpiry(this)"/>
                  </div>
                  <div class="form-group">
                    <label>CVV</label>
                    <input type="password" id="cardCvv" placeholder="•••" maxlength="3"/>
                  </div>
                </div>
                <div class="secure-note">🔒 Your card details are encrypted and secure</div>
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- RIGHT: ORDER SUMMARY -->
      <div class="order-summary">
        <div class="summary-title">🛍️ Order Summary</div>
        <div class="summary-items">
          <?php foreach ($items as $item): ?>
          <div class="summary-item">
            <span class="summary-item-name"><?= htmlspecialchars($item['name']) ?></span>
            <span class="summary-item-qty">x<?= $item['quantity'] ?></span>
            <span class="summary-item-price">₹<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="summary-row"><span>Subtotal</span><span>₹<?= number_format($total, 2) ?></span></div>
        <div class="summary-row">
          <span>Delivery</span>
          <?php if ($delivery == 0): ?>
            <span class="free-delivery">FREE ✓</span>
          <?php else: ?>
            <span>₹<?= $delivery ?></span>
          <?php endif; ?>
        </div>
        <div class="summary-total">
          <span>Total Amount</span>
          <span>₹<?= number_format($grand_total, 2) ?></span>
        </div>
        <button type="submit" class="btn-place-order" id="placeOrderBtn">
          Place Order → ₹<?= number_format($grand_total, 2) ?>
        </button>
        <div class="secure-badge">🔒 100% Secure & Safe Checkout</div>
      </div>
    </div>
  </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
  const savedAddresses = <?= json_encode($saved_addresses) ?>;

  // ✅ FIX: upiUrl PHP se JS mein correctly pass ho raha hai
  const upiUrl = "<?= addslashes($upi_qr_url) ?>";

  // ✅ QR Code generate karo page load pe
  window.addEventListener('load', () => {
    new QRCode(document.getElementById('upiQR'), {
      text: upiUrl,
      width: 160,
      height: 160,
      colorDark: '#1a0f02',
      colorLight: '#ffffff',
    });

    // Default saved address auto-fill
    <?php if (!empty($saved_addresses)): ?>
    const defaultCard = document.querySelector('.saved-addr-card.selected');
    if (defaultCard) {
      document.getElementById('newAddrFields').style.display = 'block';
    }
    <?php endif; ?>
  });

  function selectAddress(index, el) {
    document.querySelectorAll('.saved-addr-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    const newFields = document.getElementById('newAddrFields');
    newFields.style.display = 'block';

    if (index === -1) {
      document.getElementById('field_name').value    = '';
      document.getElementById('field_phone').value   = '';
      document.getElementById('field_addr1').value   = '';
      document.getElementById('field_addr2').value   = '';
      document.getElementById('field_city').value    = '';
      document.getElementById('field_pincode').value = '';
      document.getElementById('field_state').value   = '';
    } else {
      const addr = savedAddresses[index];
      document.getElementById('field_name').value    = addr.full_name;
      document.getElementById('field_phone').value   = addr.phone;
      document.getElementById('field_addr1').value   = addr.address1;
      document.getElementById('field_addr2').value   = addr.address2 || '';
      document.getElementById('field_city').value    = addr.city;
      document.getElementById('field_pincode').value = addr.pincode;
      document.getElementById('field_state').value   = addr.state;
    }
  }

  function selectPayment(type, el) {
    document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('paymentMethod').value = type;
    document.getElementById('upiSection').classList.toggle('show', type === 'upi');
    document.getElementById('cardSection').classList.toggle('show', type === 'card');
  }

  function copyUPI() {
    navigator.clipboard.writeText("<?= $upi_id ?>").then(() => {
      const btn = document.querySelector('.copy-btn');
      btn.textContent = 'Copied!';
      btn.style.background = '#28a745';
      setTimeout(() => { btn.textContent = 'Copy'; btn.style.background = '#2b6cb0'; }, 2000);
    });
  }

  function formatCard(input) {
    let v = input.value.replace(/\D/g, '').substring(0, 16);
    input.value = v.replace(/(.{4})/g, '$1 ').trim();
    document.getElementById('cardLast4').value = v.slice(-4);
  }

  function formatExpiry(input) {
    let v = input.value.replace(/\D/g, '').substring(0, 4);
    if (v.length >= 2) v = v.slice(0,2) + '/' + v.slice(2);
    input.value = v;
  }

  document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    const method = document.getElementById('paymentMethod').value;
    if (method === 'upi') {
      const txn = document.getElementById('upiTxnInput').value.trim();
      if (!txn) {
        e.preventDefault();
        alert('Please enter UPI Transaction ID after payment!');
        return;
      }
    }
    if (method === 'card') {
      const num  = document.getElementById('cardNumber').value.replace(/\s/g,'');
      const name = document.getElementById('cardName').value.trim();
      const exp  = document.getElementById('cardExpiry').value.trim();
      const cvv  = document.getElementById('cardCvv').value.trim();
      if (num.length < 16 || !name || exp.length < 5 || cvv.length < 3) {
        e.preventDefault();
        alert('Please fill all card details correctly!');
        return;
      }
    }
  });
</script>
</body>
</html>