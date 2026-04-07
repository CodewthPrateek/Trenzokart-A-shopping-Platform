<?php
require 'config.php';

// Agar pehle se logged in hai
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name             = trim($_POST['name']);
    $email            = trim($_POST['email']);
    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        $stmt_check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt_check, 's', $email);
        mysqli_stmt_execute($stmt_check);
        $check = mysqli_stmt_get_result($stmt_check);

        if (mysqli_num_rows($check) > 0) {
            $error = "This email is already registered!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt_ins = mysqli_prepare($conn, "INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt_ins, 'sss', $name, $email, $hashed_password);
            if (mysqli_stmt_execute($stmt_ins)) {
                $success = "Account created successfully! Please login.";
            } else {
                $error = "Something went wrong. Please try again!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — Sign Up</title>
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
      align-items: center; justify-content: center;
      padding: 2rem 0; position: relative;
    }
    body::before {
      content: ''; position: fixed; top: -200px; right: -200px;
      width: 600px; height: 600px;
      background: radial-gradient(circle, rgba(212,98,42,0.12) 0%, transparent 70%);
      border-radius: 50%; pointer-events: none;
    }
    body::after {
      content: ''; position: fixed; bottom: -150px; left: -150px;
      width: 500px; height: 500px;
      background: radial-gradient(circle, rgba(232,160,69,0.10) 0%, transparent 70%);
      border-radius: 50%; pointer-events: none;
    }
    .container {
      display: flex; width: min(900px, 95vw); max-width: 95vw; min-height: 560px;
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
      background: linear-gradient(145deg, var(--brown) 0%, #3a2010 100%);
      padding: 3rem 2.5rem; display: flex; flex-direction: column;
      justify-content: space-between; position: relative; overflow: hidden;
    }
    .left-panel::before {
      content: ''; position: absolute; top: -80px; right: -80px;
      width: 250px; height: 250px;
      border: 40px solid rgba(255,255,255,0.05); border-radius: 50%;
    }
    .left-panel::after {
      content: ''; position: absolute; bottom: -60px; left: -60px;
      width: 200px; height: 200px;
      border: 30px solid rgba(212,98,42,0.15); border-radius: 50%;
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
    .features { list-style: none; position: relative; z-index: 1; }
    .features li {
      color: rgba(255,255,255,0.75); font-size: 0.85rem;
      padding: 0.4rem 0; display: flex; align-items: center; gap: 0.6rem;
    }
    .features li::before {
      content: '✓'; background: var(--accent); color: white;
      width: 18px; height: 18px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.7rem; flex-shrink: 0;
    }
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
      box-shadow: 0 0 0 3px rgba(212,98,42,0.12);
      background: var(--white);
    }
    .btn-signup {
      width: 100%; padding: 0.85rem; background: var(--accent);
      color: white; border: none; border-radius: 10px;
      font-family: 'DM Sans', sans-serif; font-size: 0.95rem;
      font-weight: 600; cursor: pointer; margin-top: 0.5rem;
      transition: background .2s, transform .15s, box-shadow .2s;
    }
    .btn-signup:hover {
      background: #c0551f; transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(212,98,42,0.3);
    }
    .login-link { text-align: center; margin-top: 1.2rem; font-size: 0.88rem; color: var(--muted); }
    .login-link a { color: var(--accent); text-decoration: none; font-weight: 600; }
    .msg { padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.88rem; margin-bottom: 1rem; }
    .msg.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .msg.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    @media (max-width: 650px) { .left-panel { display: none; } }
    .pwd-wrap { position: relative; }
    .pwd-wrap input { padding-right: 2.8rem !important; }
    .pwd-toggle { position: absolute; right: 0.9rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 1.1rem; color: var(--muted); transition: color .2s; padding: 0; line-height: 1; }
    .pwd-toggle:hover { color: var(--accent); }
  
    @media(max-width:480px){
      .container{min-height:auto;border-radius:12px;}
      .right-panel{padding:1.5rem;}
      .form-group input{font-size:1rem;}
    }


    @media(max-width:400px){
      .right-panel,.form-side{padding:1.5rem 1rem!important;}
      .btn-login,.btn-signup,.btn-submit{font-size:0.9rem;}
    }

</style>
</head>
<body>
<div class="container">
  <div class="left-panel">
    <a href="index.php" class="logo" style="text-decoration:none;">Trenzo<span>Kart</span></a>
      <a href="index.php" style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.4rem 1rem;border-radius:50px;background:var(--warm);color:var(--brown);text-decoration:none;font-size:0.82rem;font-weight:600;margin-bottom:1.5rem;">🏠 Home</a>
    <div class="left-content">
      <h2>Your one-stop general store</h2>
      <p>Join thousands of happy customers shopping clothes, electronics, groceries & more!</p>
    </div>
    <ul class="features">
      <li>Free delivery on orders above ₹499</li>
      <li>Easy returns within 7 days</li>
      <li>Secure payments</li>
      <li>24/7 customer support</li>
    </ul>
  </div>
  <div class="right-panel">
    <h1>Create Account</h1>
    <p class="subtitle">Join TrenzoKart today — it's free!</p>

    <?php if (!empty($error)): ?>
      <div class="msg error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
      <div class="msg success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form action="signup.php" method="POST">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" placeholder="Enter your full name" required/>
      </div>
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" placeholder="Enter your email address" required/>
      </div>
      <div class="form-group">
        <label>Password</label>
        <div class="pwd-wrap">
          <input type="password" id="signupPwd" name="password" placeholder="Minimum 6 characters" required/>
          <button type="button" class="pwd-toggle" onclick="togglePwd('signupPwd', this)">👁️</button>
        </div>
      </div>
      <div class="form-group">
        <label>Confirm Password</label>
        <div class="pwd-wrap">
          <input type="password" id="signupPwd2" name="confirm_password" placeholder="Re-enter your password" required/>
          <button type="button" class="pwd-toggle" onclick="togglePwd('signupPwd2', this)">👁️</button>
        </div>
      </div>
      <button type="submit" class="btn-signup">Create Account →</button>
    </form>
    <p class="login-link">Already have an account? <a href="login.php">Login here</a></p>
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