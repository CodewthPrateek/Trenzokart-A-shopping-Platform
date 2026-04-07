<?php
require 'config.php';

$user_id   = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? null;
$items     = [];
$total     = 0;

if ($user_id) {
    if (!empty($_SESSION['guest_cart'])) {
        foreach ($_SESSION['guest_cart'] as $pid => $qty) {
            $pid = intval($pid); $qty = intval($qty);
            $check = mysqli_query($conn, "SELECT * FROM cart WHERE user_id='$user_id' AND product_id='$pid'");
            if (mysqli_num_rows($check) > 0) {
                mysqli_query($conn, "UPDATE cart SET quantity = quantity + $qty WHERE user_id='$user_id' AND product_id='$pid'");
            } else {
                mysqli_query($conn, "INSERT INTO cart (user_id, product_id, quantity) VALUES ('$user_id','$pid','$qty')");
            }
        }
        unset($_SESSION['guest_cart']);
    }
    $sql    = "SELECT cart.id, cart.quantity, products.id as product_id, products.name, products.price, products.category, products.image
               FROM cart JOIN products ON cart.product_id = products.id WHERE cart.user_id = '$user_id'";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) { $items[] = $row; $total += $row['price'] * $row['quantity']; }
} elseif (!empty($_SESSION['guest_cart'])) {
    foreach ($_SESSION['guest_cart'] as $pid => $qty) {
        $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM products WHERE id='$pid'"));
        if ($p) { $p['quantity'] = $qty; $items[] = $p; $total += $p['price'] * $qty; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — Cart</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --cream: #f5efe6; --warm: #e8d5b7; --brown: #5c3d1e;
      --accent: #d4622a; --text: #2d1a0a; --muted: #8a6a4a;
      --white: #fffdf8;
    }
    body { font-family: "DM Sans", sans-serif; background: var(--cream); color: var(--text); overflow-x: hidden; }

    /* ── NAV ── */
    nav {
      position: fixed; top: 0; left: 0; right: 0; z-index: 100;
      background: rgba(245,239,230,0.97); backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--warm);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 5vw; height: 64px;
      box-shadow: 0 2px 16px rgba(92,61,30,0.08);
    }
    .logo {
      font-family: "Playfair Display", serif; font-size: 1.7rem;
      font-weight: 900; color: var(--brown); text-decoration: none; flex-shrink: 0;
    }
    .logo span { color: var(--accent); }

    .nav-links { display: flex; align-items: center; gap: 0.6rem; }
    .nav-btn {
      display: inline-flex; align-items: center; gap: 0.35rem;
      padding: 0.45rem 1rem; border-radius: 50px;
      font-family: "DM Sans", sans-serif; font-size: 0.84rem; font-weight: 600;
      cursor: pointer; text-decoration: none; transition: all 0.2s; white-space: nowrap; border: none;
    }
    .btn-primary { background: var(--warm); color: var(--brown); }
    .btn-primary:hover { background: var(--accent); color: #fff; }
    .btn-outline { background: none; color: var(--muted); border: 1.5px solid var(--warm); }
    .btn-outline:hover { border-color: var(--accent); color: var(--accent); }

    /* ── MAIN ── */
    .main { margin-top: 64px; padding: 2.5rem 5vw; }
    h1 { font-family: "Playfair Display", serif; font-size: 1.9rem; color: var(--brown); margin-bottom: 1.8rem; }

    /* ── CART LAYOUT ── */
    .cart-layout { display: grid; grid-template-columns: 1fr 320px; gap: 1.5rem; }
    .cart-items { display: flex; flex-direction: column; gap: 0.9rem; }

    .cart-item {
      background: var(--white); border-radius: 14px; padding: 1rem 1.2rem;
      display: flex; align-items: center; gap: 1rem;
      box-shadow: 0 2px 10px rgba(92,61,30,0.06);
    }
    .item-icon {
      width: 58px; height: 58px; background: var(--warm); border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.8rem; flex-shrink: 0; overflow: hidden; cursor: pointer;
    }
    .item-icon img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
    .item-info { flex: 1; min-width: 0; }
    .item-name {
      font-weight: 600; color: var(--text); margin-bottom: 0.15rem;
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: pointer;
    }
    .item-name:hover { color: var(--accent); }
    .item-cat { font-size: 0.76rem; color: var(--muted); }
    .item-price { font-family: "Playfair Display", serif; font-size: 1.05rem; font-weight: 700; color: var(--brown); flex-shrink: 0; }

    .item-qty { display: flex; align-items: center; gap: 0.45rem; flex-shrink: 0; }
    .qty-btn {
      width: 27px; height: 27px; border-radius: 50%; border: 1.5px solid var(--warm);
      background: var(--cream); cursor: pointer; font-size: 1rem; font-weight: 700;
      display: flex; align-items: center; justify-content: center; transition: all 0.2s;
    }
    .qty-btn:hover { background: var(--accent); color: #fff; border-color: var(--accent); }
    .qty-num { font-weight: 600; min-width: 22px; text-align: center; }

    .remove-btn { background: none; border: none; color: var(--muted); cursor: pointer; font-size: 1.1rem; transition: color 0.2s; flex-shrink: 0; }
    .remove-btn:hover { color: #e53e3e; }

    /* ── SUMMARY ── */
    .cart-summary {
      background: var(--white); border-radius: 18px; padding: 1.8rem;
      height: fit-content; box-shadow: 0 4px 20px rgba(92,61,30,0.08);
    }
    .summary-title { font-family: "Playfair Display", serif; font-size: 1.25rem; color: var(--brown); margin-bottom: 1.3rem; }
    .summary-row { display: flex; justify-content: space-between; margin-bottom: 0.75rem; font-size: 0.88rem; color: var(--muted); }
    .summary-total {
      display: flex; justify-content: space-between; font-weight: 700; font-size: 1.05rem;
      color: var(--text); border-top: 1.5px solid var(--warm); padding-top: 0.9rem; margin-top: 0.9rem;
    }
    .btn-checkout {
      width: 100%; padding: 0.85rem; background: var(--accent); color: #fff; border: none;
      border-radius: 10px; font-family: "DM Sans", sans-serif; font-size: 0.95rem;
      font-weight: 600; cursor: pointer; margin-top: 1.3rem; transition: all 0.2s;
    }
    .btn-checkout:hover { background: #c0551f; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(212,98,42,0.28); }

    /* ── EMPTY ── */
    .empty-cart { text-align: center; padding: 4rem 1rem; color: var(--muted); }
    .empty-cart .empty-icon { font-size: 4rem; display: block; margin-bottom: 1rem; }
    .empty-cart a { color: var(--accent); text-decoration: none; font-weight: 600; }

    /* ── GUEST BANNER ── */
    .guest-banner {
      background: linear-gradient(135deg,#fff5f0,#ffe8d6);
      border: 1.5px solid var(--warm); border-radius: 14px;
      padding: 1.1rem 1.4rem; margin-bottom: 1.4rem;
      display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.9rem;
    }
    .guest-banner-btns { display: flex; gap: 0.6rem; }
    .guest-banner-btns a {
      padding: 0.5rem 1.2rem; border-radius: 50px; font-size: 0.83rem;
      font-weight: 700; text-decoration: none;
    }
    .gb-login { background: var(--accent); color: #fff; }
    .gb-signup { background: var(--warm); color: var(--brown); }

    /* ── RESPONSIVE ── */
    @media (max-width: 860px) {
      .cart-layout { grid-template-columns: 1fr 280px; }
    }
    @media (max-width: 680px) {
      .cart-layout { grid-template-columns: 1fr; }
      .main { padding: 1.5rem 4vw; }
      h1 { font-size: 1.5rem; }
    }
    @media (max-width: 480px) {
      nav { padding: 0 4vw; height: 58px; }
      .logo { font-size: 1.35rem; }
      .nav-btn { padding: 0.38rem 0.75rem; font-size: 0.78rem; }
      .main { margin-top: 58px; padding: 1.2rem 4vw; }
      h1 { font-size: 1.3rem; }
      .cart-item { flex-wrap: wrap; padding: 0.85rem; gap: 0.55rem; }
      .item-icon { width: 48px; height: 48px; font-size: 1.5rem; }
      .item-price { font-size: 0.92rem; }
      .item-name { font-size: 0.86rem; }
      .cart-summary { padding: 1.1rem; }
      .summary-title { font-size: 1.1rem; }
    }
    @media (max-width: 360px) {
      .logo { font-size: 1.2rem; }
      .nav-btn { padding: 0.35rem 0.6rem; font-size: 0.74rem; }
    }
  </style>
</head>
<body>

<nav>
  <a href="index.php" class="logo">Trenzo<span>Kart</span></a>
  <div class="nav-links">
    <a href="index.php" class="nav-btn btn-primary">🏠 Home</a>
    <?php if ($user_name): ?>
      <a href="logout.php" class="nav-btn btn-outline">🚪 Logout</a>
    <?php else: ?>
      <a href="login.php" class="nav-btn btn-primary">Login</a>
      <a href="signup.php" class="nav-btn btn-outline">Sign Up</a>
    <?php endif; ?>
  </div>
</nav>

<?php $total_items = array_sum(array_column($items, 'quantity')); ?>

<div class="main">
  <h1>🛒 Your Cart
    <?php if (!empty($items)): ?>
      <span style="font-family:'DM Sans',sans-serif;font-size:0.95rem;font-weight:600;background:var(--accent);color:white;padding:0.18rem 0.65rem;border-radius:50px;margin-left:0.5rem;vertical-align:middle;">
        <?= $total_items ?> item<?= $total_items > 1 ? 's' : '' ?>
      </span>
    <?php endif; ?>
  </h1>

  <?php if (!$user_name): ?>
  <div class="guest-banner">
    <div>
      <div style="font-weight:700;color:var(--brown);font-size:0.92rem;margin-bottom:0.2rem;">👤 Login to save your cart &amp; place order!</div>
      <div style="font-size:0.8rem;color:var(--muted);">Your cart items are saved temporarily. Login to checkout.</div>
    </div>
    <div class="guest-banner-btns">
      <a href="login.php" class="gb-login">Login →</a>
      <a href="signup.php" class="gb-signup">Sign Up</a>
    </div>
  </div>
  <?php endif; ?>

  <?php if (empty($items)): ?>
    <div class="empty-cart">
      <span class="empty-icon">🛒</span>
      <p style="font-size:1.1rem;margin-bottom:1rem;">Your cart is empty!</p>
      <a href="index.php">Continue Shopping →</a>
    </div>
  <?php else: ?>
    <div class="cart-layout">

      <!-- Items -->
      <div class="cart-items">
        <?php
        $icons = ['Clothes' => '👕', 'Electronics' => '📱', 'Grocery' => '🥗'];
        foreach ($items as $item):
          $icon = $icons[$item['category']] ?? '📦';
          $pid  = $item['product_id'] ?? $item['id'];
        ?>
        <div class="cart-item">
          <div class="item-icon" onclick="window.location.href='product.php?id=<?= $pid ?>'">
            <?php if (!empty($item['image'])): ?>
              <img src="<?= htmlspecialchars($item['image']) ?>" alt="" onerror="this.style.display='none'"/>
            <?php else: ?>
              <?= $icon ?>
            <?php endif; ?>
          </div>
          <div class="item-info">
            <div class="item-name" onclick="window.location.href='product.php?id=<?= $pid ?>'"><?= htmlspecialchars($item['name']) ?></div>
            <div class="item-cat"><?= htmlspecialchars($item['category']) ?></div>
          </div>
          <div class="item-price">₹<?= number_format($item['price'], 2) ?></div>
          <div class="item-qty">
            <?php if ($user_id): ?>
              <button class="qty-btn" onclick="updateQty(<?= $item['id'] ?>, 'decrease')">−</button>
              <span class="qty-num" id="qty-<?= $item['id'] ?>"><?= $item['quantity'] ?></span>
              <button class="qty-btn" onclick="updateQty(<?= $item['id'] ?>, 'increase')">+</button>
            <?php else: ?>
              <span class="qty-num"><?= $item['quantity'] ?></span>
            <?php endif; ?>
          </div>
          <?php if ($user_id): ?>
            <button class="remove-btn" onclick="removeItem(<?= $item['id'] ?>)" title="Remove">🗑️</button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Summary -->
      <div class="cart-summary">
        <div class="summary-title">Order Summary</div>
        <div class="summary-row"><span>Subtotal</span><span>₹<?= number_format($total, 2) ?></span></div>
        <div class="summary-row">
          <span>Delivery</span>
          <span><?= $total >= 499 ? '<span style="color:#2d8a4e;font-weight:600;">FREE</span>' : '₹49' ?></span>
        </div>
        <div class="summary-total">
          <span>Total</span>
          <span>₹<?= number_format($total >= 499 ? $total : $total + 49, 2) ?></span>
        </div>
        <?php if ($total < 499): ?>
          <div style="font-size:0.78rem;color:var(--muted);margin-top:0.5rem;text-align:center;">
            Add ₹<?= number_format(499 - $total, 2) ?> more for FREE delivery!
          </div>
        <?php endif; ?>
        <?php if ($user_id): ?>
          <button class="btn-checkout" onclick="window.location.href='checkout.php'">Place Order →</button>
        <?php else: ?>
          <button class="btn-checkout" onclick="window.location.href='login.php?redirect=checkout'">🔐 Login to Checkout →</button>
        <?php endif; ?>
      </div>

    </div>
  <?php endif; ?>
</div>

<script>
function updateQty(cartId, action) {
  fetch('update_cart.php?id=' + cartId + '&action=' + action)
    .then(r => r.json())
    .then(data => { location.reload(); });
}
function removeItem(cartId) {
  fetch('update_cart.php?id=' + cartId + '&action=remove')
    .then(() => location.reload());
}
</script>
</body>
</html>