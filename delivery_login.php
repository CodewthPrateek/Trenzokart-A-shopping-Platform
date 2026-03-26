<?php
require 'config.php';
if (isset($_SESSION['delivery_boy_id'])) { header("Location: delivery/dashboard.php"); exit(); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone    = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $password = trim($_POST['password']);
    $result   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM delivery_boys WHERE phone='$phone' AND status='active'"));
    if ($result && password_verify($password, $result['password'])) {
        $_SESSION['delivery_boy_id']   = $result['id'];
        $_SESSION['delivery_boy_name'] = $result['name'];
        $_SESSION['delivery_boy_phone']= $result['phone'];
        header("Location: delivery/dashboard.php"); exit();
    } else {
        $error = "Invalid phone or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — Delivery Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{--cream:#f5efe6;--warm:#e8d5b7;--brown:#5c3d1e;--accent:#d4622a;--accent2:#e8a045;--text:#2d1a0a;--muted:#8a6a4a;--white:#fffdf8;--dark:#1a0f02;}
    body{font-family:'DM Sans',sans-serif;background:var(--dark);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;}
    .card{background:var(--white);border-radius:24px;padding:2.5rem;width:100%;max-width:380px;box-shadow:0 24px 80px rgba(0,0,0,0.4);}
    .logo{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:900;color:var(--brown);text-align:center;margin-bottom:0.3rem;}
    .logo span{color:var(--accent);}
    .subtitle{text-align:center;color:var(--muted);font-size:0.88rem;margin-bottom:2rem;}
    .badge{text-align:center;margin-bottom:1.5rem;}
    .badge span{background:#e8f5e9;color:#2e7d32;font-size:0.8rem;font-weight:700;padding:0.3rem 1rem;border-radius:50px;border:1px solid #a5d6a7;}
    .form-group{margin-bottom:1.1rem;}
    .form-group label{display:block;font-size:0.82rem;font-weight:600;color:var(--brown);margin-bottom:0.4rem;}
    .form-group input{width:100%;padding:0.8rem 1rem;border:1.5px solid var(--warm);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.92rem;color:var(--text);background:var(--cream);outline:none;transition:all .2s;}
    .form-group input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(212,98,42,0.1);background:var(--white);}
    .pwd-wrap{position:relative;}
    .pwd-wrap input{padding-right:2.8rem!important;}
    .pwd-toggle{position:absolute;right:0.9rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1.1rem;color:var(--muted);}
    .btn-login{width:100%;padding:0.9rem;background:var(--accent);color:white;border:none;border-radius:12px;font-family:'DM Sans',sans-serif;font-size:1rem;font-weight:700;cursor:pointer;transition:all .2s;margin-top:0.5rem;}
    .btn-login:hover{background:#c0551f;transform:translateY(-1px);}
    .error{background:#f8d7da;color:#721c24;border:1.5px solid #f5b8bc;padding:0.75rem 1rem;border-radius:10px;font-size:0.85rem;margin-bottom:1rem;}
    .icon{font-size:3rem;text-align:center;display:block;margin-bottom:0.5rem;}
    @media(max-width:400px){.card{padding:1.8rem 1.3rem;border-radius:16px;}}
  </style>
</head>
<body>
<div class="card">
  <span class="icon">🚚</span>
  <div class="logo">Trenzo<span>Kart</span></div>
  <div class="subtitle">Delivery Boy Portal</div>
  <div class="badge"><span>🟢 Delivery Login</span></div>
  <?php if (!empty($error)): ?><div class="error">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="POST">
    <div class="form-group">
      <label>Phone Number</label>
      <input type="tel" name="phone" placeholder="Enter your phone number" required/>
    </div>
    <div class="form-group">
      <label>Password</label>
      <div class="pwd-wrap">
        <input type="password" id="pwd" name="password" placeholder="Enter password" required/>
        <button type="button" class="pwd-toggle" onclick="togglePwd()">👁️</button>
      </div>
    </div>
    <button type="submit" class="btn-login">🚀 Login →</button>
  </form>
  <div style="text-align:center;margin-top:1.2rem;font-size:0.85rem;color:var(--muted);">
    New ho? <a href="delivery_signup.php" style="color:var(--accent);font-weight:700;">Register karo →</a>
  </div>
</div>
<script>
function togglePwd() {
  const inp = document.getElementById('pwd');
  inp.type = inp.type === 'password' ? 'text' : 'password';
  event.target.textContent = inp.type === 'password' ? '👁️' : '🙈';
}
</script>
</body>
</html>