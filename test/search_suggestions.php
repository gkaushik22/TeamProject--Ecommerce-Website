<?php
// search_suggestions.php
require_once 'php_logic/connect.php'; // Oracle DB connection

// Get search term from AJAX request
$search_term = isset($_GET['query']) ? trim($_GET['query']) : '';

$response = ['products' => []];

if (!empty($search_term)) {
    $query = "SELECT p.product_id, p.product_name, p.price, p.image_path, p.stock_available, c.category_name, t.shop_name
              FROM Products p
              JOIN Categories c ON p.category_id = c.category_id
              JOIN Traders t ON p.trader_id = t.trader_id
              WHERE p.is_active = 1 
              AND p.stock_available > 0
              AND (LOWER(p.product_name) LIKE LOWER(:search_term)
                   OR LOWER(p.description) LIKE LOWER(:search_term)
                   OR LOWER(c.category_name) LIKE LOWER(:search_term)
                   OR LOWER(t.shop_name) LIKE LOWER(:search_term))
              AND ROWNUM <= 5
              ORDER BY p.product_name ASC";

    $stmt = oci_parse($conn, $query);
    if (!$stmt) {
        $e = oci_error($conn);
        error_log("OCI Parse Error in search_suggestions.php: " . $e['message']);
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    $search_param = '%' . $search_term . '%';
    oci_bind_by_name($stmt, ':search_term', $search_param);

    if (oci_execute($stmt)) {
        while ($row = oci_fetch_assoc($stmt)) {
            $response['products'][] = [
                'product_id' => $row['PRODUCT_ID'],
                'product_name' => $row['PRODUCT_NAME'],
                'price' => number_format($row['PRICE'], 2),
                'image_path' => $row['IMAGE_PATH'] ? 'assets/images/products/' . $row['IMAGE_PATH'] : 'assets/images/products/default.png',
                'stock_available' => $row['STOCK_AVAILABLE'],
                'category_name' => $row['CATEGORY_NAME'],
                'shop_name' => $row['SHOP_NAME']
            ];
        }
    } else {
        $e = oci_error($stmt);
        error_log("OCI Execute Error in search_suggestions.php: " . $e['message']);
    }
    oci_free_statement($stmt);
}

oci_close($conn);

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
