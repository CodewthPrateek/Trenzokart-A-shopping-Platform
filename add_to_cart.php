<?php
require 'config.php';
header('Content-Type: application/json');

$product_id = intval($_POST['product_id'] ?? $_GET['product_id'] ?? 0);
$quantity   = intval($_POST['quantity'] ?? 1);
if ($quantity < 1) $quantity = 1;

if (!$product_id) { echo json_encode(['status'=>'error','msg'=>'Invalid product']); exit(); }

// Check product exists
$product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM products WHERE id='$product_id'"));
if (!$product) { echo json_encode(['status'=>'error','msg'=>'Product not found']); exit(); }

if (isset($_SESSION['user_id'])) {
    // Logged in — save to DB
    $user_id = $_SESSION['user_id'];
    $check   = mysqli_query($conn, "SELECT * FROM cart WHERE user_id='$user_id' AND product_id='$product_id'");
    if (mysqli_num_rows($check) > 0) {
        mysqli_query($conn, "UPDATE cart SET quantity = quantity + $quantity WHERE user_id='$user_id' AND product_id='$product_id'");
    } else {
        mysqli_query($conn, "INSERT INTO cart (user_id, product_id, quantity) VALUES ('$user_id','$product_id','$quantity')");
    }
    // Cart count
    $count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(quantity) as t FROM cart WHERE user_id='$user_id'"))['t'] ?? 0;
    echo json_encode(['status'=>'success','cart_count'=>$count]);
} else {
    // Guest — save to session
    if (!isset($_SESSION['guest_cart'])) $_SESSION['guest_cart'] = [];
    if (isset($_SESSION['guest_cart'][$product_id])) {
        $_SESSION['guest_cart'][$product_id] += $quantity;
    } else {
        $_SESSION['guest_cart'][$product_id] = $quantity;
    }
    $count = array_sum($_SESSION['guest_cart']);
    echo json_encode(['status'=>'success','cart_count'=>$count,'guest'=>true]);
}