<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'config.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: cart.php"); exit(); }

$user_id = $_SESSION['user_id'];
$total   = floatval($_POST['total_amount']);
$payment = $_POST['payment'];
$address = $_POST['full_name'] . ', ' . $_POST['address1'] . ', ' . $_POST['city'] . ', ' . $_POST['state'] . ' - ' . $_POST['pincode'];
$full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
$phone     = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));

// Cart se pehla product ka vendor_id fetch karo
$vendor_result = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT p.vendor_id FROM cart c
    JOIN products p ON p.id = c.product_id
    WHERE c.user_id = '$user_id' AND p.vendor_id IS NOT NULL
    LIMIT 1
"));
$vendor_id = $vendor_result['vendor_id'] ?? null;

// Agar vendor_id nahi mila toh approved vendor le lo
if (!$vendor_id) {
    $v = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM vendors WHERE status='approved' LIMIT 1"));
    $vendor_id = $v['id'] ?? null;
}

$upi_txn = mysqli_real_escape_string($conn, trim($_POST['upi_txn_id'] ?? ''));
$card_last4 = mysqli_real_escape_string($conn, trim($_POST['card_last4'] ?? ''));

// Order insert karo
$stmt = mysqli_prepare($conn, "INSERT INTO orders (user_id, total_amount, payment_method, address, full_name, phone, status, vendor_id) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");

if (!$stmt) { die("Query Error: " . mysqli_error($conn)); }

mysqli_stmt_bind_param($stmt, 'idssssi', $user_id, $total, $payment, $address, $full_name, $phone, $vendor_id);

if (!mysqli_stmt_execute($stmt)) { die("Execute Error: " . mysqli_stmt_error($stmt)); }

$order_id = mysqli_insert_id($conn);
if (!$order_id) { die("Order ID nahi mila — insert fail hua!"); }

// Cart items order_items mein save karo
$cart_items = mysqli_query($conn, "SELECT * FROM cart WHERE user_id='$user_id'");
while ($item = mysqli_fetch_assoc($cart_items)) {
    $pid = intval($item['product_id']);
    $qty = intval($item['quantity']);
    $price_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT price FROM products WHERE id='$pid'"));
    $price = floatval($price_row['price'] ?? 0);
    mysqli_query($conn, "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES ('$order_id','$pid','$qty','$price')");
}

mysqli_query($conn, "DELETE FROM cart WHERE user_id='$user_id'");
header("Location: order_success.php?order_id=$order_id");
exit();
?>