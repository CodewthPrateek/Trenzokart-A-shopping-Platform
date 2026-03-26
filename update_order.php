<?php
require '../config.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Not authorized']);
    exit();
}

$order_id   = intval($_GET['order_id'] ?? 0);
$new_status = $_GET['status'] ?? '';

$allowed = ['confirmed', 'shipped', 'delivered', 'cancelled'];
if (!in_array($new_status, $allowed)) {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid status!']);
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE id=?");
mysqli_stmt_bind_param($stmt, 'i', $order_id);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$order) {
    echo json_encode(['status' => 'error', 'msg' => 'Order not found!']);
    exit();
}

if ($new_status === 'confirmed') {
    $upd = mysqli_prepare($conn, "UPDATE orders SET status='confirmed', confirmed_at=NOW() WHERE id=?");
} elseif ($new_status === 'shipped') {
    $upd = mysqli_prepare($conn, "UPDATE orders SET status='shipped', shipped_at=NOW() WHERE id=?");
} elseif ($new_status === 'delivered') {
    $upd = mysqli_prepare($conn, "UPDATE orders SET status='delivered', delivered_at=NOW() WHERE id=?");
} else {
    $upd = mysqli_prepare($conn, "UPDATE orders SET status='cancelled' WHERE id=?");
}

mysqli_stmt_bind_param($upd, 'i', $order_id);
mysqli_stmt_execute($upd);

header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'msg' => 'Order updated!']);