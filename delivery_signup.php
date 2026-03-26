<?php
require 'config.php';
if (isset($_SESSION['delivery_boy_id'])) { header("Location: delivery/dashboard.php"); exit(); }

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = mysqli_real_escape_string($conn, trim($_POST['name']));
    $phone    = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $email    = mysqli_real_escape_string($conn, trim($_POST['email']));
    $company  = mysqli_real_escape_string($conn, trim($_POST['company']));
    $vehicle  = mysqli_real_escape_string($conn, trim($_POST['vehicle_no']));
    $password = trim($_POST['password']);
    $confirm  = trim($_POST['confirm_password']);

    if (empty($name) || empty($phone) || empty($password)) {
        $error = "Name, Phone and Password are required!";
    } elseif (strlen($phone) < 10) {
        $error = "Please enter a valid phone number!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match!";
    } else {
        $exists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM delivery_boys WHERE phone='$phone'"));
        if ($exists) {
            $error = "This phone number is already registered!";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            mysqli_query($conn, "INSERT INTO delivery_boys (name, phone, email, company, vehicle_no, password, status)
                VALUES ('$name', '$phone', '$email', '$company', '$vehicle', '$hashed', 'inactive')");
            $success = "Registration successful! Admin will verify your account — you can login after approval.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — Delivery Boy Signup</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{--cream:#f5efe6;--warm:#e8d5b7;--brown:#5c3d1e;--accent:#d4622a;--accent2:#e8a045;--text:#2d1a0a;--muted:#8a6a4a;--white:#fffdf8;--dark:#1a0f02;}
    body{font-family:'DM Sans',sans-serif;background:var(--dark);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;}
    .card{background:var(--white);border-radius:24px;padding:2.5rem;width:100%;max-width:440px;box-shadow:0 24px 80px rgba(0,0,0,0.4);}
    .logo{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:900;color:var(--brown);text-align:center;margin-bottom:0.2rem;}
    .logo span{color:var(--accent);}
    .subtitle{text-align:center;color:var(--muted);font-size:0.88rem;margin-bottom:0.8rem;}
    .badge{text-align:center;margin-bottom:1.5rem;}
    .badge span{background:#e8f5e9;color:#2e7d32;font-size:0.8rem;font-weight:700;padding:0.3rem 1rem;border-radius:50px;border:1px solid #a5d6a7;}
    .msg{padding:0.85rem 1rem;border-radius:12px;font-size:0.85rem;margin-bottom:1.2rem;font-weight:500;}
    .msg.success{background:#d4edda;color:#155724;border:1.5px solid #b8dfc4;}
    .msg.error{background:#f8d7da;color:#721c24;border:1.5px solid #f5b8bc;}
    .section-title{font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin:1.2rem 0 0.8rem;padding-bottom:0.4rem;border-bottom:1px solid var(--warm);}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;}
    .form-group{margin-bottom:0.9rem;}
    .form-group label{display:block;font-size:0.82rem;font-weight:600;color:var(--brown);margin-bottom:0.35rem;}
    .form-group input,.form-group select{width:100%;padding:0.75rem 1rem;border:1.5px solid var(--warm);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.9rem;color:var(--text);background:var(--cream);outline:none;transition:all .2s;}
    .form-group input:focus,.form-group select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(212,98,42,0.1);background:var(--white);}
    .required{color:var(--accent);}
    .pwd-wrap{position:relative;}
    .pwd-wrap input{padding-right:2.8rem!important;}
    .pwd-toggle{position:absolute;right:0.9rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;color:var(--muted);}
    .btn-signup{width:100%;padding:0.9rem;background:var(--accent);color:white;border:none;border-radius:12px;font-family:'DM Sans',sans-serif;font-size:1rem;font-weight:700;cursor:pointer;transition:all .2s;margin-top:0.5rem;}
    .btn-signup:hover{background:#c0551f;transform:translateY(-1px);box-shadow:0 6px 20px rgba(212,98,42,0.3);}
    .login-link{text-align:center;margin-top:1.2rem;font-size:0.85rem;color:var(--muted);}
    .login-link a{color:var(--accent);font-weight:700;text-decoration:none;}
    .note-box{background:#fff3cd;border:1px solid #ffc107;border-radius:10px;padding:0.7rem 1rem;font-size:0.78rem;color:#856404;margin-bottom:1.2rem;}
    .icon{font-size:2.8rem;text-align:center;display:block;margin-bottom:0.4rem;}
    @media(max-width:480px){.form-row{grid-template-columns:1fr;}.card{padding:1.8rem 1.3rem;}}
  </style>
</head>
<body>
<div class="card">
  <span class="icon">🚚</span>
  <div class="logo">Trenzo<span>Kart</span></div>
  <div class="subtitle">Delivery Boy Registration</div>
  <div class="badge"><span>📝 New Account</span></div>

  <?php if (!empty($success)): ?>
    <div class="msg success">✅ <?= htmlspecialchars($success) ?></div>
    <div style="text-align:center;margin-top:1rem;">
      <a href="delivery_login.php" style="color:var(--accent);font-weight:700;font-size:0.9rem;">← Go to Login</a>
    </div>
  <?php else: ?>

  <div class="note-box">
    ⚠️ After registration, admin will approve your account. Approval may take up to 24 hours.
  </div>

  <?php if (!empty($error)): ?>
    <div class="msg error">❌ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="section-title">👤 Personal Information</div>
    <div class="form-group">
      <label>Full Name <span class="required">*</span></label>
      <input type="text" name="name" placeholder="Enter your full name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required/>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Phone <span class="required">*</span></label>
        <input type="tel" name="phone" placeholder="10 digit number" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" maxlength="10" required/>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" placeholder="Optional" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
      </div>
    </div>

    <div class="section-title">🏢 Company Details</div>
    <div class="form-row">
      <div class="form-group">
        <label>Courier Company</label>
        <select name="company">
          <option value="">-- Select --</option>
          <option value="Delhivery" <?= ($_POST['company']??'')==='Delhivery'?'selected':'' ?>>🚚 Delhivery</option>
          <option value="Bluedart" <?= ($_POST['company']??'')==='Bluedart'?'selected':'' ?>>🔵 Bluedart</option>
          <option value="DTDC" <?= ($_POST['company']??'')==='DTDC'?'selected':'' ?>>📦 DTDC</option>
          <option value="Ekart" <?= ($_POST['company']??'')==='Ekart'?'selected':'' ?>>🛒 Ekart</option>
          <option value="India Post" <?= ($_POST['company']??'')==='India Post'?'selected':'' ?>>📮 India Post</option>
          <option value="Amazon Logistics" <?= ($_POST['company']??'')==='Amazon Logistics'?'selected':'' ?>>📦 Amazon Logistics</option>
          <option value="Xpressbees" <?= ($_POST['company']??'')==='Xpressbees'?'selected':'' ?>>🐝 Xpressbees</option>
          <option value="Shadowfax" <?= ($_POST['company']??'')==='Shadowfax'?'selected':'' ?>>🦊 Shadowfax</option>
          <option value="Independent" <?= ($_POST['company']??'')==='Independent'?'selected':'' ?>>🏍️ Independent</option>
          <option value="Other" <?= ($_POST['company']??'')==='Other'?'selected':'' ?>>📦 Other</option>
        </select>
      </div>
      <div class="form-group">
        <label>Vehicle No.</label>
        <input type="text" name="vehicle_no" placeholder="e.g. UP13AB1234" value="<?= htmlspecialchars($_POST['vehicle_no'] ?? '') ?>"/>
      </div>
    </div>

    <div class="section-title">🔒 Set Password</div>
    <div class="form-group">
      <label>Password <span class="required">*</span></label>
      <div class="pwd-wrap">
        <input type="password" id="pwd1" name="password" placeholder="Min 6 characters" required/>
        <button type="button" class="pwd-toggle" onclick="togglePwd('pwd1',this)">👁️</button>
      </div>
    </div>
    <div class="form-group">
      <label>Confirm Password <span class="required">*</span></label>
      <div class="pwd-wrap">
        <input type="password" id="pwd2" name="confirm_password" placeholder="Enter again" required/>
        <button type="button" class="pwd-toggle" onclick="togglePwd('pwd2',this)">👁️</button>
      </div>
    </div>

    <button type="submit" class="btn-signup">📝 Register →</button>
  </form>

  <div class="login-link">Already have an account? <a href="delivery_login.php">Login →</a></div>

  <?php endif; ?>
</div>
<script>
function togglePwd(id, btn) {
  const inp = document.getElementById(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
  btn.textContent = inp.type === 'password' ? '👁️' : '🙈';
}
</script>
</body>
</html>