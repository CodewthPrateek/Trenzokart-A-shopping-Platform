<?php
require 'config.php';

// Agar pehle se logged in hai toh index pe bhejo
if (isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit();
}

// Login form submit hua
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $email    = trim($_POST['email']);
  $password = $_POST['password'];

  if (empty($email) || empty($password)) {
    $error = "Enter Email or password!";
  } else {
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) === 1) {
      $user = mysqli_fetch_assoc($result);

      if (password_verify($password, $user['password'])) {
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        // Redirect to checkout if came from cart
        $redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
        if ($redirect === 'checkout') {
            header("Location: checkout.php");
        } else {
            header("Location: index.php");
        }
        exit();
      } else {
        $error = "Password galat hai!";
      }
    } else {
      $error = "Yeh email registered nahi hai!";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TrenzoKart — Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{--cream:#f5efe6;--warm:#e8d5b7;--brown:#5c3d1e;--accent:#d4622a;--accent2:#e8a045;--text:#2d1a0a;--muted:#8a6a4a;--white:#fffdf8;}
    body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 0;}
    body::before{content:'';position:fixed;top:-200px;right:-200px;width:600px;height:600px;background:radial-gradient(circle,rgba(212,98,42,0.12) 0%,transparent 70%);border-radius:50%;pointer-events:none;}
    .container{display:flex;width: min(900px, 95vw);max-width:95vw;min-height:500px;background:var(--white);border-radius:24px;box-shadow:0 24px 80px rgba(92,61,30,0.15);overflow:hidden;animation:fadeUp 0.6s ease forwards;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
    .left-panel{width:42%;background:linear-gradient(145deg,#3a2010 0%,var(--brown) 100%);padding:3rem 2.5rem;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden;}
    .left-panel::before{content:'';position:absolute;top:-80px;right:-80px;width:250px;height:250px;border:40px solid rgba(255,255,255,0.05);border-radius:50%;}
    .logo{font-family:'Playfair Display',serif;font-size:2rem;font-weight:900;color:var(--white);letter-spacing:-1px;position:relative;z-index:1;}
    .logo span{color:var(--accent2);}
    .left-content{position:relative;z-index:1;}
    .left-content h2{font-family:'Playfair Display',serif;font-size:1.8rem;color:var(--white);line-height:1.3;margin-bottom:1rem;}
    .left-content p{color:rgba(255,255,255,0.6);font-size:0.9rem;line-height:1.6;}
    .left-bottom{position:relative;z-index:1;color:rgba(255,255,255,0.5);font-size:0.8rem;}
    .right-panel{flex:1;padding:3rem 2.5rem;display:flex;flex-direction:column;justify-content:center;}
    .right-panel h1{font-family:'Playfair Display',serif;font-size:1.8rem;color:var(--brown);margin-bottom:0.4rem;}
    .subtitle{color:var(--muted);font-size:0.9rem;margin-bottom:2rem;}
    .form-group{margin-bottom:1.2rem;}
    .form-group label{display:flex;justify-content:space-between;align-items:center;font-size:0.82rem;font-weight:600;color:var(--brown);margin-bottom:0.4rem;}
    .forgot-link{font-size:0.78rem;font-weight:600;color:var(--accent);text-decoration:none;transition:color 0.2s;}
    .forgot-link:hover{color:#c0551f;text-decoration:underline;}
    .pwd-wrap { position: relative; }
    .pwd-wrap input { padding-right: 2.8rem !important; }
    .pwd-toggle { position: absolute; right: 0.9rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 1.1rem; color: var(--muted); transition: color .2s; padding: 0; line-height: 1; }
    .pwd-toggle:hover { color: var(--accent); }
    .form-group input{width:100%;padding:0.75rem 1rem;border:1.5px solid var(--warm);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.92rem;color:var(--text);background:var(--cream);outline:none;transition:border-color .2s,box-shadow .2s;}
    .form-group input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(212,98,42,0.12);background:var(--white);}
    .btn-login{width:100%;padding:0.85rem;background:var(--accent);color:white;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.95rem;font-weight:600;cursor:pointer;margin-top:0.5rem;transition:background .2s,transform .15s,box-shadow .2s;}
    .btn-login:hover{background:#c0551f;transform:translateY(-1px);box-shadow:0 6px 20px rgba(212,98,42,0.3);}
    .signup-link{text-align:center;margin-top:1.2rem;font-size:0.88rem;color:var(--muted);}
    .signup-link a{color:var(--accent);text-decoration:none;font-weight:600;}
    .msg{padding:0.75rem 1rem;border-radius:8px;font-size:0.88rem;margin-bottom:1rem;}
    .msg.error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
    .msg.success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
    @media(max-width:650px){
      .left-panel{display:none;}
      .container{border-radius:16px;}
      .right-panel{padding:2rem 1.5rem;}
    }
    @media(max-width:400px){
      .right-panel{padding:1.5rem 1.2rem;}
      .right-panel h1{font-size:1.5rem;}
    }
  
    @media(max-width:480px){
      .container{min-height:auto;border-radius:12px;}
      .right-panel{padding:1.5rem;}
      .form-group input{font-size:1rem;}
    }

</style>
</head>
<body>
  <div class="container">
    <div class="left-panel">
      <a href="index.php" class="logo" style="text-decoration:none;">Trenzo<span>Kart</span></a>
      <a href="index.php" style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.4rem 1rem;border-radius:50px;background:var(--warm);color:var(--brown);text-decoration:none;font-size:0.82rem;font-weight:600;margin-bottom:1.5rem;">🏠 Home</a>
      <div class="left-content">
        <h2>Welcome back!</h2>
        <p>Login to continue shopping the best deals on clothes, electronics, groceries & more.</p>
      </div>
      <div class="left-bottom">© 2024 TrenzoKart. All rights reserved.</div>
    </div>
    <div class="right-panel">
      <h1>Login</h1>
      <p class="subtitle">Welcome back! Please login to your account.</p>

      <?php if (!empty($error)): ?>
        <div class="msg error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if (isset($_GET['success'])): ?>
        <div class="msg success"><?= htmlspecialchars($_GET['success']) ?></div>
      <?php endif; ?>

      <form action="login.php<?= isset($_GET['redirect']) ? '?redirect='.$_GET['redirect'] : '' ?>" method="POST">
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" placeholder="Enter your email address" required />
        </div>
        <div class="form-group">
          <label>
            Password
            <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
          </label>
          <div class="pwd-wrap">
            <input type="password" id="loginPwd" name="password" placeholder="Enter your password" required />
            <button type="button" class="pwd-toggle" onclick="togglePwd('loginPwd', this)">👁️</button>
          </div>
        </div>
        <button type="submit" class="btn-login">Login →</button>
      </form>
      <p class="signup-link">Don't have an account? <a href="signup.php">Sign up here</a></p>
    </div>
  </div>
<script>
function togglePwd(inputId, btn) {
  const input = document.getElementById(inputId);
  if (input.type === 'password') { input.type = 'text'; btn.textContent = '🙈'; }
  else { input.type = 'password'; btn.textContent = '👁️'; }
}
</script>
</body>
</html>