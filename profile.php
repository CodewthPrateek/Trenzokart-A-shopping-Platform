<?php
require 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = $_SESSION['user_id'];
$success = '';
$error   = '';

$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Update Profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    if (empty($name) || empty($email)) { $error = "Name and email are required!"; }
    else {
        $chk = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND id != ?");
        mysqli_stmt_bind_param($chk, 'si', $email, $user_id);
        mysqli_stmt_execute($chk);
        if (mysqli_num_rows(mysqli_stmt_get_result($chk)) > 0) { $error = "Email already used!"; }
        else {
            $upd = mysqli_prepare($conn, "UPDATE users SET name=?, email=?, phone=? WHERE id=?");
            mysqli_stmt_bind_param($upd, 'sssi', $name, $email, $phone, $user_id);
            mysqli_stmt_execute($upd);
            $_SESSION['user_name'] = $name;
            $success = "Profile updated!";
            $stmt2 = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
            mysqli_stmt_bind_param($stmt2, 'i', $user_id);
            mysqli_stmt_execute($stmt2);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));
        }
    }
}

// Change Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $cur = $_POST['current_password'];
    $new = $_POST['new_password'];
    $con = $_POST['confirm_new_password'];
    if (!password_verify($cur, $user['password'])) { $error = "Current password incorrect!"; }
    elseif (strlen($new) < 6) { $error = "New password min 6 characters!"; }
    elseif ($new !== $con) { $error = "Passwords do not match!"; }
    else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $pw = mysqli_prepare($conn, "UPDATE users SET password=? WHERE id=?");
        mysqli_stmt_bind_param($pw, 'si', $hashed, $user_id);
        mysqli_stmt_execute($pw);
        $success = "Password changed!";
    }
}

// Add Address
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_address'])) {
    $label    = mysqli_real_escape_string($conn, trim($_POST['label'] ?? 'Home'));
    $fname    = mysqli_real_escape_string($conn, trim($_POST['addr_full_name']));
    $phone    = mysqli_real_escape_string($conn, trim($_POST['addr_phone']));
    $addr1    = mysqli_real_escape_string($conn, trim($_POST['addr1']));
    $addr2    = mysqli_real_escape_string($conn, trim($_POST['addr2'] ?? ''));
    $city     = mysqli_real_escape_string($conn, trim($_POST['addr_city']));
    $state    = mysqli_real_escape_string($conn, trim($_POST['addr_state']));
    $pincode  = mysqli_real_escape_string($conn, trim($_POST['addr_pincode']));
    $is_def   = isset($_POST['is_default']) ? 1 : 0;

    if (empty($fname) || empty($addr1) || empty($city) || empty($pincode)) {
        $error = "Please fill all required address fields!";
    } else {
        if ($is_def) {
            mysqli_query($conn, "UPDATE user_addresses SET is_default=0 WHERE user_id='$user_id'");
        }
        mysqli_query($conn, "INSERT INTO user_addresses (user_id, label, full_name, phone, address1, address2, city, state, pincode, is_default) 
            VALUES ('$user_id','$label','$fname','$phone','$addr1','$addr2','$city','$state','$pincode','$is_def')");
        $success = "Address added!";
    }
}

// Delete Address
if (isset($_GET['delete_addr'])) {
    $aid = intval($_GET['delete_addr']);
    mysqli_query($conn, "DELETE FROM user_addresses WHERE id='$aid' AND user_id='$user_id'");
    $success = "Address deleted!";
}

// Set Default Address
if (isset($_GET['set_default'])) {
    $aid = intval($_GET['set_default']);
    mysqli_query($conn, "UPDATE user_addresses SET is_default=0 WHERE user_id='$user_id'");
    mysqli_query($conn, "UPDATE user_addresses SET is_default=1 WHERE id='$aid' AND user_id='$user_id'");
    $success = "Default address set!";
}

// Fetch addresses
$addresses = [];
$addr_result = mysqli_query($conn, "SELECT * FROM user_addresses WHERE user_id='$user_id' ORDER BY is_default DESC, id DESC");
while ($row = mysqli_fetch_assoc($addr_result)) { $addresses[] = $row; }

$order_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE user_id='$user_id'"))['c'];
$cart_count  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM cart WHERE user_id='$user_id'"))['c'];
$total_spent = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_amount) as t FROM orders WHERE user_id='$user_id'"))['t'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — My Profile</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --cream: #f5efe6; --warm: #e8d5b7; --brown: #5c3d1e; --accent: #d4622a; --accent2: #e8a045; --text: #2d1a0a; --muted: #8a6a4a; --white: #fffdf8; }
    body { font-family: 'DM Sans', sans-serif; background: var(--cream); color: var(--text); }
    nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; background: rgba(245,239,230,0.96); backdrop-filter: blur(12px); border-bottom: 1px solid var(--warm); display: flex; align-items: center; justify-content: space-between; padding: 0 5vw; height: 68px; }
    .logo { font-family: 'Playfair Display', serif; font-size: 1.8rem; font-weight: 900; color: var(--brown); text-decoration: none; }
    .logo span { color: var(--accent); }
    .back-btn { display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: 50px; background: var(--warm); color: var(--brown); text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: all .2s; }
    .back-btn:hover { background: var(--accent); color: white; }
    .main { margin-top: 88px; padding: 2rem 5vw; max-width: 860px; margin-left: auto; margin-right: auto; }
    .page-title { font-family: 'Playfair Display', serif; font-size: 2rem; color: var(--brown); margin-bottom: 0.3rem; }
    .page-sub { color: var(--muted); font-size: 0.9rem; margin-bottom: 2rem; }
    .profile-header { background: linear-gradient(135deg, var(--brown), #3a2010); border-radius: 20px; padding: 2rem; display: flex; align-items: center; gap: 1.5rem; margin-bottom: 1.5rem; }
    .avatar-big { width: 80px; height: 80px; background: var(--accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: 'Playfair Display', serif; font-size: 2rem; font-weight: 900; color: white; flex-shrink: 0; border: 3px solid rgba(255,255,255,0.2); }
    .profile-header-info h2 { font-family: 'Playfair Display', serif; font-size: 1.4rem; color: white; margin-bottom: 0.3rem; }
    .profile-header-info p { color: rgba(255,255,255,0.6); font-size: 0.88rem; }
    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
    .stat-box { background: var(--white); border-radius: 16px; padding: 1.2rem; text-align: center; box-shadow: 0 4px 20px rgba(92,61,30,0.06); }
    .stat-box-num { font-family: 'Playfair Display', serif; font-size: 1.8rem; font-weight: 900; color: var(--accent); }
    .stat-box-label { font-size: 0.78rem; color: var(--muted); margin-top: 0.2rem; }
    .card { background: var(--white); border-radius: 20px; padding: 2rem; margin-bottom: 1.5rem; box-shadow: 0 4px 20px rgba(92,61,30,0.06); }
    .card-title { font-family: 'Playfair Display', serif; font-size: 1.2rem; color: var(--brown); margin-bottom: 1.5rem; padding-bottom: 0.8rem; border-bottom: 1.5px solid var(--warm); display: flex; justify-content: space-between; align-items: center; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
    .form-group { margin-bottom: 1.1rem; }
    .form-group label { display: block; font-size: 0.82rem; font-weight: 600; color: var(--brown); margin-bottom: 0.4rem; }
    .form-group input, .form-group select { width: 100%; padding: 0.75rem 1rem; border: 1.5px solid var(--warm); border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.92rem; color: var(--text); background: var(--cream); outline: none; transition: all .2s; }
    .form-group input:focus, .form-group select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(212,98,42,0.12); background: var(--white); }
    .btn-save { padding: 0.8rem 2rem; background: var(--accent); color: white; border: none; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all .2s; }
    .btn-save:hover { background: #c0551f; transform: translateY(-1px); }
    .msg { padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.88rem; margin-bottom: 1.5rem; font-weight: 500; }
    .msg.error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .msg.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

    /* ADDRESS CARDS */
    .address-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .addr-card { background: var(--cream); border-radius: 14px; padding: 1.2rem; border: 1.5px solid var(--warm); position: relative; transition: all .2s; }
    .addr-card.default { border-color: var(--accent); background: #fff5f0; }
    .addr-card:hover { box-shadow: 0 4px 16px rgba(92,61,30,0.1); }
    .addr-label { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.6rem; }
    .addr-label-badge { background: var(--brown); color: white; font-size: 0.72rem; font-weight: 700; padding: 0.15rem 0.6rem; border-radius: 50px; }
    .addr-label-badge.default { background: var(--accent); }
    .addr-name { font-weight: 700; font-size: 0.95rem; color: var(--text); margin-bottom: 0.2rem; }
    .addr-phone { font-size: 0.82rem; color: var(--muted); margin-bottom: 0.4rem; }
    .addr-text { font-size: 0.82rem; color: var(--text); line-height: 1.5; }
    .addr-actions { display: flex; gap: 0.5rem; margin-top: 0.8rem; flex-wrap: wrap; }
    .addr-btn { padding: 0.3rem 0.8rem; border-radius: 8px; font-size: 0.75rem; font-weight: 600; text-decoration: none; transition: all .2s; cursor: pointer; border: none; font-family: 'DM Sans', sans-serif; }
    .btn-default { background: #e8f5e9; color: #2e7d32; }
    .btn-default:hover { background: #2e7d32; color: white; }
    .btn-del-addr { background: #ffebee; color: #c62828; }
    .btn-del-addr:hover { background: #c62828; color: white; }
    .default-star { position: absolute; top: 0.8rem; right: 0.8rem; color: var(--accent); font-size: 1rem; }

    /* ADD ADDRESS TOGGLE */
    .btn-add-addr { padding: 0.5rem 1.2rem; background: var(--warm); color: var(--brown); border: none; border-radius: 50px; font-family: 'DM Sans', sans-serif; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all .2s; }
    .btn-add-addr:hover { background: var(--accent); color: white; }
    .add-addr-form { display: none; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1.5px solid var(--warm); }
    .add-addr-form.open { display: block; }
    .default-check { display: flex; align-items: center; gap: 0.6rem; padding: 0.7rem 1rem; background: var(--cream); border-radius: 10px; border: 1.5px solid var(--warm); margin-bottom: 1rem; cursor: pointer; }
    .default-check input { width: auto; cursor: pointer; }
    .default-check label { font-size: 0.85rem; color: var(--brown); font-weight: 600; cursor: pointer; margin: 0; }

    @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } .form-row-3 { grid-template-columns: 1fr; } .stats-grid { grid-template-columns: 1fr 1fr; } }
  </style>
</head>
<body>
<nav>
  <a href="index.php" class="logo">Trenzo<span>Kart</span></a>
  <a href="index.php" class="back-btn">← Back to Home</a>
</nav>

<div class="main">
  <h1 class="page-title">👤 My Profile</h1>
  <p class="page-sub">Manage your account, addresses and password</p>

  <?php if (!empty($error)): ?><div class="msg error">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if (!empty($success)): ?><div class="msg success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

  <!-- PROFILE HEADER -->
  <div class="profile-header">
    <div class="avatar-big"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
    <div class="profile-header-info">
      <h2><?= htmlspecialchars($user['name']) ?></h2>
      <p><?= htmlspecialchars($user['email']) ?></p>
      <?php if (!empty($user['phone'])): ?>
        <p style="margin-top:0.3rem;color:rgba(255,255,255,0.5);font-size:0.8rem;">📱 <?= htmlspecialchars($user['phone']) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <!-- STATS -->
  <div class="stats-grid">
    <div class="stat-box"><div class="stat-box-num"><?= $order_count ?></div><div class="stat-box-label">Total Orders</div></div>
    <div class="stat-box"><div class="stat-box-num">₹<?= number_format($total_spent, 0) ?></div><div class="stat-box-label">Total Spent</div></div>
    <div class="stat-box"><div class="stat-box-num"><?= count($addresses) ?></div><div class="stat-box-label">Saved Addresses</div></div>
  </div>

  <!-- SAVED ADDRESSES -->
  <div class="card">
    <div class="card-title">
      📍 My Addresses
      <button class="btn-add-addr" onclick="toggleAddForm()">+ Add New Address</button>
    </div>

    <?php if (!empty($addresses)): ?>
    <div class="address-grid">
      <?php foreach ($addresses as $addr): ?>
      <div class="addr-card <?= $addr['is_default'] ? 'default' : '' ?>">
        <?php if ($addr['is_default']): ?><div class="default-star">⭐</div><?php endif; ?>
        <div class="addr-label">
          <span class="addr-label-badge <?= $addr['is_default'] ? 'default' : '' ?>"><?= htmlspecialchars($addr['label']) ?></span>
          <?php if ($addr['is_default']): ?><span style="font-size:0.72rem;color:var(--accent);font-weight:600;">Default</span><?php endif; ?>
        </div>
        <div class="addr-name"><?= htmlspecialchars($addr['full_name']) ?></div>
        <div class="addr-phone">📞 <?= htmlspecialchars($addr['phone']) ?></div>
        <div class="addr-text">
          <?= htmlspecialchars($addr['address1']) ?>
          <?php if (!empty($addr['address2'])): ?>, <?= htmlspecialchars($addr['address2']) ?><?php endif; ?><br>
          <?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['state']) ?> — <?= htmlspecialchars($addr['pincode']) ?>
        </div>
        <div class="addr-actions">
          <?php if (!$addr['is_default']): ?>
            <a href="profile.php?set_default=<?= $addr['id'] ?>" class="addr-btn btn-default">⭐ Set Default</a>
          <?php endif; ?>
          <a href="profile.php?delete_addr=<?= $addr['id'] ?>" class="addr-btn btn-del-addr" onclick="return confirm('Delete this address?')">🗑️ Delete</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <p style="color:var(--muted);font-size:0.88rem;margin-bottom:1rem;">No addresses saved yet. Add one below!</p>
    <?php endif; ?>

    <!-- ADD ADDRESS FORM -->
    <div class="add-addr-form" id="addAddrForm">
      <form method="POST">
        <input type="hidden" name="add_address" value="1"/>
        <div class="form-row">
          <div class="form-group">
            <label>Label (Home/Office/Other)</label>
            <select name="label">
              <option value="Home">🏠 Home</option>
              <option value="Office">🏢 Office</option>
              <option value="Other">📍 Other</option>
            </select>
          </div>
          <div class="form-group">
            <label>Full Name *</label>
            <input type="text" name="addr_full_name" placeholder="Name for delivery" value="<?= htmlspecialchars($user['name']) ?>" required/>
          </div>
        </div>
        <div class="form-group">
          <label>Phone Number *</label>
          <input type="tel" name="addr_phone" placeholder="10-digit number" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" maxlength="10" required/>
        </div>
        <div class="form-group">
          <label>Address Line 1 *</label>
          <input type="text" name="addr1" placeholder="House no., Building, Street" required/>
        </div>
        <div class="form-group">
          <label>Address Line 2</label>
          <input type="text" name="addr2" placeholder="Area, Colony, Landmark (optional)"/>
        </div>
        <div class="form-row-3">
          <div class="form-group">
            <label>City *</label>
            <input type="text" name="addr_city" placeholder="City" required/>
          </div>
          <div class="form-group">
            <label>State *</label>
            <select name="addr_state" required>
              <option value="">State</option>
              <option>Andhra Pradesh</option><option>Delhi</option><option>Gujarat</option>
              <option>Haryana</option><option>Karnataka</option><option>Kerala</option>
              <option>Madhya Pradesh</option><option>Maharashtra</option><option>Punjab</option>
              <option>Rajasthan</option><option>Tamil Nadu</option><option>Telangana</option>
              <option selected>Uttar Pradesh</option><option>West Bengal</option><option>Other</option>
            </select>
          </div>
          <div class="form-group">
            <label>Pincode *</label>
            <input type="text" name="addr_pincode" placeholder="Pincode" maxlength="6" required/>
          </div>
        </div>
        <div class="default-check">
          <input type="checkbox" name="is_default" id="isDefault"/>
          <label for="isDefault">⭐ Set as default address</label>
        </div>
        <div style="display:flex;gap:1rem;">
          <button type="submit" class="btn-save">💾 Save Address →</button>
          <button type="button" class="btn-save" style="background:var(--warm);color:var(--brown);" onclick="toggleAddForm()">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- EDIT PROFILE -->
  <div class="card">
    <div class="card-title">✏️ Edit Profile</div>
    <form method="POST">
      <div class="form-row">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required/>
        </div>
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required/>
        </div>
      </div>
      <div class="form-group">
        <label>Phone Number</label>
        <input type="tel" name="phone" placeholder="Enter your phone number" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" maxlength="10"/>
      </div>
      <button type="submit" name="update_profile" class="btn-save">Save Changes →</button>
    </form>
  </div>

  <!-- CHANGE PASSWORD -->
  <div class="card">
    <div class="card-title">🔒 Change Password</div>
    <form method="POST">
      <div class="form-group">
        <label>Current Password</label>
        <input type="password" name="current_password" placeholder="Enter current password"/>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>New Password</label>
          <input type="password" name="new_password" placeholder="Minimum 6 characters"/>
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_new_password" placeholder="Re-enter new password"/>
        </div>
      </div>
      <button type="submit" name="change_password" class="btn-save">Update Password →</button>
    </form>
  </div>
</div>

<script>
function toggleAddForm() {
  document.getElementById('addAddrForm').classList.toggle('open');
}
</script>
</body>
</html>