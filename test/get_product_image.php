<?php
require_once 'php_logic/connect.php'; // Connect to Oracle DB

$product_id = isset($_GET['id']) ? trim($_GET['id']) : '';

if (!empty($product_id)) {
    $query = "SELECT image FROM PRODUCT WHERE product_id = :product_id";
    $stmt = oci_parse($conn, $query);
    oci_bind_by_name($stmt, ':product_id', $product_id);
    if (oci_execute($stmt)) {
        $row = oci_fetch_assoc($stmt);
        if ($row && $row['IMAGE']) {
            header("Content-Type: image/jpeg"); // Adjust MIME type if needed (e.g., image/png)
            echo $row['IMAGE']->load();
        } else {
            // Serve a placeholder image if no image exists
            header("Content-Type: image/jpeg");
            readfile('assets/images/placeholder.jpg');
        }
    } else {
        header("HTTP/1.1 500 Internal Server Error");
    }
    oci_free_statement($stmt);
} else {
    header("HTTP/1.1 400 Bad Request");
}

oci_close($conn);
