<?php
require 'config.php';
if (!isset($_SESSION['user_id'])) { echo json_encode(['status' => 'error']); exit(); }

$cart_id = intval($_GET['id']);
$action  = $_GET['action'];

if ($action === 'remove') {
    mysqli_query($conn, "DELETE FROM cart WHERE id='$cart_id'");
    echo json_encode(['status' => 'removed']);
} elseif ($action === 'increase') {
    mysqli_query($conn, "UPDATE cart SET quantity = quantity + 1 WHERE id='$cart_id'");
    $result = mysqli_query($conn, "SELECT quantity FROM cart WHERE id='$cart_id'");
    $row = mysqli_fetch_assoc($result);
    echo json_encode(['status' => 'updated', 'qty' => $row['quantity']]);
} elseif ($action === 'decrease') {
    $result = mysqli_query($conn, "SELECT quantity FROM cart WHERE id='$cart_id'");
    $row = mysqli_fetch_assoc($result);
    if ($row['quantity'] <= 1) {
        mysqli_query($conn, "DELETE FROM cart WHERE id='$cart_id'");
        echo json_encode(['status' => 'removed']);
    } else {
        mysqli_query($conn, "UPDATE cart SET quantity = quantity - 1 WHERE id='$cart_id'");
        echo json_encode(['status' => 'updated', 'qty' => $row['quantity'] - 1]);
    }
}
?>
