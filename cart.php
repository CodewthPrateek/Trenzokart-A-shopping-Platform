<?php
require 'config.php';

$user_id   = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? null;
$items     = [];
$total     = 0;

if ($user_id) {
    // Merge guest cart into DB cart if exists
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
    // Fetch from DB
    $sql    = "SELECT cart.id, cart.quantity, products.id as product_id, products.name, products.price, products.category, products.image
               FROM cart JOIN products ON cart.product_id = products.id WHERE cart.user_id = '$user_id'";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) { $items[] = $row; $total += $row['price'] * $row['quantity']; }
} elseif (!empty($_SESSION['guest_cart'])) {
    // Guest cart from session
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
  <style>*,
*::before,
*::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}
:root {
  --cream: #f5efe6;
  --warm: #e8d5b7;
  --brown: #5c3d1e;
  --accent: #d4622a;
  --accent2: #e8a045;
  --text: #2d1a0a;
  --muted: #8a6a4a;
  --white: #fffdf8;
  --dark: #1a0f02;
}
body {
  font-family: "DM Sans", sans-serif;
  background: var(--cream);
  color: var(--text);
}
nav {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  background: rgba(245, 239, 230, 0.96);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--warm);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 5vw;
  height: 68px;
}
.logo {
  font-family: "Playfair Display", serif;
  font-size: 1.8rem;
  font-weight: 900;
  color: var(--brown);
  text-decoration: none;
}
.logo span {
  color: var(--accent);
}
.nav-right {
  display: flex;
  align-items: center;
  gap: 1rem;
}
.nav-btn {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.5rem 1rem;
  border-radius: 50px;
  font-family: "DM Sans", sans-serif;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s;
}
.btn-home {
  background: var(--warm);
  color: var(--brown);
  border: none;
}
.btn-home:hover {
  background: var(--accent);
  color: white;
}
.btn-logout {
  background: none;
  color: var(--muted);
  border: 1.5px solid var(--warm);
}
.btn-logout:hover {
  border-color: var(--accent);
  color: var(--accent);
}

.main {
  margin-top: 68px;
  padding: 3rem 5vw;
}
h1 {
  font-family: "Playfair Display", serif;
  font-size: 2rem;
  color: var(--brown);
  margin-bottom: 2rem;
}

.cart-layout {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 2rem;
}

.cart-items {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.cart-item {
  background: var(--white);
  border-radius: 16px;
  padding: 1.2rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 2px 12px rgba(92, 61, 30, 0.06);
}
.item-icon {
  width: 60px;
  height: 60px;
  background: var(--warm);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  flex-shrink: 0;
}
.item-info {
  flex: 1;
}
.item-name {
  font-weight: 600;
  color: var(--text);
  margin-bottom: 0.2rem;
}
.item-cat {
  font-size: 0.78rem;
  color: var(--muted);
}
.item-price {
  font-family: "Playfair Display", serif;
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--brown);
}
.item-qty {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.qty-btn {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 1.5px solid var(--warm);
  background: var(--cream);
  cursor: pointer;
  font-size: 1rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}
.qty-btn:hover {
  background: var(--accent);
  color: white;
  border-color: var(--accent);
}
.qty-num {
  font-weight: 600;
  min-width: 24px;
  text-align: center;
}
.remove-btn {
  background: none;
  border: none;
  color: var(--muted);
  cursor: pointer;
  font-size: 1.2rem;
  transition: color 0.2s;
}
.remove-btn:hover {
  color: #e53e3e;
}

.cart-summary {
  background: var(--white);
  border-radius: 20px;
  padding: 2rem;
  height: fit-content;
  box-shadow: 0 4px 20px rgba(92, 61, 30, 0.08);
}
.summary-title {
  font-family: "Playfair Display", serif;
  font-size: 1.3rem;
  color: var(--brown);
  margin-bottom: 1.5rem;
}
.summary-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.8rem;
  font-size: 0.9rem;
  color: var(--muted);
}
.summary-total {
  display: flex;
  justify-content: space-between;
  font-weight: 700;
  font-size: 1.1rem;
  color: var(--text);
  border-top: 1.5px solid var(--warm);
  padding-top: 1rem;
  margin-top: 1rem;
}
.btn-checkout {
  width: 100%;
  padding: 0.9rem;
  background: var(--accent);
  color: white;
  border: none;
  border-radius: 12px;
  font-family: "DM Sans", sans-serif;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  margin-top: 1.5rem;
  transition: all 0.2s;
}
.btn-checkout:hover {
  background: #c0551f;
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(212, 98, 42, 0.3);
}

.empty-cart {
  text-align: center;
  padding: 4rem;
  color: var(--muted);
}
.empty-cart span {
  font-size: 4rem;
  display: block;
  margin-bottom: 1rem;
}
.empty-cart a {
  color: var(--accent);
  text-decoration: none;
  font-weight: 600;
}

@media (max-width: 768px) {
  .cart-layout {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 600px) {
  nav { height: auto; flex-wrap: wrap; padding: 0.6rem 4vw; gap: 0.5rem; }
  .logo { font-size: 1.4rem; }
  .nav-right { gap: 0.5rem; }
  .nav-btn { padding: 0.4rem 0.7rem; font-size: 0.78rem; }
  .main { margin-top: 90px; padding: 1rem 4vw; }
  h1 { font-size: 1.4rem; }
}
@media (max-width: 500px) {
  .main { padding: 1rem 4vw; }
  h1 { font-size: 1.5rem; }
  .cart-item { flex-wrap: wrap; gap: 0.6rem; padding: 0.9rem; }
  .item-icon { width: 50px; height: 50px; font-size: 1.5rem; }
  .item-price { font-size: 0.95rem; }
  .item-name { font-size: 0.88rem; }
  .cart-summary { padding: 1.2rem; }
}
</style>
</head>
<body>
<nav>
  <a href="index.php" class="logo">Trenzo<span>Kart</span></a>
  <div class="nav-right">
    <a href="index.php" class="nav-btn btn-home">🏠 Home</a>
    <?php if ($user_name): ?>
      <a href="logout.php" class="nav-btn btn-logout">Logout</a>
    <?php else: ?>
      <a href="login.php" class="nav-btn btn-home">Login</a>
      <a href="signup.php" class="nav-btn btn-logout">Sign Up</a>
    <?php endif; ?>
  </div>
</nav>

<?php
$total_items = array_sum(array_column($items, 'quantity'));
?>

<div class="main">
  <h1>🛒 Your Cart 
    <?php if (!empty($items)): ?>
      <span style="font-family:'DM Sans',sans-serif;font-size:1rem;font-weight:600;background:var(--accent);color:white;padding:0.2rem 0.7rem;border-radius:50px;margin-left:0.5rem;"><?= $total_items ?> item<?= $total_items > 1 ? 's' : '' ?></span>
    <?php endif; ?>
  </h1>

  <?php if (!$user_name): ?>
  <!-- GUEST LOGIN BANNER -->
  <div style="background:linear-gradient(135deg,#fff5f0,#ffe8d6);border:1.5px solid var(--warm);border-radius:16px;padding:1.2rem 1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
      <div style="font-weight:700;color:var(--brown);font-size:0.95rem;margin-bottom:0.2rem;">👤 Login to save your cart & place order!</div>
      <div style="font-size:0.82rem;color:var(--muted);">Your cart items are saved temporarily. Login to checkout.</div>
    </div>
    <div style="display:flex;gap:0.7rem;">
      <a href="login.php" style="padding:0.55rem 1.3rem;background:var(--accent);color:white;border-radius:50px;font-size:0.85rem;font-weight:700;text-decoration:none;">Login →</a>
      <a href="signup.php" style="padding:0.55rem 1.3rem;background:var(--warm);color:var(--brown);border-radius:50px;font-size:0.85rem;font-weight:700;text-decoration:none;">Sign Up</a>
    </div>
  </div>
  <?php endif; ?>

  <?php if (empty($items)): ?>
    <div class="empty-cart">
      <span>🛒</span>
      <p>Your cart is empty!</p>
      <br>
      <a href="index.php">Continue Shopping →</a>
    </div>
  <?php else: ?>
    <div class="cart-layout">
      <div class="cart-items">
        <?php
        $icons = ['Clothes' => '👕', 'Electronics' => '📱', 'Grocery' => '🥗'];
        foreach ($items as $item):
          $icon = $icons[$item['category']] ?? '📦';
          $pid  = $item['product_id'] ?? $item['id'];
        ?>
        <div class="cart-item">
          <div class="item-icon" onclick="window.location.href='product.php?id=<?= $pid ?>'" style="cursor:pointer;">
            <?php if (!empty($item['image'])): ?>
              <img src="<?= htmlspecialchars($item['image']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:10px;" alt=""/>
            <?php else: ?>
              <?= $icon ?>
            <?php endif; ?>
          </div>
          <div class="item-info">
            <div class="item-name" onclick="window.location.href='product.php?id=<?= $pid ?>'" style="cursor:pointer;"><?= htmlspecialchars($item['name']) ?></div>
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
            <button class="remove-btn" onclick="removeItem(<?= $item['id'] ?>)">🗑️</button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="cart-summary">
        <div class="summary-title">Order Summary</div>
        <div class="summary-row"><span>Subtotal</span><span>₹<?= number_format($total, 2) ?></span></div>
        <div class="summary-row"><span>Delivery</span><span><?= $total >= 499 ? 'FREE' : '₹49' ?></span></div>
        <div class="summary-total">
          <span>Total</span>
          <span>₹<?= number_format($total >= 499 ? $total : $total + 49, 2) ?></span>
        </div>
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
    .then(data => {
      if (data.status === 'removed') {
        location.reload();
      } else {
        document.getElementById('qty-' + cartId).textContent = data.qty;
        location.reload();
      }
    });
}
function removeItem(cartId) {
  fetch('update_cart.php?id=' + cartId + '&action=remove')
    .then(() => location.reload());
}
</script>
</body>
</html>