<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['login_error'] = "Please log in to access your account.";
    header("Location: login.php");
    exit;
}

require_once 'php_logic/connect.php'; // Connect to Oracle DB

// Initialize variables
$error = '';
$success = '';

// Get user information
$user_id = $_SESSION['user_id'] ?? null;
$usertype = $_SESSION['usertype'] ?? '';

// Check if user is a trader
$is_trader = ($usertype === 'trader');

// If not a trader but wants to become one
if (!$is_trader && isset($_POST['become_trader'])) {
    // Get form data
    $license = $_POST['license'] ?? '';
    $shop_name = $_POST['shop_name'] ?? '';
    $shop_type = $_POST['shop_type'] ?? '';
    
    // Validate form data
    if (empty($license) || empty($shop_name) || empty($shop_type)) {
        $error = "All fields are required.";
    } else {
        // Check if license already exists
        $query_check_license = "SELECT COUNT(*) as count FROM TRADER WHERE license = :license";
        $stmt_check_license = oci_parse($conn, $query_check_license);
        oci_bind_by_name($stmt_check_license, ':license', $license);
        oci_execute($stmt_check_license);
        $row_license = oci_fetch_assoc($stmt_check_license);
        oci_free_statement($stmt_check_license);
        
        if ($row_license['COUNT'] > 0) {
            $error = "License already exists. Please use a different license.";
        } else {
            // Check if shop name already exists
            $query_check_shop = "SELECT COUNT(*) as count FROM SHOP WHERE name = :shop_name";
            $stmt_check_shop = oci_parse($conn, $query_check_shop);
            oci_bind_by_name($stmt_check_shop, ':shop_name', $shop_name);
            oci_execute($stmt_check_shop);
            $row_shop = oci_fetch_assoc($stmt_check_shop);
            oci_free_statement($stmt_check_shop);
            
            if ($row_shop['COUNT'] > 0) {
                $error = "Shop name already exists. Please use a different name.";
            } else {
                // Begin transaction
                oci_execute(oci_parse($conn, "BEGIN TRANSACTION"));
                
                try {
                    // Update user type to trader
                    $new_usertype = 'trader';
                    $query_update_user = "UPDATE USERS SET usertype = :usertype WHERE user_id = :user_id";
                    $stmt_update_user = oci_parse($conn, $query_update_user);
                    oci_bind_by_name($stmt_update_user, ':usertype', $new_usertype);
                    oci_bind_by_name($stmt_update_user, ':user_id', $user_id);
                    
                    if (!oci_execute($stmt_update_user)) {
                        throw new Exception("Failed to update user account.");
                    }
                    oci_free_statement($stmt_update_user);
                    
                    // Insert into TRADER table
                    $query_insert_trader = "INSERT INTO TRADER (user_id, license) VALUES (:user_id, :license)";
                    $stmt_insert_trader = oci_parse($conn, $query_insert_trader);
                    oci_bind_by_name($stmt_insert_trader, ':user_id', $user_id);
                    oci_bind_by_name($stmt_insert_trader, ':license', $license);
                    
                    if (!oci_execute($stmt_insert_trader)) {
                        throw new Exception("Failed to create trader account.");
                    }
                    oci_free_statement($stmt_insert_trader);
                    
                    // Insert into SHOP table
                    $current_date = date('Y-m-d');
                    $query_insert_shop = "INSERT INTO SHOP (name, type, registered, fk1_user_id) 
                                         VALUES (:name, :type, TO_DATE(:registered, 'YYYY-MM-DD'), :user_id)";
                    $stmt_insert_shop = oci_parse($conn, $query_insert_shop);
                    oci_bind_by_name($stmt_insert_shop, ':name', $shop_name);
                    oci_bind_by_name($stmt_insert_shop, ':type', $shop_type);
                    oci_bind_by_name($stmt_insert_shop, ':registered', $current_date);
                    oci_bind_by_name($stmt_insert_shop, ':user_id', $user_id);
                    
                    if (!oci_execute($stmt_insert_shop)) {
                        throw new Exception("Failed to create shop.");
                    }
                    oci_free_statement($stmt_insert_shop);
                    
                    // Commit transaction
                    oci_execute(oci_parse($conn, "COMMIT"));
                    
                    // Update session
                    $_SESSION['usertype'] = 'trader';
                    
                    // Set success message
                    $success = "Congratulations! You are now a trader. Redirecting to trader dashboard...";
                    
                    // Redirect to trader dashboard after a delay
                    header("Refresh: 3; URL=trader_profile.php");
                    
                } catch (Exception $e) {
                    // Rollback transaction on error
                    oci_execute(oci_parse($conn, "ROLLBACK"));
                    $error = $e->getMessage();
                }
            }
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
    <title>CleckBasket - Become a Trader</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-3xl mx-auto bg-white rounded-lg shadow-md p-8">
            <h1 class="text-3xl font-bold text-center mb-8">Become a Trader</h1>
            
            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                    <span class="block sm:inline"><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                    <span class="block sm:inline"><?php echo $success; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($is_trader): ?>
                <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-6" role="alert">
                    <span class="block sm:inline">You are already registered as a trader. <a href="trader_profile.php" class="underline">Go to your trader dashboard</a>.</span>
                </div>
            <?php else: ?>
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-6" role="alert">
                    <span class="block sm:inline">Become a trader to sell your products on CleckBasket. Fill out the form below to get started.</span>
                </div>
                
                <form method="POST" action="become_trader.php" class="space-y-6">
                    <div class="bg-gray-50 p-4 rounded-lg mb-6">
                        <h2 class="text-xl font-semibold mb-4">Trader Information</h2>
                        <div class="mb-4">
                            <label for="license" class="block text-gray-700 mb-2">Trader License Number</label>
                            <input type="text" id="license" name="license" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                            <p class="text-xs text-gray-500 mt-1">Enter your business license or registration number</p>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg mb-6">
                        <h2 class="text-xl font-semibold mb-4">Shop Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="shop_name" class="block text-gray-700 mb-2">Shop Name</label>
                                <input type="text" id="shop_name" name="shop_name" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                            </div>
                            <div>
                                <label for="shop_type" class="block text-gray-700 mb-2">Shop Type</label>
                                <select id="shop_type" name="shop_type" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                                    <option value="">Select Shop Type</option>
                                    <option value="Grocery">Grocery</option>
                                    <option value="Electronics">Electronics</option>
                                    <option value="Clothing">Clothing</option>
                                    <option value="Home & Garden">Home & Garden</option>
                                    <option value="Beauty & Health">Beauty & Health</option>
                                    <option value="Sports & Outdoors">Sports & Outdoors</option>
                                    <option value="Toys & Games">Toys & Games</option>
                                    <option value="Books & Stationery">Books & Stationery</option>
                                    <option value="Food & Beverage">Food & Beverage</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <button type="submit" name="become_trader" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-6 rounded-lg">Become a Trader</button>
                        <a href="index.php" class="text-orange-500 hover:text-orange-700">Cancel and return to homepage</a>
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
