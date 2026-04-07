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

// ── Base URL helper ──────────────────────────────────────────────
// Automatically detects whether running on localhost or live hosting.
// Use img_url($path) everywhere you display a product image.
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST']
    . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'));

/**
 * Convert a stored image path (e.g. "uploads/products/abc.jpg")
 * to a full URL safe for <img src="...">.
 * Also handles comma-separated paths — returns only the first image.
 *
 * Usage:  <img src="<?= img_url($product['image']) ?>">
 */
function img_url(string $path): string {
    if (empty($path)) return '';
    $path = trim(explode(',', $path)[0]);
    if (empty($path)) return '';
    if (str_starts_with($path, 'http') || str_starts_with($path, 'data:')) return $path;
    $path = ltrim($path, '/');
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $docRoot  = realpath($_SERVER['DOCUMENT_ROOT']);
        $fullPath = realpath($_SERVER['DOCUMENT_ROOT'] . '/' . $path);
        if ($fullPath && str_starts_with($fullPath, $docRoot)) {
            return $protocol . '://' . $host . '/' . $path;
        }
    }
    return $protocol . '://' . $host . '/' . $path;
}
?>