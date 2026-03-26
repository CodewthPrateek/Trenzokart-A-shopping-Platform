<?php
require 'config.php';

// Category filter check karo
$category = isset($_GET['category']) ? $_GET['category'] : 'all';

if ($category === 'all') {
    $sql = "SELECT * FROM products ORDER BY id DESC";
} else {
    $safe_category = mysqli_real_escape_string($conn, $category);
    $sql = "SELECT * FROM products WHERE category = '$safe_category' ORDER BY id DESC";
}

$result = mysqli_query($conn, $sql);
$products = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // YAHAN HAI MAIN FIX: 
        // Agar image column mein multiple images hain (comma separated), 
        // toh hum sirf pehli image nikal kar bhejenge.
        if (!empty($row['image'])) {
            $images = explode(',', $row['image']);
            $row['image'] = trim($images[0]); // Sirf pehli image (Main Image)
        }
        $products[] = $row;
    }
}

// JSON format mein data bhej rahe hain
header('Content-Type: application/json');
echo json_encode($products);
?>