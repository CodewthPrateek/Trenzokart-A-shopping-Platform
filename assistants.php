<?php
require '../config.php';
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
$success = ''; $error = '';

// Add assistant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_assistant'])) {
    $name     = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email    = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = trim($_POST['password']);

    // Max 10 check
    $count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM assistants WHERE status='active'"))['c'];
    if ($count >= 10) { $error = "Maximum 10 assistants allowed!"; }
    elseif (empty($name) || empty($email) || strlen($password) < 6) { $error = "All fields required, password min 6 chars!"; }
    else {
        $exists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM assistants WHERE email='$email'"));
        if ($exists) { $error = "Email already exists!"; }
        else {
            // Generate special ID
            $special_id = 'TK-AST-' . strtoupper(substr(md5(uniqid()), 0, 6));
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            mysqli_query($conn, "INSERT INTO assistants (name, email, password, special_id, created_by) VALUES ('$name','$email','$hashed','$special_id','{$_SESSION['admin_id']}')");
            $success = "Assistant added! Special ID: $special_id";
        }
    }
}

// Delete/Toggle status
if (isset($_GET['toggle'])) {
    $aid = intval($_GET['toggle']);
    $ast = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM assistants WHERE id='$aid'"));
    $new = $ast['status'] === 'active' ? 'inactive' : 'active';
    mysqli_query($conn, "UPDATE assistants SET status='$new' WHERE id='$aid'");
    header("Location: assistants.php"); exit();
}
if (isset($_GET['delete'])) {
    $aid = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM assistants WHERE id='$aid'");
    $success = "Assistant deleted!";
}

$assistants = [];
$result = mysqli_query($conn, "SELECT * FROM assistants ORDER BY created_at DESC");
while ($row = mysqli_fetch_assoc($result)) { $assistants[] = $row; }
$active_count = count(array_filter($assistants, fn($a) => $a['status'] === 'active'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart Admin — Assistants</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{--cream:#f5efe6;--warm:#e8d5b7;--brown:#5c3d1e;--accent:#d4622a;--accent2:#e8a045;--text:#2d1a0a;--muted:#8a6a4a;--white:#fffdf8;--dark:#1a0f02;}
    body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--text);}
    nav{position:fixed;top:0;left:0;right:0;z-index:100;background:var(--dark);display:flex;align-items:center;justify-content:space-between;padding:0 5vw;height:65px;box-shadow:0 4px 20px rgba(0,0,0,0.3);}
    .logo{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:900;color:var(--white);text-decoration:none;}
    .logo span{color:var(--accent2);}
    .nav-right{display:flex;gap:0.8rem;}
    .nav-link{padding:0.45rem 1.1rem;border:1.5px solid rgba(255,255,255,0.25);border-radius:50px;color:rgba(255,255,255,0.7);font-size:0.82rem;font-weight:600;text-decoration:none;transition:all .2s;}
    .nav-link:hover{border-color:var(--accent2);color:var(--accent2);}
    .main{margin-top:85px;padding:2.5rem 5vw;display:grid;grid-template-columns:400px 1fr;gap:2rem;}
    @media(max-width:900px){.main{grid-template-columns:1fr;}}
    .page-title{font-family:'Playfair Display',serif;font-size:2rem;color:var(--brown);margin-bottom:0.3rem;}
    .page-sub{color:var(--muted);font-size:0.9rem;margin-bottom:1.5rem;}
    .msg{padding:0.85rem 1.2rem;border-radius:12px;font-size:0.88rem;margin-bottom:1.5rem;font-weight:500;}
    .msg.success{background:#d4edda;color:#155724;border:1.5px solid #b8dfc4;}
    .msg.error{background:#f8d7da;color:#721c24;border:1.5px solid #f5b8bc;}
    .card{background:var(--white);border-radius:22px;padding:2rem;box-shadow:0 4px 24px rgba(92,61,30,0.07);height:fit-content;}
    .card-title{font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--brown);margin-bottom:1.5rem;padding-bottom:0.8rem;border-bottom:1.5px solid var(--warm);}
    .form-group{margin-bottom:1rem;}
    .form-group label{display:block;font-size:0.82rem;font-weight:600;color:var(--brown);margin-bottom:0.4rem;}
    .form-group input{width:100%;padding:0.75rem 1rem;border:1.5px solid var(--warm);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.9rem;color:var(--text);background:var(--cream);outline:none;transition:all .2s;}
    .form-group input:focus{border-color:var(--accent);background:var(--white);}
    .pwd-wrap{position:relative;}
    .pwd-wrap input{padding-right:2.8rem !important;}
    .pwd-toggle{position:absolute;right:0.9rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1.1rem;color:var(--muted);}
    .btn-add{width:100%;padding:0.85rem;background:var(--accent);color:white;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.95rem;font-weight:700;cursor:pointer;transition:all .2s;}
    .btn-add:hover{background:#c0551f;transform:translateY(-1px);}
    .limit-bar{background:var(--cream);border-radius:10px;padding:0.8rem 1rem;margin-bottom:1.5rem;border:1.5px solid var(--warm);display:flex;justify-content:space-between;align-items:center;}
    .limit-text{font-size:0.82rem;color:var(--brown);font-weight:600;}
    .limit-badge{background:var(--accent);color:white;padding:0.2rem 0.7rem;border-radius:50px;font-size:0.78rem;font-weight:700;}
    .ast-card{background:var(--white);border-radius:18px;padding:1.3rem;margin-bottom:1rem;box-shadow:0 2px 16px rgba(92,61,30,0.06);border:1.5px solid var(--warm);display:flex;align-items:center;gap:1rem;flex-wrap:wrap;}
    .ast-avatar{width:46px;height:46px;border-radius:50%;background:var(--accent);color:white;font-size:1.2rem;font-weight:900;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .ast-info{flex:1;}
    .ast-name{font-weight:700;font-size:0.95rem;color:var(--text);}
    .ast-email{font-size:0.78rem;color:var(--muted);}
    .ast-id{display:inline-block;background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;padding:0.2rem 0.6rem;border-radius:6px;font-size:0.75rem;font-weight:700;font-family:monospace;margin-top:0.3rem;}
    .ast-actions{display:flex;gap:0.5rem;}
    .btn-sm{padding:0.35rem 0.8rem;border-radius:8px;font-size:0.78rem;font-weight:600;cursor:pointer;border:none;font-family:'DM Sans',sans-serif;text-decoration:none;display:inline-block;transition:all .2s;}
    .btn-toggle-active{background:#fff3e0;color:#e65100;}
    .btn-toggle-active:hover{background:#e65100;color:white;}
    .btn-toggle-inactive{background:#d4edda;color:#155724;}
    .btn-toggle-inactive:hover{background:#155724;color:white;}
    .btn-del{background:#ffebee;color:#c62828;}
    .btn-del:hover{background:#c62828;color:white;}
    .status-dot{width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:0.3rem;}
    .dot-active{background:#28a745;}
    .dot-inactive{background:#dc3545;}
    .empty-state{text-align:center;padding:3rem;color:var(--muted);background:var(--white);border-radius:16px;border:1.5px dashed var(--warm);}
  </style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="logo">Trenzo<span>Kart</span> <span style="font-size:0.75rem;color:var(--accent2);font-weight:400;font-family:'DM Sans',sans-serif;">Admin</span></a>
  <div class="nav-right">
    <a href="dashboard.php" class="nav-link">Dashboard</a>
    <a href="vendors.php" class="nav-link">Vendors</a>
    <a href="returns.php" class="nav-link">↩ Returns</a>
    <a href="logout.php" class="nav-link">Logout</a>
  </div>
</nav>

<div class="main">
  <!-- LEFT: ADD FORM -->
  <div>
    <?php if (!empty($success)): ?><div class="msg success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="msg error">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="limit-bar">
      <span class="limit-text">👥 Active Assistants</span>
      <span class="limit-badge"><?= $active_count ?> / 10</span>
    </div>

    <div class="card">
      <div class="card-title">➕ Add Assistant</div>
      <form method="POST">
        <input type="hidden" name="add_assistant" value="1"/>
        <div class="form-group"><label>Full Name *</label><input type="text" name="name" placeholder="e.g. Rahul Sharma" required/></div>
        <div class="form-group"><label>Email *</label><input type="email" name="email" placeholder="rahul@example.com" required/></div>
        <div class="form-group">
          <label>Password *</label>
          <div class="pwd-wrap">
            <input type="password" id="astPwd" name="password" placeholder="Min 6 characters" required/>
            <button type="button" class="pwd-toggle" onclick="togglePwd('astPwd',this)">👁️</button>
          </div>
        </div>
        <button type="submit" class="btn-add">🤝 Add Assistant →</button>
      </form>
      <div style="margin-top:1rem;padding:0.8rem 1rem;background:var(--cream);border-radius:10px;border:1.5px solid var(--warm);font-size:0.82rem;color:var(--brown);">
        <strong>📱 Assistant Login URL:</strong><br>
        <code>localhost/ecommerce/assistant_login.php</code>
      </div>
    </div>
  </div>

  <!-- RIGHT: LIST -->
  <div>
    <div class="page-title">🤝 Assistants</div>
    <p class="page-sub">Return requests handle karne wale assistants</p>

    <?php if (empty($assistants)): ?>
      <div class="empty-state"><span style="font-size:3rem;display:block;margin-bottom:1rem;">🤝</span><p>Koi assistant nahi hai abhi!</p></div>
    <?php else: ?>
      <?php foreach ($assistants as $a): ?>
      <div class="ast-card">
        <div class="ast-avatar"><?= strtoupper(substr($a['name'], 0, 1)) ?></div>
        <div class="ast-info">
          <div class="ast-name">
            <span class="status-dot <?= $a['status'] === 'active' ? 'dot-active' : 'dot-inactive' ?>"></span>
            <?= htmlspecialchars($a['name']) ?>
          </div>
          <div class="ast-email"><?= htmlspecialchars($a['email']) ?></div>
          <div class="ast-id"><?= htmlspecialchars($a['special_id']) ?></div>
        </div>
        <div class="ast-actions">
          <a href="assistants.php?toggle=<?= $a['id'] ?>" class="btn-sm <?= $a['status'] === 'active' ? 'btn-toggle-active' : 'btn-toggle-inactive' ?>">
            <?= $a['status'] === 'active' ? '🔴 Deactivate' : '🟢 Activate' ?>
          </a>
          <a href="assistants.php?delete=<?= $a['id'] ?>" class="btn-sm btn-del" onclick="return confirm('Delete?')">🗑️</a>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
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