<?php
require '../config.php';
if (!isset($_SESSION['vendor_id'])) { header("Location: login.php"); exit(); }
$vendor_id = $_SESSION['vendor_id'];

// Mark as read
if (isset($_GET['read'])) {
    $nid = intval($_GET['read']);
    mysqli_query($conn, "INSERT IGNORE INTO notification_reads (notification_id, vendor_id) VALUES ('$nid', '$vendor_id')");
    header("Location: notifications.php"); exit();
}

// Mark all as read
if (isset($_GET['read_all'])) {
    $all = mysqli_query($conn, "SELECT id FROM notifications WHERE vendor_id IS NULL OR vendor_id='$vendor_id'");
    while ($row = mysqli_fetch_assoc($all)) {
        mysqli_query($conn, "INSERT IGNORE INTO notification_reads (notification_id, vendor_id) VALUES ('{$row['id']}', '$vendor_id')");
    }
    header("Location: notifications.php"); exit();
}

// Fetch notifications for this vendor
$result = mysqli_query($conn, "
    SELECT n.*,
           IF(nr.id IS NOT NULL, 1, 0) as is_read
    FROM notifications n
    LEFT JOIN notification_reads nr ON nr.notification_id = n.id AND nr.vendor_id = '$vendor_id'
    WHERE (n.vendor_id IS NULL OR n.vendor_id = '$vendor_id')
      AND (n.valid_until IS NULL OR n.valid_until >= CURDATE())
    ORDER BY n.is_pinned DESC, n.created_at DESC
");
$notifications = [];
while ($row = mysqli_fetch_assoc($result)) { $notifications[] = $row; }
$unread_count = count(array_filter($notifications, fn($n) => !$n['is_read']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — Notifications</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --cream: #f5efe6; --warm: #e8d5b7; --brown: #5c3d1e; --accent: #d4622a; --accent2: #e8a045; --text: #2d1a0a; --muted: #8a6a4a; --white: #fffdf8; --dark: #1a0f02; }
    body { font-family: 'DM Sans', sans-serif; background: var(--cream); color: var(--text); }
    nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; background: var(--dark); display: flex; align-items: center; justify-content: space-between; padding: 0 5vw; height: 65px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
    .logo { font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 900; color: var(--white); text-decoration: none; }
    .logo span { color: var(--accent2); }
    .nav-right { display: flex; gap: 0.8rem; align-items: center; }
    .nav-link { padding: 0.45rem 1.1rem; border: 1.5px solid rgba(255,255,255,0.25); border-radius: 50px; color: rgba(255,255,255,0.7); font-size: 0.82rem; font-weight: 600; text-decoration: none; transition: all .2s; }
    .nav-link:hover { border-color: var(--accent2); color: var(--accent2); }

    .main { margin-top: 85px; padding: 2.5rem 5vw; max-width: 820px; margin-left: auto; margin-right: auto; }
    .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
    .page-title { font-family: 'Playfair Display', serif; font-size: 2rem; color: var(--brown); }
    .unread-badge { background: var(--accent); color: white; font-size: 0.8rem; font-weight: 700; padding: 0.3rem 0.8rem; border-radius: 50px; margin-left: 0.5rem; }
    .btn-read-all { padding: 0.5rem 1.2rem; background: var(--cream); border: 1.5px solid var(--warm); border-radius: 50px; font-size: 0.82rem; font-weight: 600; color: var(--brown); text-decoration: none; transition: all .2s; }
    .btn-read-all:hover { border-color: var(--accent); color: var(--accent); }

    .filter-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .filter-tab { padding: 0.4rem 1rem; border-radius: 50px; font-size: 0.8rem; font-weight: 600; cursor: pointer; border: 1.5px solid var(--warm); background: var(--white); color: var(--muted); transition: all .2s; }
    .filter-tab.active, .filter-tab:hover { background: var(--accent); border-color: var(--accent); color: white; }

    .notif-card { background: var(--white); border-radius: 18px; padding: 1.5rem; margin-bottom: 1rem; box-shadow: 0 2px 16px rgba(92,61,30,0.06); border: 1.5px solid transparent; transition: all .2s; position: relative; cursor: pointer; }
    .notif-card:hover { border-color: var(--warm); box-shadow: 0 6px 24px rgba(92,61,30,0.1); }
    .notif-card.unread { border-left: 4px solid var(--accent); background: linear-gradient(135deg, #fffdf8, #fff5f0); }
    .notif-card.pinned { border-top: 2px solid var(--accent2); }
    .notif-card.urgent { border-left: 4px solid #dc3545; }

    .unread-dot { width: 10px; height: 10px; background: var(--accent); border-radius: 50%; position: absolute; top: 1.2rem; right: 1.2rem; }

    .notif-title { font-weight: 700; font-size: 1rem; color: var(--text); margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
    .notif-message { color: var(--muted); font-size: 0.88rem; line-height: 1.6; margin-bottom: 0.8rem; }
    .notif-footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; }
    .notif-meta { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }

    .badge { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.2rem 0.65rem; border-radius: 50px; font-size: 0.72rem; font-weight: 700; }
    .badge-broadcast { background: #e3f2fd; color: #1565c0; }
    .badge-targeted  { background: #f3e5f5; color: #6a1b9a; }
    .badge-sale      { background: #e8f5e9; color: #2e7d32; }
    .badge-policy    { background: #fff3e0; color: #e65100; }
    .badge-warning   { background: #ffebee; color: #c62828; }
    .badge-system    { background: #e8eaf6; color: #283593; }
    .priority-badge { padding: 0.18rem 0.55rem; border-radius: 50px; font-size: 0.7rem; font-weight: 700; }
    .p-urgent { background: #ffebee; color: #c62828; }
    .p-high   { background: #fff3e0; color: #e65100; }
    .p-normal { background: #e8f5e9; color: #2e7d32; }
    .p-low    { background: #f5f5f5; color: #616161; }
    .pin-badge { background: var(--accent2); color: white; padding: 0.18rem 0.55rem; border-radius: 50px; font-size: 0.7rem; font-weight: 700; }
    .time-badge { color: var(--muted); font-size: 0.75rem; }
    .read-link { font-size: 0.78rem; color: var(--accent); font-weight: 600; text-decoration: none; }
    .read-link:hover { text-decoration: underline; }

    .empty-state { text-align: center; padding: 5rem 2rem; color: var(--muted); background: var(--white); border-radius: 22px; border: 1.5px dashed var(--warm); }
    .empty-state span { font-size: 4rem; display: block; margin-bottom: 1rem; }
  </style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="logo">Trenzo<span>Kart</span></a>
  <div class="nav-right">
    <a href="products.php" class="nav-link">Products</a>
    <a href="dashboard.php" class="nav-link">← Dashboard</a>
    <a href="logout.php" class="nav-link">Logout</a>
  </div>
</nav>

<div class="main">
  <div class="page-header">
    <div>
      <h1 class="page-title">
        🔔 Notifications
        <?php if ($unread_count > 0): ?>
          <span class="unread-badge"><?= $unread_count ?> new</span>
        <?php endif; ?>
      </h1>
      <p style="color:var(--muted);font-size:0.88rem;margin-top:0.3rem;">Admin ke messages aur updates</p>
    </div>
    <?php if ($unread_count > 0): ?>
      <a href="notifications.php?read_all=1" class="btn-read-all">✅ Mark All as Read</a>
    <?php endif; ?>
  </div>

  <div class="filter-tabs">
    <div class="filter-tab active" onclick="filterNotifs('all',this)">All</div>
    <div class="filter-tab" onclick="filterNotifs('unread',this)">🔴 Unread <?= $unread_count > 0 ? "($unread_count)" : '' ?></div>
    <div class="filter-tab" onclick="filterNotifs('sale',this)">🏷️ Sale</div>
    <div class="filter-tab" onclick="filterNotifs('policy',this)">📋 Policy</div>
    <div class="filter-tab" onclick="filterNotifs('warning',this)">⚠️ Warning</div>
  </div>

  <?php if (empty($notifications)): ?>
    <div class="empty-state">
      <span>🔔</span>
      <p>Abhi koi notification nahi hai!</p>
    </div>
  <?php else: ?>
    <?php foreach ($notifications as $n): ?>
    <div class="notif-card <?= !$n['is_read'] ? 'unread' : '' ?> <?= $n['is_pinned'] ? 'pinned' : '' ?> <?= $n['priority'] === 'urgent' ? 'urgent' : '' ?>" 
         data-type="<?= $n['type'] ?>" data-read="<?= $n['is_read'] ?>">
      <?php if (!$n['is_read']): ?>
        <div class="unread-dot"></div>
      <?php endif; ?>
      <div class="notif-title">
        <?= htmlspecialchars($n['title']) ?>
        <span class="badge badge-<?= $n['type'] ?>">
          <?= ['broadcast'=>'📢 Broadcast','targeted'=>'🎯 Direct','sale'=>'🏷️ Sale','policy'=>'📋 Policy','warning'=>'⚠️ Warning','system'=>'🔧 System'][$n['type']] ?>
        </span>
        <?php if ($n['priority'] !== 'normal'): ?>
          <span class="priority-badge p-<?= $n['priority'] ?>">
            <?= ['urgent'=>'🔴 Urgent','high'=>'🟠 High','low'=>'⚪ Low'][$n['priority']] ?? '' ?>
          </span>
        <?php endif; ?>
        <?php if ($n['is_pinned']): ?><span class="pin-badge">📌 Pinned</span><?php endif; ?>
      </div>
      <div class="notif-message"><?= nl2br(htmlspecialchars($n['message'])) ?></div>
      <div class="notif-footer">
        <div class="notif-meta">
          <span class="time-badge">🕐 <?= date('d M Y, h:i A', strtotime($n['created_at'])) ?></span>
          <?php if (!empty($n['valid_until'])): ?>
            <span style="background:#e8f5e9;color:#2e7d32;padding:0.18rem 0.55rem;border-radius:50px;font-size:0.7rem;font-weight:600;">
              📅 Valid till <?= date('d M Y', strtotime($n['valid_until'])) ?>
            </span>
          <?php endif; ?>
        </div>
        <?php if (!$n['is_read']): ?>
          <a href="notifications.php?read=<?= $n['id'] ?>" class="read-link">✓ Mark as read</a>
        <?php else: ?>
          <span style="color:var(--muted);font-size:0.75rem;">✓ Read</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
function filterNotifs(type, el) {
  document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  document.querySelectorAll('.notif-card').forEach(card => {
    if (type === 'all') { card.style.display = 'block'; }
    else if (type === 'unread') { card.style.display = card.dataset.read === '0' ? 'block' : 'none'; }
    else { card.style.display = card.dataset.type === type ? 'block' : 'none'; }
  });
}
</script>
</body>
</html>