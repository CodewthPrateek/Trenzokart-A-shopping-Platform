<?php
require '../config.php';
if (!isset($_SESSION['vendor_id'])) { header("Location: login.php"); exit(); }
$vendor_id = $_SESSION['vendor_id'];
$success = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_boy'])) {
    $name     = mysqli_real_escape_string($conn, trim($_POST['name']));
    $phone    = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $email    = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = trim($_POST['password']);
    $exists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM delivery_boys WHERE phone='$phone'"));
    if ($exists) { $error = "Is phone number se already account hai!"; }
    elseif (strlen($password) < 6) { $error = "Password minimum 6 characters!"; }
    else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        mysqli_query($conn, "INSERT INTO delivery_boys (name, phone, email, password, vendor_id) VALUES ('$name','$phone','$email','$hashed','$vendor_id')");
        $success = "Delivery boy added successfully!";
    }
}

if (isset($_GET['delete'])) {
    $bid = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM delivery_boys WHERE id='$bid' AND vendor_id='$vendor_id'");
    $success = "Delivery boy removed!";
}

$boys = mysqli_query($conn, "SELECT * FROM delivery_boys WHERE vendor_id='$vendor_id' ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — Delivery Boys</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --cream: #f5efe6; --warm: #e8d5b7; --brown: #5c3d1e; --accent: #d4622a; --accent2: #e8a045; --text: #2d1a0a; --muted: #8a6a4a; --white: #fffdf8; --dark: #1a0f02; }
    body { font-family: 'DM Sans', sans-serif; background: var(--cream); color: var(--text); overflow-x: hidden; }

    nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; background: var(--dark); display: flex; align-items: center; justify-content: space-between; padding: 0 5vw; height: 65px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
    .logo { font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 900; color: var(--white); text-decoration: none; flex-shrink: 0; }
    .logo span { color: var(--accent2); }
    .nav-right { display: flex; gap: 0.8rem; align-items: center; }
    .nav-link { padding: 0.45rem 1.1rem; border: 1.5px solid rgba(255,255,255,0.25); border-radius: 50px; color: rgba(255,255,255,0.7); font-size: 0.82rem; font-weight: 600; text-decoration: none; transition: all .2s; white-space: nowrap; }
    .nav-link:hover { border-color: var(--accent2); color: var(--accent2); }

    /* HAMBURGER */
    .hamburger { display: none; flex-direction: column; gap: 5px; cursor: pointer; background: none; border: none; padding: 6px; flex-shrink: 0; }
    .hamburger span { display: block; width: 24px; height: 2.5px; background: rgba(255,255,255,0.85); border-radius: 4px; transition: all 0.3s ease; }
    .hamburger.open span:nth-child(1) { transform: translateY(7.5px) rotate(45deg); }
    .hamburger.open span:nth-child(2) { opacity: 0; transform: scaleX(0); }
    .hamburger.open span:nth-child(3) { transform: translateY(-7.5px) rotate(-45deg); }

    /* OVERLAY — sirf sidebar ke baahri hissa cover karta hai, sidebar ko nahi */
    .nav-overlay {
      display: none;
      position: fixed;
      top: 0; left: 0; bottom: 0;
      right: 260px; /* sidebar width = 260px — overlay usse aage nahi jayega */
      background: rgba(0,0,0,0.5);
      z-index: 500;
      cursor: pointer;
    }
    .nav-overlay.open { display: block; }

    /* SIDEBAR */
    @media (max-width: 768px) {
      .hamburger { display: flex; }
      .nav-right {
        position: fixed;
        top: 0; right: -270px; bottom: 0;
        width: 260px;
        background: #150a02;
        flex-direction: column;
        align-items: stretch;
        padding: 75px 1.2rem 2rem;
        gap: 0.5rem;
        z-index: 600; /* overlay(500) se upar — links clickable */
        box-shadow: -6px 0 30px rgba(0,0,0,0.55);
        transition: right 0.3s ease;
        overflow-y: auto;
      }
      .nav-right.open { right: 0; }
      .nav-link { width: 100%; border-radius: 10px; padding: 0.75rem 1rem; font-size: 0.9rem; display: block; text-align: left; }
      .nav-overlay { right: 260px; }
    }
    @media (max-width: 480px) {
      nav { padding: 0 3vw; }
      .logo { font-size: 1.3rem; }
      .nav-right { width: 240px; right: -250px; }
      .nav-right.open { right: 0; }
      .nav-overlay { right: 240px; }
    }

    .main { margin-top: 65px; padding: 2.5rem 5vw; display: grid; grid-template-columns: 380px 1fr; gap: 2rem; }
    @media (max-width: 900px) { .main { grid-template-columns: 1fr; padding: 1.5rem 4vw; } }
    @media (max-width: 480px) { .main { padding: 1rem 3vw; } }

    .page-title { font-family: 'Playfair Display', serif; font-size: 1.8rem; color: var(--brown); margin-bottom: 0.3rem; }
    .page-sub { color: var(--muted); font-size: 0.88rem; margin-bottom: 1.5rem; }
    .msg { padding: 0.85rem 1.2rem; border-radius: 12px; font-size: 0.88rem; margin-bottom: 1.5rem; font-weight: 500; }
    .msg.success { background: #d4edda; color: #155724; border: 1.5px solid #b8dfc4; }
    .msg.error   { background: #f8d7da; color: #721c24; border: 1.5px solid #f5b8bc; }
    .card { background: var(--white); border-radius: 22px; padding: 2rem; box-shadow: 0 4px 24px rgba(92,61,30,0.07); height: fit-content; }
    @media (max-width: 480px) { .card { padding: 1.2rem; } }
    .card-title { font-family: 'Playfair Display', serif; font-size: 1.2rem; color: var(--brown); margin-bottom: 1.5rem; padding-bottom: 0.8rem; border-bottom: 1.5px solid var(--warm); }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-size: 0.82rem; font-weight: 600; color: var(--brown); margin-bottom: 0.4rem; }
    .form-group input { width: 100%; padding: 0.75rem 1rem; border: 1.5px solid var(--warm); border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.9rem; color: var(--text); background: var(--cream); outline: none; transition: all .2s; }
    .form-group input:focus { border-color: var(--accent); background: var(--white); }
    .btn-add { width: 100%; padding: 0.85rem; background: var(--accent); color: white; border: none; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: all .2s; }
    .btn-add:hover { background: #c0551f; transform: translateY(-1px); }
    .boy-card { background: var(--white); border-radius: 16px; padding: 1.2rem; margin-bottom: 1rem; box-shadow: 0 2px 16px rgba(92,61,30,0.06); border: 1.5px solid var(--warm); display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
    .boy-avatar { width: 48px; height: 48px; border-radius: 50%; background: var(--accent); color: white; font-size: 1.3rem; font-weight: 900; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .boy-info { flex: 1; min-width: 0; }
    .boy-name { font-weight: 700; font-size: 1rem; color: var(--text); margin-bottom: 0.2rem; }
    .boy-phone { font-size: 0.82rem; color: var(--muted); }
    .boy-actions { display: flex; gap: 0.5rem; flex-shrink: 0; }
    .btn-call { padding: 0.4rem 0.9rem; background: #d4edda; color: #155724; border-radius: 8px; font-size: 0.78rem; font-weight: 700; text-decoration: none; }
    .btn-del  { padding: 0.4rem 0.9rem; background: #f8d7da; color: #721c24; border-radius: 8px; font-size: 0.78rem; font-weight: 700; text-decoration: none; }
    .empty-state { text-align: center; padding: 3rem; color: var(--muted); background: var(--white); border-radius: 16px; border: 1.5px dashed var(--warm); }
    .login-info { background: #e8f5e9; border: 1.5px solid #a5d6a7; border-radius: 12px; padding: 1rem; margin-top: 1rem; font-size: 0.82rem; color: #2e7d32; }
    .login-info strong { display: block; margin-bottom: 0.4rem; font-size: 0.88rem; }
    .login-url { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; background: white; border: 1.5px solid #a5d6a7; border-radius: 8px; padding: 0.6rem 0.8rem; margin-top: 0.5rem; }
    .login-url code { font-size: 0.78rem; color: #155724; font-family: monospace; word-break: break-all; flex: 1; }
    .btn-copy { padding: 0.25rem 0.7rem; background: #2e7d32; color: white; border: none; border-radius: 6px; font-size: 0.72rem; font-weight: 700; cursor: pointer; font-family: 'DM Sans', sans-serif; white-space: nowrap; }
    .btn-copy:hover { background: #1b5e20; }
  </style>
</head>
<body>

<!-- OVERLAY: right edge = sidebar width toh sidebar khud cover nahi hoga -->
<div class="nav-overlay" id="navOverlay" onclick="closeNav()"></div>

<nav>
  <a href="dashboard.php" class="logo">Trenzo<span>Kart</span></a>
  <button class="hamburger" id="hamburger" onclick="toggleNav()" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
  <div class="nav-right" id="navRight">
    <a href="orders.php" class="nav-link">📦 Orders</a>
    <a href="dashboard.php" class="nav-link">← Dashboard</a>
    <a href="logout.php" class="nav-link">🚪 Logout</a>
  </div>
</nav>

<div class="main">
  <div>
    <?php if (!empty($success)): ?><div class="msg success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="msg error">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="card">
      <div class="card-title">➕ Add Delivery Boy</div>
      <form method="POST">
        <input type="hidden" name="add_boy" value="1"/>
        <div class="form-group"><label>Full Name *</label><input type="text" name="name" placeholder="e.g. Rahul Kumar" required/></div>
        <div class="form-group"><label>Phone Number *</label><input type="tel" name="phone" placeholder="e.g. 9876543210" required/></div>
        <div class="form-group"><label>Email (optional)</label><input type="email" name="email" placeholder="rahul@example.com"/></div>
        <div class="form-group"><label>Password *</label><input type="password" name="password" placeholder="Min 6 characters" required/></div>
        <button type="submit" class="btn-add">🚚 Add Delivery Boy →</button>
      </form>
      <div class="login-info">
        <strong>📱 Delivery Boy Login URL:</strong>
        Delivery boy yeh link se login karega:
        <div class="login-url">
          <code id="loginUrl">https://trenzokart.infinityfree.me/delivery_login.php</code>
          <button class="btn-copy" onclick="copyUrl()">Copy</button>
        </div>
      </div>
    </div>
  </div>

  <div>
    <div class="page-title">🚚 Delivery Boys</div>
    <p class="page-sub">Apne delivery agents manage karo</p>
    <?php if (mysqli_num_rows($boys) === 0): ?>
      <div class="empty-state"><span style="font-size:3rem;display:block;margin-bottom:1rem;">🚚</span><p>Koi delivery boy nahi add kiya abhi!</p></div>
    <?php else: ?>
      <?php while ($b = mysqli_fetch_assoc($boys)): ?>
      <div class="boy-card">
        <div class="boy-avatar"><?= strtoupper(substr($b['name'], 0, 1)) ?></div>
        <div class="boy-info">
          <div class="boy-name"><?= htmlspecialchars($b['name']) ?></div>
          <div class="boy-phone">📞 <?= htmlspecialchars($b['phone']) ?> • <?= ucfirst($b['status']) ?></div>
        </div>
        <div class="boy-actions">
          <a href="tel:<?= preg_replace('/\D/','',$b['phone']) ?>" class="btn-call">📞 Call</a>
          <a href="delivery_boys.php?delete=<?= $b['id'] ?>" class="btn-del" onclick="return confirm('Remove this delivery boy?')">🗑️</a>
        </div>
      </div>
      <?php endwhile; ?>
    <?php endif; ?>
  </div>
</div>

<script>
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
function copyUrl() {
  var url = document.getElementById('loginUrl').textContent;
  navigator.clipboard.writeText(url).then(function() {
    var btn = document.querySelector('.btn-copy');
    btn.textContent = 'Copied!';
    setTimeout(function() { btn.textContent = 'Copy'; }, 2000);
  });
}
</script>
</body>
</html>