<?php
require '../config.php';
if (!isset($_SESSION['vendor_id'])) { header("Location: login.php"); exit(); }

$vendor_id = $_SESSION['vendor_id'];
$success = '';
$error   = '';

// Fetch vendor
$stmt = mysqli_prepare($conn, "SELECT * FROM vendors WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $vendor_id);
mysqli_stmt_execute($stmt);
$vendor = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $name      = trim($_POST['name']);
    $shop_name = trim($_POST['shop_name']);
    $phone     = trim($_POST['phone']);
    $address   = trim($_POST['address']);

    $upd = mysqli_prepare($conn, "UPDATE vendors SET name=?, shop_name=?, phone=?, address=? WHERE id=?");
    mysqli_stmt_bind_param($upd, 'ssssi', $name, $shop_name, $phone, $address, $vendor_id);
    mysqli_stmt_execute($upd);
    $_SESSION['vendor_name'] = $name;
    $_SESSION['vendor_shop'] = $shop_name;
    $success = "Profile updated successfully!";
    $vendor['name']      = $name;
    $vendor['shop_name'] = $shop_name;
    $vendor['phone']     = $phone;
    $vendor['address']   = $address;
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['current_password']) && !empty($_POST['current_password'])) {
    $cur = $_POST['current_password'];
    $new = $_POST['new_password'];
    $con = $_POST['confirm_password'];

    if (!password_verify($cur, $vendor['password'])) {
        $error = "Current password is incorrect!";
    } elseif (strlen($new) < 6) {
        $error = "New password must be at least 6 characters!";
    } elseif ($new !== $con) {
        $error = "Passwords do not match!";
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $pw = mysqli_prepare($conn, "UPDATE vendors SET password=? WHERE id=?");
        mysqli_stmt_bind_param($pw, 'si', $hashed, $vendor_id);
        mysqli_stmt_execute($pw);
        $success = "Password changed successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — Vendor Profile</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --cream: #f5efe6; --warm: #e8d5b7; --brown: #5c3d1e; --accent: #d4622a; --accent2: #e8a045; --text: #2d1a0a; --muted: #8a6a4a; --white: #fffdf8; --dark: #1a0f02; }
    body { font-family: 'DM Sans', sans-serif; background: var(--cream); color: var(--text); }
    nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; background: var(--dark); display: flex; align-items: center; justify-content: space-between; padding: 0 5vw; height: 65px; }
    .logo { font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 900; color: var(--white); text-decoration: none; }
    .logo span { color: var(--accent2); }
    .nav-right { display: flex; align-items: center; gap: 1rem; }
    .nav-link { padding: 0.4rem 1rem; background: none; border: 1.5px solid rgba(255,255,255,0.3); border-radius: 50px; color: rgba(255,255,255,0.7); font-size: 0.82rem; cursor: pointer; text-decoration: none; transition: all .2s; font-family: 'DM Sans', sans-serif; }
    .nav-link:hover { border-color: var(--accent2); color: var(--accent2); }
    .main { margin-top: 85px; padding: 2rem 5vw; max-width: 700px; margin-left: auto; margin-right: auto; }
    .page-title { font-family: 'Playfair Display', serif; font-size: 1.8rem; color: var(--brown); margin-bottom: 0.3rem; }
    .page-sub { color: var(--muted); font-size: 0.88rem; margin-bottom: 2rem; }

    /* Profile Header */
    .profile-header { background: linear-gradient(135deg, var(--dark) 0%, #3a2010 100%); border-radius: 20px; padding: 2rem; display: flex; align-items: center; gap: 1.5rem; margin-bottom: 1.5rem; }
    .avatar-big { width: 80px; height: 80px; background: var(--accent2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: 'Playfair Display', serif; font-size: 2rem; font-weight: 900; color: var(--dark); flex-shrink: 0; border: 3px solid rgba(255,255,255,0.2); }
    .profile-info h2 { font-family: 'Playfair Display', serif; font-size: 1.4rem; color: white; margin-bottom: 0.3rem; }
    .profile-info p { color: rgba(255,255,255,0.5); font-size: 0.85rem; }
    .vendor-tag { display: inline-block; background: var(--accent2); color: var(--dark); font-size: 0.72rem; font-weight: 700; padding: 0.2rem 0.7rem; border-radius: 50px; margin-top: 0.4rem; }
    .commission-info { margin-left: auto; text-align: right; }
    .comm-num { font-family: 'Playfair Display', serif; font-size: 1.8rem; font-weight: 900; color: var(--accent2); }
    .comm-label { font-size: 0.75rem; color: rgba(255,255,255,0.5); }

    /* Cards */
    .card { background: var(--white); border-radius: 20px; padding: 2rem; margin-bottom: 1.5rem; box-shadow: 0 4px 20px rgba(92,61,30,0.06); }
    .card-title { font-family: 'Playfair Display', serif; font-size: 1.2rem; color: var(--brown); margin-bottom: 1.5rem; padding-bottom: 0.8rem; border-bottom: 1.5px solid var(--warm); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-group { margin-bottom: 1.2rem; }
    .form-group label { display: block; font-size: 0.82rem; font-weight: 600; color: var(--brown); margin-bottom: 0.4rem; }
    .form-group input, .form-group textarea { width: 100%; padding: 0.75rem 1rem; border: 1.5px solid var(--warm); border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.92rem; color: var(--text); background: var(--cream); outline: none; transition: border-color .2s; }
    .form-group input:focus, .form-group textarea:focus { border-color: var(--accent); background: var(--white); }
    .form-group textarea { resize: none; height: 80px; }
    .btn-save { padding: 0.8rem 2rem; background: var(--accent); color: white; border: none; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all .2s; }
    .btn-save:hover { background: #c0551f; transform: translateY(-1px); }
    .msg { padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.88rem; margin-bottom: 1.5rem; }
    .msg.error   { background: #f8d7da; color: #721c24; }
    .msg.success { background: #d4edda; color: #155724; }
    @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } .commission-info { display: none; } }
  </style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="logo">Trenzo<span>Kart</span></a>
  <div class="nav-right">
    <a href="dashboard.php" class="nav-link">← Dashboard</a>
    <a href="logout.php" class="nav-link">Logout</a>
  </div>
</nav>

<div class="main">
  <h1 class="page-title">⚙️ My Profile</h1>
  <p class="page-sub">Manage your vendor account</p>

  <?php if (!empty($error)): ?>
    <div class="msg error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if (!empty($success)): ?>
    <div class="msg success">✅ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <!-- Profile Header -->
  <div class="profile-header">
    <div class="avatar-big"><?= strtoupper(substr($vendor['name'], 0, 1)) ?></div>
    <div class="profile-info">
      <h2><?= htmlspecialchars($vendor['name']) ?></h2>
      <p><?= htmlspecialchars($vendor['shop_name']) ?></p>
      <span class="vendor-tag">🏪 Verified Vendor</span>
    </div>
    <div class="commission-info">
      <div class="comm-num"><?= $vendor['commission'] ?>%</div>
      <div class="comm-label">Commission Rate</div>
    </div>
  </div>

  <!-- Update Profile -->
  <div class="card">
    <div class="card-title">✏️ Update Profile</div>
    <form method="POST">
      <div class="form-row">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="name" value="<?= htmlspecialchars($vendor['name']) ?>" required/>
        </div>
        <div class="form-group">
          <label>Shop Name</label>
          <input type="text" name="shop_name" value="<?= htmlspecialchars($vendor['shop_name']) ?>" required/>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Email (cannot change)</label>
          <input type="email" value="<?= htmlspecialchars($vendor['email']) ?>" disabled style="opacity:0.6;cursor:not-allowed;"/>
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" value="<?= htmlspecialchars($vendor['phone'] ?? '') ?>"/>
        </div>
      </div>
      <div class="form-group">
        <label>Shop Address</label>
        <textarea name="address"><?= htmlspecialchars($vendor['address'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn-save">Update Profile →</button>
    </form>
  </div>

  <!-- Change Password -->
  <div class="card">
    <div class="card-title">🔒 Change Password</div>
    <form method="POST">
      <div class="form-group">
        <label>Current Password</label>
        <input type="password" name="current_password" placeholder="Enter current password" required/>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>New Password</label>
          <input type="password" name="new_password" placeholder="Minimum 6 characters" required/>
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_password" placeholder="Re-enter new password" required/>
        </div>
      </div>
      <button type="submit" class="btn-save">Change Password →</button>
    </form>
  </div>
</div>
</body>
</html>