<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as trader
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['usertype'] !== 'TRADER') {
    $_SESSION['login_error'] = "Please log in as a trader to access this page.";
    header("Location: login.php");
    exit;
}

require_once 'php_logic/connect.php'; // Connect to Oracle DB

// Get trader information
$user_id = $_SESSION['user_id'] ?? null;
$error = '';
$success = '';

// Check if trader already has 2 shops (maximum limit)
$query_shop_count = "SELECT COUNT(*) as shop_count FROM SHOP WHERE fk1_user_id = :user_id";
$stmt_shop_count = oci_parse($conn, $query_shop_count);
oci_bind_by_name($stmt_shop_count, ':user_id', $user_id);
oci_execute($stmt_shop_count);
$row_shop_count = oci_fetch_assoc($stmt_shop_count);
$shop_count = $row_shop_count['SHOP_COUNT'];
oci_free_statement($stmt_shop_count);

// If trader already has 2 shops, redirect to trader profile with error
if ($shop_count >= 2) {
    $_SESSION['error'] = "You have reached the maximum limit of 2 shops per trader.";
    header("Location: trader_profile.php");
    exit;
}

// Fetch product categories for dropdown
$query_categories = "SELECT name FROM PRODUCT_CATEGORY ORDER BY name";
$stmt_categories = oci_parse($conn, $query_categories);
oci_execute($stmt_categories);
$categories = [];
while ($row = oci_fetch_assoc($stmt_categories)) {
    $categories[] = $row['NAME'];
}
oci_free_statement($stmt_categories);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_shop'])) {
    $shop_name = trim($_POST['shop_name'] ?? '');
    $shop_type = trim($_POST['shop_type'] ?? '');
    
    // Validate inputs
    if (empty($shop_name)) {
        $error = "Shop name is required.";
    } elseif (empty($shop_type)) {
        $error = "Shop type is required.";
    } elseif (!in_array($shop_type, $categories)) {
        $error = "Invalid shop type selected.";
    } else {
        // Check if shop name already exists
        $query_check_name = "SELECT COUNT(*) as count FROM SHOP WHERE UPPER(name) = UPPER(:shop_name)";
        $stmt_check_name = oci_parse($conn, $query_check_name);
        oci_bind_by_name($stmt_check_name, ':shop_name', $shop_name);
        oci_execute($stmt_check_name);
        $row_check_name = oci_fetch_assoc($stmt_check_name);
        oci_free_statement($stmt_check_name);
        
        if ($row_check_name['COUNT'] > 0) {
            $error = "A shop with this name already exists. Please choose a different name.";
        } else {
            // Insert new shop with Pending approval status and current date for registered
            $query_insert = "INSERT INTO SHOP (name, type, registered, fk1_user_id, approval_status, action) 
                            VALUES (:shop_name, :shop_type, SYSDATE, :user_id, 'Pending', 'Added')";
            $stmt_insert = oci_parse($conn, $query_insert);
            oci_bind_by_name($stmt_insert, ':shop_name', $shop_name);
            oci_bind_by_name($stmt_insert, ':shop_type', $shop_type);
            oci_bind_by_name($stmt_insert, ':user_id', $user_id);
            
            if (oci_execute($stmt_insert)) {
                oci_commit($conn); // Explicitly commit the transaction
                $success = "Your additional shop has been submitted for admin verification. You will be notified once it's approved.";
            } else {
                $error = "Failed to add shop. Please try again.";
                $e = oci_error($stmt_insert);
                error_log("Database error: " . $e['message']);
            }
            oci_free_statement($stmt_insert);
        }
    }
}

include_once 'includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Shop - CleckBasket</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-3xl mx-auto bg-white rounded-lg shadow-md p-8">
            <h1 class="text-3xl font-bold text-center mb-8">Add a New Shop</h1>
            
            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                    <span class="block sm:inline"><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                    <span class="block sm:inline"><?php echo $success; ?></span>
                    <p class="mt-2">
                        <a href="trader_profile.php" class="underline">Return to your trader dashboard</a>
                    </p>
                </div>
            <?php else: ?>
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-6" role="alert">
                    <span class="block sm:inline">You can add one more shop to your account (maximum 2 shops per trader). The new shop will require admin verification before it becomes active.</span>
                </div>
                
                <form method="POST" action="add_shop.php" class="space-y-6">
                    <div class="bg-gray-50 p-4 rounded-lg mb-6">
                        <h2 class="text-xl font-semibold mb-4">New Shop Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="shop_name" class="block text-gray-700 mb-2">Shop Name</label>
                                <input type="text" id="shop_name" name="shop_name" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                            </div>
                            <div>
                                <label for="shop_type" class="block text-gray-700 mb-2">Shop Type</label>
                                <select id="shop_type" name="shop_type" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                                    <option value="">Select Shop Type</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <button type="submit" name="add_shop" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-6 rounded-lg">Add New Shop</button>
                        <a href="trader_profile.php" class="text-orange-500 hover:text-orange-700">Cancel and return to dashboard</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
if(isset($conn)) oci_close($conn);
include_once 'includes/footer.php';
?>