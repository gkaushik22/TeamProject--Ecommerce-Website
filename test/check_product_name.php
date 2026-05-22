<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as trader
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['usertype'] !== 'TRADER') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once 'php_logic/connect.php'; // Connect to Oracle DB

// Get trader information
$user_id = $_SESSION['user_id'] ?? null;

// Get shop information for this trader
$shop_id = null;
$query_shop = "SELECT shop_id FROM SHOP WHERE fk1_user_id = :user_id";
$stmt_shop = oci_parse($conn, $query_shop);
oci_bind_by_name($stmt_shop, ':user_id', $user_id);
oci_execute($stmt_shop);
$shop_row = oci_fetch_assoc($stmt_shop);
oci_free_statement($stmt_shop);

if (!$shop_row) {
    http_response_code(400);
    echo json_encode(['error' => 'Shop not found']);
    exit;
}

$shop_id = $shop_row['SHOP_ID'];

// Check for product name in POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_name'])) {
    $product_name = trim($_POST['product_name']);

    if (empty($product_name)) {
        echo json_encode(['exists' => false, 'error' => 'Product name is required']);
        exit;
    }

    // Check if product name exists for this shop
    $query_check_name = "SELECT COUNT(*) AS name_count FROM PRODUCT WHERE fk1_shop_id = :shop_id AND name = :name";
    $stmt_check_name = oci_parse($conn, $query_check_name);
    oci_bind_by_name($stmt_check_name, ':shop_id', $shop_id);
    oci_bind_by_name($stmt_check_name, ':name', $product_name);
    oci_execute($stmt_check_name);
    $row_check_name = oci_fetch_assoc($stmt_check_name);
    oci_free_statement($stmt_check_name);

    echo json_encode(['exists' => $row_check_name['NAME_COUNT'] > 0]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
exit;

if (isset($conn)) {
    oci_close($conn);
}
