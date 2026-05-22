<?php
require_once 'php_logic/connect.php'; // Connect to Oracle DB

/**
 * This script provides a way to retrieve product images from the database
 * as base64-encoded strings in JSON format, which can be used in application
 * builders or other interfaces that need to display BLOB data as images.
 * 
 * Usage:
 * 1. AJAX call to get base64: fetch('get_product_image_base64.php?id=800')
 *    .then(response => response.json())
 *    .then(data => {
 *        if (data.image) {
 *            document.getElementById('image').src = 'data:image/jpeg;base64,' + data.image;
 *        }
 *    });
 * 
 * 2. In application builders: Use this endpoint to get the base64 string and
 *    display it using the appropriate method for your builder.
 */

$product_id = isset($_GET['id']) ? trim($_GET['id']) : '';

if (!empty($product_id)) {
    $query = "SELECT image FROM PRODUCT WHERE product_id = :product_id";
    $stmt = oci_parse($conn, $query);
    oci_bind_by_name($stmt, ':product_id', $product_id);
    if (oci_execute($stmt)) {
        $row = oci_fetch_assoc($stmt);
        if ($row && $row['IMAGE']) {
            // Get the image data
            $image_data = $row['IMAGE']->load();
            
            // Convert to base64
            $base64_image = base64_encode($image_data);
            
            // Return as JSON
            header('Content-Type: application/json');
            echo json_encode(['image' => $base64_image]);
        } else {
            // Return empty or placeholder
            header('Content-Type: application/json');
            echo json_encode(['image' => null, 'error' => 'No image found']);
        }
    } else {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => 'Database error']);
    }
    oci_free_statement($stmt);
} else {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(['error' => 'Invalid product ID']);
}

oci_close($conn);
?>
