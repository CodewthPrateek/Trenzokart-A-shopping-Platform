<?php
require '../config.php';
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }

$admin_id = $_SESSION['admin_id'];
$success  = '';
$error    = '';

// Fetch admin
$stmt = mysqli_prepare($conn, "SELECT * FROM admins WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $admin_id);
mysqli_stmt_execute($stmt);
$admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Update username
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $new_username = trim($_POST['username']);
    if (empty($new_username)) {
        $error = "Username cannot be empty!";
    } else {
        $chk = mysqli_prepare($conn, "SELECT id FROM admins WHERE username=? AND id != ?");
        mysqli_stmt_bind_param($chk, 'si', $new_username, $admin_id);
        mysqli_stmt_execute($chk);
        if (mysqli_num_rows(mysqli_stmt_get_result($chk)) > 0) {
            $error = "This username is already taken!";
        } else {
            $upd = mysqli_prepare($conn, "UPDATE admins SET username=? WHERE id=?");
            mysqli_stmt_bind_param($upd, 'si', $new_username, $admin_id);
            mysqli_stmt_execute($upd);
            $_SESSION['admin_name'] = $new_username;
            $success = "Username updated successfully!";
            $admin['username'] = $new_username;
        }
    }
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['current_password']) && !empty($_POST['current_password'])) {
    $cur = $_POST['current_password'];
    $new = $_POST['new_password'];
    $con = $_POST['confirm_password'];

    if (!password_verify($cur, $admin['password'])) {
        $error = "Current password is incorrect!";
    } elseif (strlen($new) < 6) {
        $error = "New password must be at least 6 characters!";
    } elseif ($new !== $con) {
        $error = "New passwords do not match!";
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $pw = mysqli_prepare($conn, "UPDATE admins SET password=? WHERE id=?");
        mysqli_stmt_bind_param($pw, 'si', $hashed, $admin_id);
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
  <title>TrenzoKart — Admin Profile</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --cream: #f5efe6; --warm: #e8d5b7; --brown: #5c3d1e; --accent: #d4622a; --accent2: #e8a045; --text: #2d1a0a; --muted: #8a6a4a; --white: #fffdf8; --dark: #1a0f02; }
    body { font-family: 'DM Sans', sans-serif; background: var(--cream); color: var(--text); }
    nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; background: var(--dark); display: flex; align-items: center; justify-content: space-between; padding: 0 5vw; height: 65px; }
    .logo { font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 900; color: var(--white); text-decoration: none; }
    .logo span { color: var(--accent2); }
    .nav-right { display: flex; align-items: center; gap: 1rem; }
    .back-btn { padding: 0.4rem 1rem; background: none; border: 1.5px solid rgba(255,255,255,0.3); border-radius: 50px; color: rgba(255,255,255,0.7); font-size: 0.82rem; cursor: pointer; text-decoration: none; transition: all .2s; font-family: 'DM Sans', sans-serif; }
    .back-btn:hover { border-color: var(--accent2); color: var(--accent2); }
    .btn-logout { padding: 0.4rem 1rem; background: none; border: 1.5px solid rgba(255,255,255,0.3); border-radius: 50px; color: rgba(255,255,255,0.7); font-size: 0.82rem; cursor: pointer; text-decoration: none; transition: all .2s; font-family: 'DM Sans', sans-serif; }
    .btn-logout:hover { border-color: var(--accent); color: var(--accent); }
    .main { margin-top: 85px; padding: 2rem 5vw; max-width: 700px; margin-left: auto; margin-right: auto; }
    .page-title { font-family: 'Playfair Display', serif; font-size: 2rem; color: var(--brown); margin-bottom: 0.3rem; }
    .page-sub { color: var(--muted); font-size: 0.9rem; margin-bottom: 2rem; }

    /* Profile Header */
    .profile-header { background: linear-gradient(135deg, var(--dark) 0%, #3a2010 100%); border-radius: 20px; padding: 2rem; display: flex; align-items: center; gap: 1.5rem; margin-bottom: 1.5rem; }
    .avatar-big { width: 80px; height: 80px; background: var(--accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: 'Playfair Display', serif; font-size: 2rem; font-weight: 900; color: white; flex-shrink: 0; border: 3px solid rgba(255,255,255,0.2); }
    .profile-info h2 { font-family: 'Playfair Display', serif; font-size: 1.4rem; color: white; margin-bottom: 0.3rem; }
    .profile-info p { color: rgba(255,255,255,0.5); font-size: 0.85rem; }
    .admin-tag { display: inline-block; background: var(--accent); color: white; font-size: 0.72rem; font-weight: 700; padding: 0.2rem 0.7rem; border-radius: 50px; margin-top: 0.4rem; }

    /* Cards */
    .card { background: var(--white); border-radius: 20px; padding: 2rem; margin-bottom: 1.5rem; box-shadow: 0 4px 20px rgba(92,61,30,0.06); }
    .card-title { font-family: 'Playfair Display', serif; font-size: 1.2rem; color: var(--brown); margin-bottom: 1.5rem; padding-bottom: 0.8rem; border-bottom: 1.5px solid var(--warm); }
    .form-group { margin-bottom: 1.2rem; }
    .form-group label { display: block; font-size: 0.82rem; font-weight: 600; color: var(--brown); margin-bottom: 0.4rem; }
    .form-group input { width: 100%; padding: 0.75rem 1rem; border: 1.5px solid var(--warm); border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.92rem; color: var(--text); background: var(--cream); outline: none; transition: border-color .2s, box-shadow .2s; }
    .form-group input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(212,98,42,0.12); background: var(--white); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .btn-save { padding: 0.8rem 2rem; background: var(--accent); color: white; border: none; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all .2s; }
    .btn-save:hover { background: #c0551f; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(212,98,42,0.3); }
    .msg { padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.88rem; margin-bottom: 1.5rem; }
    .msg.error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .msg.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .hint { font-size: 0.78rem; color: var(--muted); margin-top: 0.3rem; }
    @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="logo">Trenzo<span>Kart</span></a>
  <div class="nav-right">
    <a href="dashboard.php" class="back-btn">← Dashboard</a>
    <a href="logout.php" class="btn-logout">Logout</a>
  </div>
</nav>

<div class="main">
  <h1 class="page-title">⚙️ Admin Profile</h1>
  <p class="page-sub">Manage your admin account details</p>

  <?php if (!empty($error)): ?>
    <div class="msg error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if (!empty($success)): ?>
    <div class="msg success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <!-- Profile Header -->
  <div class="profile-header">
    <div class="avatar-big"><?= strtoupper(substr($admin['username'], 0, 1)) ?></div>
    <div class="profile-info">
      <h2><?= htmlspecialchars($admin['username']) ?></h2>
      <p>Administrator Account</p>
      <span class="admin-tag">⚙️ Super Admin</span>
    </div>
  </div>

  <!-- Update Username -->
  <div class="card">
    <div class="card-title">✏️ Update Username</div>
    <form method="POST">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" value="<?= htmlspecialchars($admin['username']) ?>" required/>
        <div class="hint">This is your login username for admin panel.</div>
      </div>
      <button type="submit" class="btn-save">Update Username →</button>
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