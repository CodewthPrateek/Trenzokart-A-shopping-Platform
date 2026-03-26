<?php
require 'config.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['status'=>'error','msg'=>'Not logged in']); exit(); }

$user_id  = $_SESSION['user_id'];
$order_id = intval($_POST['order_id'] ?? 0);
$reason   = mysqli_real_escape_string($conn, trim($_POST['reason'] ?? ''));
$desc     = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));

if (!$order_id || empty($reason)) { echo json_encode(['status'=>'error','msg'=>'Invalid request!']); exit(); }

// Check order belongs to user and is delivered
$order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM orders WHERE id='$order_id' AND user_id='$user_id'"));
if (!$order) { echo json_encode(['status'=>'error','msg'=>'Order not found!']); exit(); }
if ($order['status'] !== 'delivered') { echo json_encode(['status'=>'error','msg'=>'Only delivered orders can be returned!']); exit(); }

// Check 7 working days (10 calendar days)
if (!empty($order['delivered_at'])) {
    $days = (time() - strtotime($order['delivered_at'])) / 86400;
    if ($days > 10) { echo json_encode(['status'=>'error','msg'=>'Return window of 7 working days has expired!']); exit(); }
}

// Check already requested
$exists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM return_requests WHERE order_id='$order_id' AND user_id='$user_id'"));
if ($exists) { echo json_encode(['status'=>'error','msg'=>'Return request already submitted!']); exit(); }

mysqli_query($conn, "INSERT INTO return_requests (order_id, user_id, reason, description) VALUES ('$order_id','$user_id','$reason','$desc')");
echo json_encode(['status'=>'success','msg'=>'Return request submitted!']);