<?php
require 'config.php';

$error   = '';
$success = '';
$step    = 1;

// STEP 2: Set new password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
  $email            = trim($_POST['email']);
  $new_password     = $_POST['new_password'];
  $confirm_password = $_POST['confirm_password'];

  if (empty($new_password) || empty($confirm_password)) {
    $error = "Please fill in both fields!";
    $step  = 2;
  } elseif ($new_password !== $confirm_password) {
    $error = "Passwords do not match. Please try again!";
    $step  = 2;
  } elseif (strlen($new_password) < 6) {
    $error = "Password must be at least 6 characters!";
    $step  = 2;
  } else {
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt   = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE email = ?");
    mysqli_stmt_bind_param($stmt, 'ss', $hashed, $email);
    mysqli_stmt_execute($stmt);
    $success = "Your password has been updated successfully!";
    $step    = 3;
  }
}

// STEP 1: Verify email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && !isset($_POST['new_password'])) {
  $email = trim($_POST['email']);

  if (empty($email)) {
    $error = "Please enter your email address!";
  } else {
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 1) {
      $step = 2;
    } else {
      $error = "No account found with this email address!";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — Forgot Password</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --cream: #f5efe6; --warm: #e8d5b7; --brown: #5c3d1e;
      --accent: #d4622a; --accent2: #e8a045; --text: #2d1a0a;
      --muted: #8a6a4a; --white: #fffdf8;
    }
    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--cream); color: var(--text);
      min-height: 100vh; display: flex;
      align-items: center; justify-content: center; padding: 2rem 0;
    }
    body::before {
      content: ''; position: fixed; top: -200px; right: -200px;
      width: 600px; height: 600px;
      background: radial-gradient(circle, rgba(212,98,42,0.12) 0%, transparent 70%);
      border-radius: 50%; pointer-events: none;
    }
    .container {
      display: flex; width: min(900px, 95vw); max-width: 95vw; min-height: 500px;
      background: var(--white); border-radius: 24px;
      box-shadow: 0 24px 80px rgba(92,61,30,0.15); overflow: hidden;
      animation: fadeUp 0.6s ease forwards;
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .left-panel {
      width: 42%;
      background: linear-gradient(145deg, #3a2010 0%, var(--brown) 100%);
      padding: 3rem 2.5rem; display: flex; flex-direction: column;
      justify-content: space-between; position: relative; overflow: hidden;
    }
    .left-panel::before {
      content: ''; position: absolute; top: -80px; right: -80px;
      width: 250px; height: 250px;
      border: 40px solid rgba(255,255,255,0.05); border-radius: 50%;
    }
    .logo {
      font-family: 'Playfair Display', serif;
      font-size: 2rem; font-weight: 900; color: var(--white);
      letter-spacing: -1px; position: relative; z-index: 1;
    }
    .logo span { color: var(--accent2); }
    .left-content { position: relative; z-index: 1; }
    .left-content h2 {
      font-family: 'Playfair Display', serif; font-size: 1.8rem;
      color: var(--white); line-height: 1.3; margin-bottom: 1rem;
    }
    .left-content p { color: rgba(255,255,255,0.6); font-size: 0.9rem; line-height: 1.6; }
    .left-bottom { position: relative; z-index: 1; color: rgba(255,255,255,0.5); font-size: 0.8rem; }
    .right-panel {
      flex: 1; padding: 3rem 2.5rem; display: flex;
      flex-direction: column; justify-content: center;
    }
    .right-panel h1 {
      font-family: 'Playfair Display', serif; font-size: 1.8rem;
      color: var(--brown); margin-bottom: 0.4rem;
    }
    .subtitle { color: var(--muted); font-size: 0.9rem; margin-bottom: 2rem; }
    .form-group { margin-bottom: 1.2rem; }
    .form-group label {
      display: block; font-size: 0.82rem; font-weight: 600;
      color: var(--brown); margin-bottom: 0.4rem;
    }
    .form-group input {
      width: 100%; padding: 0.75rem 1rem;
      border: 1.5px solid var(--warm); border-radius: 10px;
      font-family: 'DM Sans', sans-serif; font-size: 0.92rem;
      color: var(--text); background: var(--cream); outline: none;
      transition: border-color .2s, box-shadow .2s;
    }
    .form-group input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(212,98,42,0.12); background: var(--white);
    }
    .btn-submit {
      width: 100%; padding: 0.85rem; background: var(--accent);
      color: white; border: none; border-radius: 10px;
      font-family: 'DM Sans', sans-serif; font-size: 0.95rem;
      font-weight: 600; cursor: pointer; margin-top: 0.5rem;
      transition: background .2s, transform .15s, box-shadow .2s;
    }
    .btn-submit:hover {
      background: #c0551f; transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(212,98,42,0.3);
    }
    .back-link { text-align: center; margin-top: 1.2rem; font-size: 0.88rem; color: var(--muted); }
    .back-link a { color: var(--accent); text-decoration: none; font-weight: 600; }
    .msg { padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.88rem; margin-bottom: 1rem; }
    .msg.error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .msg.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .step-indicator { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem; }
    .step-dot {
      width: 28px; height: 28px; border-radius: 50%;
      background: var(--warm); color: var(--muted);
      display: flex; align-items: center; justify-content: center;
      font-size: 0.8rem; font-weight: 700;
    }
    .step-dot.active { background: var(--accent); color: white; }
    .step-dot.done   { background: var(--brown); color: white; }
    .step-line-sm { width: 30px; height: 2px; background: var(--warm); }
    .step-line-sm.done { background: var(--brown); }
    .success-icon {
      width: 70px; height: 70px; background: #d4edda; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 2.5rem; margin: 0 auto 1.5rem;
    }
    @media (max-width: 650px) {
      .left-panel { display: none; }
      .container { border-radius: 16px; }
      .right-panel { padding: 2rem 1.5rem; }
    }
    @media(max-width:400px){
      .right-panel { padding: 1.5rem 1.2rem; }
      .right-panel h1 { font-size: 1.5rem; }
    }
  </style>
</head>
<body>
<div class="container">
  <div class="left-panel">
    <div class="logo">Trenzo<span>Kart</span></div>
    <div class="left-content">
      <h2>Forgot your password?</h2>
      <p>No worries! Enter your registered email address and reset your password in just a few steps.</p>
    </div>
    <div class="left-bottom">© 2024 TrenzoKart. All rights reserved.</div>
  </div>

  <div class="right-panel">
    <h1>Reset Password</h1>

    <!-- Step Indicator -->
    <div class="step-indicator">
      <div class="step-dot <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>">1</div>
      <div class="step-line-sm <?= $step > 1 ? 'done' : '' ?>"></div>
      <div class="step-dot <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>">2</div>
      <div class="step-line-sm <?= $step > 2 ? 'done' : '' ?>"></div>
      <div class="step-dot <?= $step >= 3 ? 'done' : '' ?>">✓</div>
    </div>

    <?php if (!empty($error)): ?>
      <div class="msg error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="success-icon">🎉</div>
      <div class="msg success"><?= htmlspecialchars($success) ?></div>
      <p class="back-link"><a href="login.php">← Back to Login</a></p>

    <?php elseif ($step === 2): ?>
      <p class="subtitle">Enter your new password below.</p>
      <form action="forgot_password.php" method="POST">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>"/>
        <div class="form-group">
          <label>New Password</label>
          <input type="password" name="new_password" placeholder="Minimum 6 characters" required/>
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_password" placeholder="Re-enter your new password" required/>
        </div>
        <button type="submit" class="btn-submit">Update Password →</button>
      </form>

    <?php else: ?>
      <p class="subtitle">Enter your registered email address to reset your password.</p>
      <form action="forgot_password.php" method="POST">
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" placeholder="Enter your email address" required/>
        </div>
        <button type="submit" class="btn-submit">Continue →</button>
      </form>
      <p class="back-link"><a href="login.php">← Back to Login</a></p>

    <?php endif; ?>
  </div>
</div>
</body>
</html>