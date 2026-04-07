<?php
require 'config.php';
$q     = trim($_GET['q'] ?? '');
$words = array_filter(explode(' ', $q), fn($w) => strlen(trim($w)) > 0);

if (empty($words)) {
    echo json_encode([]); exit();
}

// Each word separately search karo — OR condition
$conditions = [];
foreach ($words as $word) {
    $w = mysqli_real_escape_string($conn, trim($word));
    $conditions[] = "(name LIKE '%$w%' OR category LIKE '%$w%' OR description LIKE '%$w%')";
}
$where  = implode(' OR ', $conditions);
$sql    = "SELECT * FROM products WHERE $where ORDER BY id DESC LIMIT 12";
$result = mysqli_query($conn, $sql);
$products = [];
while ($row = mysqli_fetch_assoc($result)) { $products[] = $row; }
header('Content-Type: application/json');
echo json_encode($products);