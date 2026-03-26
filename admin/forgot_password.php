<?php
require '../config.php';

if (isset($_SESSION['admin_id'])) { header("Location: dashboard.php"); exit(); }

$success = '';
$error   = '';
$step    = 1;
$email   = '';

// Step 2 — verify username
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && !isset($_POST['new_password'])) {
    $username = trim($_POST['username']);
    $stmt     = mysqli_prepare($conn, "SELECT * FROM admins WHERE username = ?");
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$admin) {
        $error = "No admin found with this username!";
    } else {
        $step     = 2;
        $username = $admin['username'];
    }
}

// Step 3 — reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $username = trim($_POST['username']);
    $new      = $_POST['new_password'];
    $confirm  = $_POST['confirm_password'];

    if (strlen($new) < 6) {
        $error = "Password must be at least 6 characters!";
        $step  = 2;
    } elseif ($new !== $confirm) {
        $error = "Passwords do not match!";
        $step  = 2;
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $upd    = mysqli_prepare($conn, "UPDATE admins SET password=? WHERE username=?");
        mysqli_stmt_bind_param($upd, 'ss', $hashed, $username);
        mysqli_stmt_execute($upd);
        $success = "Password reset successfully!";
        $step    = 3;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — Admin Forgot Password</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --cream: #f5efe6; --warm: #e8d5b7; --brown: #5c3d1e; --accent: #d4622a; --accent2: #e8a045; --text: #2d1a0a; --muted: #8a6a4a; --white: #fffdf8; }
    body { font-family: 'DM Sans', sans-serif; background: var(--brown); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
    .card { background: var(--white); border-radius: 24px; padding: 3rem; width: 100%; max-width: 420px; box-shadow: 0 24px 80px rgba(0,0,0,0.3); }
    .logo { font-family: 'Playfair Display', serif; font-size: 1.8rem; font-weight: 900; color: var(--brown); text-align: center; margin-bottom: 0.3rem; }
    .logo span { color: var(--accent); }
    .admin-tag { text-align: center; font-size: 0.78rem; font-weight: 700; color: var(--muted); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 2rem; }
    h1 { font-family: 'Playfair Display', serif; font-size: 1.5rem; color: var(--brown); margin-bottom: 0.3rem; }
    .subtitle { color: var(--muted); font-size: 0.88rem; margin-bottom: 1.5rem; }
    .steps { display: flex; justify-content: center; gap: 0.5rem; margin-bottom: 1.5rem; }
    .step-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--warm); }
    .step-dot.active { background: var(--accent); }
    .form-group { margin-bottom: 1.2rem; }
    .form-group label { display: block; font-size: 0.82rem; font-weight: 600; color: var(--brown); margin-bottom: 0.4rem; }
    .form-group input { width: 100%; padding: 0.75rem 1rem; border: 1.5px solid var(--warm); border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.92rem; color: var(--text); background: var(--cream); outline: none; transition: border-color .2s; }
    .form-group input:focus { border-color: var(--accent); background: var(--white); }
    .btn { width: 100%; padding: 0.85rem; background: var(--accent); color: white; border: none; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all .2s; margin-top: 0.5rem; display: block; text-align: center; text-decoration: none; }
    .btn:hover { background: #c0551f; transform: translateY(-1px); }
    .msg { padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.88rem; margin-bottom: 1rem; }
    .msg.error   { background: #f8d7da; color: #721c24; }
    .msg.success { background: #d4edda; color: #155724; }
    .back-link { text-align: center; margin-top: 1.2rem; font-size: 0.85rem; color: var(--muted); }
    .back-link a { color: var(--accent); font-weight: 600; text-decoration: none; }
  </style>
</head>
<body>
<div class="card">
  <div class="logo">Trenzo<span>Kart</span></div>
  <div class="admin-tag">⚙️ Admin Panel</div>

  <div class="steps">
    <div class="step-dot <?= $step >= 1 ? 'active' : '' ?>"></div>
    <div class="step-dot <?= $step >= 2 ? 'active' : '' ?>"></div>
    <div class="step-dot <?= $step >= 3 ? 'active' : '' ?>"></div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="msg error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($step === 1): ?>
    <h1>Forgot Password?</h1>
    <p class="subtitle">Enter your admin username to reset password</p>
    <form method="POST">
      <div class="form-group">
        <label>Admin Username</label>
        <input type="text" name="username" placeholder="Enter your username" required/>
      </div>
      <button type="submit" class="btn">Verify Username →</button>
    </form>

  <?php elseif ($step === 2): ?>
    <h1>Reset Password</h1>
    <p class="subtitle">Enter your new password</p>
    <form method="POST">
      <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>"/>
      <div class="form-group">
        <label>New Password</label>
        <input type="password" name="new_password" placeholder="Minimum 6 characters" required/>
      </div>
      <div class="form-group">
        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" placeholder="Re-enter new password" required/>
      </div>
      <button type="submit" class="btn">Reset Password →</button>
    </form>

  <?php elseif ($step === 3): ?>
    <h1>✅ Password Reset!</h1>
    <p class="subtitle">Your password has been changed successfully.</p>
    <div class="msg success"><?= htmlspecialchars($success) ?></div>
    <a href="login.php" class="btn">Go to Login →</a>
  <?php endif; ?>

  <div class="back-link"><a href="login.php">← Back to Login</a></div>
</div>
</body>
</html>