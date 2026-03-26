<?php
session_start();

$host     = "sql210.infinityfree.com";
$username = "if0_41395047";
$password = "6398667276";
$database = "if0_41395047_ecommerce_db";

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}
?>
