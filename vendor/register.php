<?php
require '../config.php';

if (isset($_SESSION['vendor_id'])) { header("Location: dashboard.php"); exit(); }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name']);
    $email     = trim($_POST['email']);
    $password  = $_POST['password'];
    $shop_name = trim($_POST['shop_name']);
    $phone     = trim($_POST['phone']);
    $address   = trim($_POST['address']);

    if (empty($name) || empty($email) || empty($password) || empty($shop_name)) {
        $error = "Please fill all required fields!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        $chk = mysqli_prepare($conn, "SELECT id FROM vendors WHERE email = ?");
        mysqli_stmt_bind_param($chk, 's', $email);
        mysqli_stmt_execute($chk);
        if (mysqli_num_rows(mysqli_stmt_get_result($chk)) > 0) {
            $error = "Email already registered!";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "INSERT INTO vendors (name, email, password, shop_name, phone, address) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssssss', $name, $email, $hashed, $shop_name, $phone, $address);
            mysqli_stmt_execute($stmt);
            $success = "Registration successful! Wait for admin approval.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — Vendor Register</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --cream: #f5efe6; --warm: #e8d5b7; --brown: #5c3d1e; --accent: #d4622a; --accent2: #e8a045; --text: #2d1a0a; --muted: #8a6a4a; --white: #fffdf8; }
    body { font-family: 'DM Sans', sans-serif; background: var(--brown); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
    .card { background: var(--white); border-radius: 24px; padding: 2.5rem; width: 100%; max-width: 500px; box-shadow: 0 24px 80px rgba(0,0,0,0.3); }
    .logo { font-family: 'Playfair Display', serif; font-size: 1.8rem; font-weight: 900; color: var(--brown); text-align: center; margin-bottom: 0.3rem; }
    .logo span { color: var(--accent); }
    .vendor-tag { text-align: center; font-size: 0.78rem; font-weight: 700; color: var(--muted); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 1.5rem; }
    h1 { font-family: 'Playfair Display', serif; font-size: 1.4rem; color: var(--brown); margin-bottom: 0.3rem; }
    .subtitle { color: var(--muted); font-size: 0.88rem; margin-bottom: 1.5rem; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-size: 0.82rem; font-weight: 600; color: var(--brown); margin-bottom: 0.4rem; }
    .form-group input, .form-group textarea { width: 100%; padding: 0.75rem 1rem; border: 1.5px solid var(--warm); border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.92rem; color: var(--text); background: var(--cream); outline: none; transition: border-color .2s; }
    .form-group input:focus, .form-group textarea:focus { border-color: var(--accent); background: var(--white); }
    .form-group textarea { resize: none; height: 80px; }
    .btn { width: 100%; padding: 0.85rem; background: var(--accent); color: white; border: none; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all .2s; margin-top: 0.5rem; }
    .btn:hover { background: #c0551f; transform: translateY(-1px); }
    .msg { padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.88rem; margin-bottom: 1rem; }
    .msg.error   { background: #f8d7da; color: #721c24; }
    .msg.success { background: #d4edda; color: #155724; }
    .login-link { text-align: center; margin-top: 1.2rem; font-size: 0.85rem; color: var(--muted); }
    .login-link a { color: var(--accent); font-weight: 600; text-decoration: none; }
    @media (max-width: 500px) { .form-row { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
<div class="card">
  <div class="logo">Trenzo<span>Kart</span></div>
  <div class="vendor-tag">🏪 Vendor Registration</div>
  <h1>Become a Seller</h1>
  <p class="subtitle">Create your vendor account and start selling</p>

  <?php if (!empty($error)): ?>
    <div class="msg error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if (!empty($success)): ?>
    <div class="msg success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-row">
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="name" placeholder="Your name" required/>
      </div>
      <div class="form-group">
        <label>Shop Name *</label>
        <input type="text" name="shop_name" placeholder="Your shop name" required/>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Email *</label>
        <input type="email" name="email" placeholder="vendor@email.com" required/>
      </div>
      <div class="form-group">
        <label>Phone</label>
        <input type="text" name="phone" placeholder="Mobile number"/>
      </div>
    </div>
    <div class="form-group">
      <label>Password *</label>
      <input type="password" name="password" placeholder="Minimum 6 characters" required/>
    </div>
    <div class="form-group">
      <label>Shop Address</label>
      <textarea name="address" placeholder="Your shop address..."></textarea>
    </div>
    <button type="submit" class="btn">Register as Vendor →</button>
  </form>
  <div class="login-link">Already have an account? <a href="login.php">Login here</a></div>
</div>
</body>
</html>