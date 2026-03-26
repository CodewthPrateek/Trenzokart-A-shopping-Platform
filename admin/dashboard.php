<?php
require '../config.php';
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }

// Admin name update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_name'])) {
    $new_name = trim($_POST['new_name']);
    if (!empty($new_name)) {
        $upd = mysqli_prepare($conn, "UPDATE admins SET username=? WHERE id=?");
        mysqli_stmt_bind_param($upd, 'si', $new_name, $_SESSION['admin_id']);
        mysqli_stmt_execute($upd);
        $_SESSION['admin_name'] = $new_name;
    }
    header("Location: dashboard.php");
    exit();
}

$total_orders     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders"))['c'];
$pending_orders   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE status='pending'"))['c'];
$confirmed_orders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE status='confirmed'"))['c'];
$shipped_orders   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE status='shipped'"))['c'];
$cancelled_orders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE status='cancelled'"))['c'];
$total_revenue    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_amount) as t FROM orders WHERE status != 'cancelled'"))['t'] ?? 0;
$total_users      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users"))['c'];

$orders_result = mysqli_query($conn, "
    SELECT orders.*, users.name as user_name, users.email as user_email
    FROM orders
    JOIN users ON orders.user_id = users.id
    ORDER BY orders.id DESC
");
$orders = [];
while ($row = mysqli_fetch_assoc($orders_result)) { $orders[] = $row; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrenzoKart — Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --cream: #f5efe6; --warm: #e8d5b7; --brown: #5c3d1e; --accent: #d4622a; --accent2: #e8a045; --text: #2d1a0a; --muted: #8a6a4a; --white: #fffdf8; --dark: #1a0f02; }
    body { font-family: 'DM Sans', sans-serif; background: var(--cream); color: var(--text); }
    nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; background: var(--dark); display: flex; align-items: center; justify-content: space-between; padding: 0 5vw; height: 65px; }
    .logo { font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 900; color: var(--white); text-decoration: none; }
    .logo span { color: var(--accent2); }
    .admin-badge { background: var(--accent); color: white; font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.7rem; border-radius: 50px; }
    .nav-right { display: flex; align-items: center; gap: 1rem; }
    .admin-name-wrap { display: flex; align-items: center; gap: 0.4rem; color: rgba(255,255,255,0.7); font-size: 0.85rem; cursor: pointer; }
    .admin-name-wrap:hover .edit-icon { opacity: 1; }
    .edit-icon { opacity: 0; font-size: 0.75rem; transition: opacity .2s; }
    .btn-logout { padding: 0.4rem 1rem; background: none; border: 1.5px solid rgba(255,255,255,0.3); border-radius: 50px; color: rgba(255,255,255,0.7); font-size: 0.82rem; cursor: pointer; text-decoration: none; transition: all .2s; font-family: 'DM Sans', sans-serif; }
    .btn-logout:hover { border-color: var(--accent); color: var(--accent); }
    .main { margin-top: 85px; padding: 2rem 5vw; }
    .page-title { font-family: 'Playfair Display', serif; font-size: 1.8rem; color: var(--brown); margin-bottom: 0.3rem; }
    .page-sub { color: var(--muted); font-size: 0.88rem; margin-bottom: 2rem; }

    /* Stats Grid */
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: var(--white); border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(92,61,30,0.06); border-left: 4px solid transparent; }
    .stat-card.orange { border-left-color: #d4622a; }
    .stat-card.yellow { border-left-color: #e8a045; }
    .stat-card.green  { border-left-color: #2d8a4e; }
    .stat-card.blue   { border-left-color: #004085; }
    .stat-card.teal   { border-left-color: #0c5460; }
    .stat-card.red    { border-left-color: #721c24; }
    .stat-card.purple { border-left-color: #6f42c1; }
    .stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
    .stat-num { font-family: 'Playfair Display', serif; font-size: 1.8rem; font-weight: 900; color: var(--brown); }
    .stat-label { font-size: 0.78rem; color: var(--muted); margin-top: 0.2rem; }

    /* Orders Table */
    .orders-table-wrap { background: var(--white); border-radius: 20px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(92,61,30,0.06); overflow-x: auto; }
    .table-title { font-family: 'Playfair Display', serif; font-size: 1.2rem; color: var(--brown); margin-bottom: 1.2rem; }
    table { width: 100%; border-collapse: collapse; min-width: 900px; }
    th { text-align: left; font-size: 0.75rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; padding: 0.6rem 0.8rem; border-bottom: 2px solid var(--warm); }
    td { padding: 0.9rem 0.8rem; font-size: 0.88rem; border-bottom: 1px solid rgba(232,213,183,0.5); vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: var(--cream); }
    .order-id { font-weight: 700; color: var(--brown); }
    .user-name { font-weight: 600; }
    .user-email { font-size: 0.78rem; color: var(--muted); }
    .amount { font-family: 'Playfair Display', serif; font-weight: 700; color: var(--brown); }
    .status-badge { padding: 0.25rem 0.7rem; border-radius: 50px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; display: inline-block; }
    .status-pending   { background: #fff3cd; color: #856404; }
    .status-confirmed { background: #d4edda; color: #155724; }
    .status-shipped   { background: #cce5ff; color: #004085; }
    .status-delivered { background: #d1ecf1; color: #0c5460; }
    .status-cancelled { background: #f8d7da; color: #721c24; }
    .status-note { font-size: 0.72rem; color: var(--muted); margin-top: 0.3rem; }
    .action-select { padding: 0.35rem 0.6rem; border: 1.5px solid var(--warm); border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 0.82rem; color: var(--text); background: var(--cream); cursor: pointer; outline: none; }
    .action-select:focus { border-color: var(--accent); }
    .btn-update { padding: 0.35rem 0.8rem; background: var(--accent); color: white; border: none; border-radius: 8px; font-size: 0.82rem; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; transition: all .2s; margin-left: 0.4rem; }
    .btn-update:hover { background: #c0551f; }

    /* Edit Name Modal */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 500; display: none; align-items: center; justify-content: center; }
    .modal-overlay.open { display: flex; }
    .modal-box { background: var(--white); border-radius: 20px; padding: 2rem; width: 100%; max-width: 380px; box-shadow: 0 24px 80px rgba(0,0,0,0.3); }
    .modal-title { font-family: 'Playfair Display', serif; font-size: 1.3rem; color: var(--brown); margin-bottom: 1.2rem; }
    .modal-input { width: 100%; padding: 0.75rem 1rem; border: 1.5px solid var(--warm); border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.92rem; color: var(--text); background: var(--cream); outline: none; margin-bottom: 1rem; }
    .modal-input:focus { border-color: var(--accent); }
    .modal-btns { display: flex; gap: 0.8rem; }
    .modal-save { flex: 1; padding: 0.75rem; background: var(--accent); color: white; border: none; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-weight: 600; cursor: pointer; }
    .modal-cancel { flex: 1; padding: 0.75rem; background: var(--warm); color: var(--brown); border: none; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-weight: 600; cursor: pointer; }

    .toast { position: fixed; bottom: 2rem; right: 2rem; background: var(--brown); color: white; padding: 0.8rem 1.5rem; border-radius: 12px; font-size: 0.88rem; font-weight: 500; box-shadow: 0 8px 24px rgba(0,0,0,0.2); transform: translateY(100px); opacity: 0; transition: all 0.3s; z-index: 999; }
    .toast.show { transform: translateY(0); opacity: 1; }
    @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
  </style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="logo">Trenzo<span>Kart</span></a>
  <div class="nav-right">
    <span class="admin-badge">⚙️ Admin</span>
   <div class="admin-name-wrap" onclick="window.location.href='profile.php'">
      👋 <?= htmlspecialchars($_SESSION['admin_name']) ?>
      <span class="edit-icon">✏️</span>
    </div>
    <a href="vendors.php" class="btn-logout">🏪 Vendors</a>
    <a href="orders.php" class="btn-logout">🛒 Orders</a>
    <a href="summary.php" class="btn-logout">📊 Summary</a>
    <a href="returns.php" class="btn-logout">↩ Returns</a>
    <a href="assistants.php" class="btn-logout">🤝 Assistants</a>
    <a href="delivery_boys.php" class="btn-logout">🚚 Delivery Boys</a>
    <a href="notifications.php" class="btn-logout">📢 Notify</a>
    <a href="logout.php" class="btn-logout">Logout</a>
  </div>
</nav>

<div class="main">
  <h1 class="page-title">📊 Admin Dashboard</h1>
  <p class="page-sub">Manage orders, users and products</p>

  <!-- Stats Row 1 -->
  <div class="stats-grid">
    <div class="stat-card orange">
      <div class="stat-icon">📦</div>
      <div class="stat-num"><?= $total_orders ?></div>
      <div class="stat-label">Total Orders</div>
    </div>
    <div class="stat-card yellow">
      <div class="stat-icon">⏳</div>
      <div class="stat-num"><?= $pending_orders ?></div>
      <div class="stat-label">Pending Orders</div>
    </div>
    <div class="stat-card blue">
      <div class="stat-icon">💰</div>
      <div class="stat-num">₹<?= number_format($total_revenue, 0) ?></div>
      <div class="stat-label">Total Revenue</div>
    </div>
    <div class="stat-card purple">
      <div class="stat-icon">👥</div>
      <div class="stat-num"><?= $total_users ?></div>
      <div class="stat-label">Total Users</div>
    </div>
  </div>

  <!-- Stats Row 2 -->
  <div class="stats-grid" style="margin-bottom:2rem;">
    <div class="stat-card green">
      <div class="stat-icon">✅</div>
      <div class="stat-num"><?= $confirmed_orders ?></div>
      <div class="stat-label">Confirmed Orders</div>
    </div>
    <div class="stat-card teal">
      <div class="stat-icon">🚚</div>
      <div class="stat-num"><?= $shipped_orders ?></div>
      <div class="stat-label">Dispatched Orders</div>
    </div>
    <div class="stat-card red">
      <div class="stat-icon">✕</div>
      <div class="stat-num"><?= $cancelled_orders ?></div>
      <div class="stat-label">Cancelled Orders</div>
    </div>
    <div class="stat-card orange">
      <div class="stat-icon">🎉</div>
      <div class="stat-num"><?= mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE status='delivered'"))['c'] ?></div>
      <div class="stat-label">Delivered Orders</div>
    </div>
  </div>

  <div class="orders-table-wrap">
    <div class="table-title">🛒 All Orders</div>
    <table>
      <thead>
        <tr>
          <th>Order ID</th>
          <th>Customer</th>
          <th>Amount</th>
          <th>Payment</th>
          <th>Date</th>
          <th>Status</th>
          <th>Update</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order):
          $date   = isset($order['created_at']) ? date('d M Y', strtotime($order['created_at'])) : 'N/A';
          $status = $order['status'];
          $status_note = '';
          if ($status === 'confirmed' && !empty($order['confirmed_at'])) {
            $status_note = '✅ Confirmed on ' . date('d M, h:i A', strtotime($order['confirmed_at']));
          } elseif ($status === 'shipped' && !empty($order['shipped_at'])) {
            $status_note = '🚚 Shipped on ' . date('d M, h:i A', strtotime($order['shipped_at']));
          } elseif ($status === 'delivered' && !empty($order['delivered_at'])) {
            $status_note = '✓ Delivered on ' . date('d M, h:i A', strtotime($order['delivered_at']));
          } elseif ($status === 'cancelled') {
            $status_note = '✕ Order Cancelled';
          } elseif ($status === 'pending') {
            $status_note = '⏳ Waiting for confirmation';
          }
        ?>
        <tr id="row-<?= $order['id'] ?>">
          <td class="order-id">#TK<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></td>
          <td>
            <div class="user-name"><?= htmlspecialchars($order['user_name']) ?></div>
            <div class="user-email"><?= htmlspecialchars($order['user_email']) ?></div>
          </td>
          <td class="amount">₹<?= number_format($order['total_amount'], 2) ?></td>
          <td><?= strtoupper($order['payment_method'] ?? 'COD') ?></td>
          <td><?= $date ?></td>
          <td>
            <span class="status-badge status-<?= $status ?>" id="badge-<?= $order['id'] ?>"><?= ucfirst($status) ?></span>
            <div class="status-note" id="note-<?= $order['id'] ?>"><?= $status_note ?></div>
          </td>
          <td>
            <?php if ($status !== 'cancelled' && $status !== 'delivered'): ?>
            <select class="action-select" id="select-<?= $order['id'] ?>">
              <option value="">Change...</option>
              <?php if ($status === 'pending'): ?>
                <option value="confirmed">✓ Confirm</option>
                <option value="cancelled">✕ Cancel</option>
              <?php elseif ($status === 'confirmed'): ?>
                <option value="shipped">🚚 Ship</option>
                <option value="cancelled">✕ Cancel</option>
              <?php elseif ($status === 'shipped'): ?>
                <option value="delivered">✓ Delivered</option>
              <?php endif; ?>
            </select>
            <button class="btn-update" onclick="updateOrder(<?= $order['id'] ?>)">Update</button>
            <?php else: ?>
              <span style="color:var(--muted);font-size:0.8rem;">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Edit Name Modal -->
<div class="modal-overlay" id="editModal">
  <div class="modal-box">
    <div class="modal-title">✏️ Edit Admin Name</div>
    <form method="POST">
      <input type="text" name="new_name" class="modal-input" placeholder="Enter new name" value="<?= htmlspecialchars($_SESSION['admin_name']) ?>" required/>
      <div class="modal-btns">
        <button type="submit" class="modal-save">Save →</button>
        <button type="button" class="modal-cancel" onclick="closeEditName()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
function openEditName() {
  document.getElementById('editModal').classList.add('open');
}
function closeEditName() {
  document.getElementById('editModal').classList.remove('open');
}

function updateOrder(orderId) {
  const select = document.getElementById('select-' + orderId);
  const status = select.value;
  if (!status) { showToast('⚠️ Please select a status!'); return; }
  if (!confirm('Are you sure you want to update this order to "' + status + '"?')) return;

  fetch('update_order.php?order_id=' + orderId + '&status=' + status)
    .then(r => r.json())
    .then(data => {
      if (data.status === 'success') {
        showToast('✅ Order updated to ' + status + '!');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast('❌ ' + data.msg);
      }
    })
    .catch(() => showToast('❌ Something went wrong!'));
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}
</script>
</body>
</html>