<?php


// Start a session if not already started (some projects might require this)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set error reporting for debugging (remove or adjust for production)
error_reporting(E_ALL);
ini_set("display_errors", 1);

// Output header for browser viewing
if (php_sapi_name() !== 'cli') {
    echo "<pre>";
}

echo "Attempting to connect to Oracle database...\n";

// Include the database connection script
// The connect.php script will attempt to establish a connection and store it in $conn
// It will also handle and display connection errors if they occur, then exit.
require_once __DIR__ . 
'/php_logic/connect.php';

// If connect.php executed without exiting, the connection was successful.
echo "-------------------------------------\n";
echo "Oracle Database Connection Successful!\n";
echo "Connection resource: ";
print_r($conn);
echo "\n-------------------------------------\n\n";

echo "Attempting to query the PRODUCT table...\n";

// Test Query: Select a few products
// Using ROWNUM to limit results as an example, adjust table/column names if they differ in your schema.
$query = "SELECT PRODUCT_ID, PRODUCT_NAME, PRICE, STOCK_QUANTITY FROM PRODUCT WHERE ROWNUM <= 5";

$statement = oci_parse($conn, $query);

if (!$statement) {
    $e = oci_error($conn); // Get error associated with the connection
    echo "Oracle Query Parse Error!\n";
    echo "Error Code: " . htmlentities($e["code"]) . "\n";
    echo "Error Message: " . htmlentities($e["message"]) . "\n";
    if (php_sapi_name() !== 'cli') {
        echo "</pre>";
    }
    oci_close($conn); // Close the connection
    exit;
}

$executed = @oci_execute($statement);

if (!$executed) {
    $e = oci_error($statement); // Get error associated with the statement
    echo "Oracle Query Execution Error!\n";
    echo "Error Code: " . htmlentities($e["code"]) . "\n";
    echo "Error Message: " . htmlentities($e["message"]) . "\n";
    echo "Attempted Query: " . htmlentities($query) . "\n";
    if (php_sapi_name() !== 'cli') {
        echo "</pre>";
    }
    oci_free_statement($statement);
    oci_close($conn); // Close the connection
    exit;
}

echo "Query executed successfully. Fetching results...\n\n";

$products_found = 0;
// Fetch and display results
// Using oci_fetch_assoc for associative array results
echo "Products from PRODUCT table (up to 5):
";
echo "-------------------------------------------------------------------------------------
";
printf("%-10s | %-30s | %-10s | %-15s\n", "ID", "Name", "Price", "Stock Quantity");
echo "-------------------------------------------------------------------------------------
";

while ($row = oci_fetch_assoc($statement)) {
    $products_found++;
    printf("%-10s | %-30s | %-10.2f | %-15s\n",
        htmlentities($row["PRODUCT_ID"]),
        htmlentities($row["PRODUCT_NAME"]),
        htmlentities($row["PRICE"]),
        htmlentities($row["STOCK_QUANTITY"])
    );
}
echo "-------------------------------------------------------------------------------------
";

if ($products_found == 0) {
    echo "No products found in the PRODUCT table, or the table does not exist/is not accessible with the current user.\n";
    echo "Please ensure the PRODUCT table exists and contains data, and that the user in connect.php has SELECT privileges on it.\n";
}

echo "\nTest script finished.\n";

// Clean up: Free statement and close connection
oci_free_statement($statement);
oci_close($conn);
echo "Oracle connection closed.\n";

if (php_sapi_name() !== 'cli') {
    echo "</pre>";
}

?>

