<?php
require '../config.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = mysqli_prepare($conn, "SELECT * FROM admins WHERE username = ?");
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['username'];
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid username or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — Admin Login</title>
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
    .form-group { margin-bottom: 1.2rem; }
    .form-group label { display: block; font-size: 0.82rem; font-weight: 600; color: var(--brown); margin-bottom: 0.4rem; }
    .form-group input { width: 100%; padding: 0.75rem 1rem; border: 1.5px solid var(--warm); border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.92rem; color: var(--text); background: var(--cream); outline: none; transition: border-color .2s, box-shadow .2s; }
    .form-group input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(212,98,42,0.12); background: var(--white); }
    .forgot-link { display: block; text-align: right; font-size: 0.8rem; color: var(--accent); text-decoration: none; font-weight: 600; margin-top: -0.5rem; margin-bottom: 1.2rem; }
    .forgot-link:hover { text-decoration: underline; }
    .btn { width: 100%; padding: 0.85rem; background: var(--accent); color: white; border: none; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all .2s; margin-top: 0.5rem; }
    .btn:hover { background: #c0551f; transform: translateY(-1px); }
    .msg { padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.88rem; margin-bottom: 1rem; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
  </style>
</head>
<body>
<div class="card">
  <div class="logo">Trenzo<span>Kart</span></div>
  <div class="admin-tag">⚙️ Admin Panel</div>
  <h1>Admin Login</h1>
  <p class="subtitle">Enter your credentials to continue</p>
  <?php if (!empty($error)): ?>
    <div class="msg"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" placeholder="Enter username" required/>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" placeholder="Enter password" required/>
    </div>
    <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
    <button type="submit" class="btn">Login to Admin →</button>
  </form>
</div>
</body>
</html>