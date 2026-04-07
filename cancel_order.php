<?php
require 'config.php';
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Not logged in']);
    exit();
}

$order_id = intval($_GET['order_id'] ?? 0);
$user_id  = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE id=? AND user_id=?");
mysqli_stmt_bind_param($stmt, 'ii', $order_id, $user_id);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$order) {
    echo json_encode(['status' => 'error', 'msg' => 'Order not found!']);
    exit();
}

// Shipped ya delivered cancel nahi hoga
if (in_array($order['status'], ['shipped', 'delivered', 'cancelled'])) {
    echo json_encode(['status' => 'error', 'msg' => 'This order cannot be cancelled anymore!']);
    exit();
}

// 24 hour check after confirmation
if ($order['status'] === 'confirmed' && !empty($order['confirmed_at'])) {
    $confirmed_time = strtotime($order['confirmed_at']);
    $hours_passed   = (time() - $confirmed_time) / 3600;
    if ($hours_passed > 24) {
        echo json_encode(['status' => 'error', 'msg' => 'Cancellation window of 24 hours has passed!']);
        exit();
    }
}

$upd = mysqli_prepare($conn, "UPDATE orders SET status='cancelled' WHERE id=? AND user_id=?");
mysqli_stmt_bind_param($upd, 'ii', $order_id, $user_id);
mysqli_stmt_execute($upd);

header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'msg' => 'Order cancelled successfully!']);
?>