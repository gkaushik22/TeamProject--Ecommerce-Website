<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as trader
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['usertype'] !== 'TRADER') {
    error_log("Access denied: loggedin=" . ($_SESSION['loggedin'] ?? 'not set') . ", usertype=" . ($_SESSION['usertype'] ?? 'not set'));
    $_SESSION['login_error'] = "Please log in as a trader to access the dashboard.";
    header("Location: login.php");
    exit;
}

require_once 'php_logic/connect.php'; // Connect to Oracle DB

// Get trader information
$user_id = $_SESSION['user_id'] ?? null;

// Get shop information for this trader
$shop_id = null;
$shop_name = "";
$query_shop = "SELECT shop_id, name, approval_status FROM SHOP WHERE fk1_user_id = :user_id";
$stmt_shop = oci_parse($conn, $query_shop);
oci_bind_by_name($stmt_shop, ':user_id', $user_id);
oci_execute($stmt_shop);
$shop_row = oci_fetch_assoc($stmt_shop);
oci_free_statement($stmt_shop);

if ($shop_row) {
    $shop_id = $shop_row['SHOP_ID'];
    $shop_name = $shop_row['NAME'];
    if ($shop_row['APPROVAL_STATUS'] !== 'Approved') {
        $_SESSION['error_message'] = "Your shop is not approved yet. Contact admin to manage products.";
        header("Location: trader_dashboard.php?t=" . time());
        exit;
    }
} else {
    header("Location: create_shop.php");
    exit;
}

// Handle product status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    error_log("Toggle status POST received: product_id=" . ($_POST['product_id'] ?? 'not set') .
        ", toggle_status=" . ($_POST['toggle_status'] ?? 'not set'));

    $product_id = $_POST['product_id'] ?? '';
    $current_status = null;
    $toggle_status = 'Disable'; // Default to Disable if something goes wrong

    if (!empty($product_id) && is_numeric($product_id)) {
        // Get current status to determine the new status
        $query_check = "SELECT status, fk1_shop_id, fk3_discount_id FROM PRODUCT WHERE product_id = :product_id AND fk1_shop_id = :shop_id";
        $stmt_check = oci_parse($conn, $query_check);
        oci_bind_by_name($stmt_check, ':product_id', $product_id);
        oci_bind_by_name($stmt_check, ':shop_id', $shop_id);
        if (!oci_execute($stmt_check)) {
            $e = oci_error($stmt_check);
            error_log("Failed to fetch current status: " . $e['message']);
            $_SESSION['error_message'] = "Failed to fetch product status: " . htmlentities($e['message']);
            header("Location: trader_manage_products.php?t=" . time() . "&nocache=" . uniqid());
            exit;
        }
        $row = oci_fetch_assoc($stmt_check);
        oci_free_statement($stmt_check);

        if ($row) {
            $current_status = $row['STATUS'] ?? 'Disable'; // Default to 'Disable' if NULL
            error_log("Fetched current status: product_id=$product_id, status=$current_status");

            // Toggle logic: Flip the current status
            $toggle_status = ($current_status === 'Enable') ? 'Disable' : 'Enable';
            $discount_id = $row['FK3_DISCOUNT_ID'];

            // Check for expired discount
            if ($discount_id !== null) {
                $query_discount = "SELECT valid_upto FROM DISCOUNT WHERE discount_id = :discount_id";
                $stmt_discount = oci_parse($conn, $query_discount);
                oci_bind_by_name($stmt_discount, ':discount_id', $discount_id);
                if (!oci_execute($stmt_discount)) {
                    $e = oci_error($stmt_discount);
                    error_log("Failed to fetch discount: " . $e['message']);
                    $_SESSION['error_message'] = "Failed to validate discount: " . htmlentities($e['message']);
                    header("Location: trader_manage_products.php?t=" . time() . "&nocache=" . uniqid());
                    exit;
                }
                $discount_row = oci_fetch_assoc($stmt_discount);
                oci_free_statement($stmt_discount);

                if ($discount_row && $discount_row['VALID_UPTO'] < date('Y-m-d H:i:s', strtotime('now'))) {
                    error_log("Expired discount detected for product_id=$product_id, discount_id=$discount_id");
                    $_SESSION['error_message'] = "Cannot update status: Product has an expired discount. Please remove the discount.";
                    header("Location: trader_manage_products.php?t=" . time() . "&nocache=" . uniqid());
                    exit;
                }
            }

            // Update product status
            $query_update = "UPDATE PRODUCT SET status = :status, action = 'Update' 
                            WHERE product_id = :product_id AND fk1_shop_id = :shop_id";
            $stmt_update = oci_parse($conn, $query_update);
            oci_bind_by_name($stmt_update, ':status', $toggle_status);
            oci_bind_by_name($stmt_update, ':product_id', $product_id);
            oci_bind_by_name($stmt_update, ':shop_id', $shop_id);

            if (!oci_execute($stmt_update, OCI_DEFAULT)) {
                $e = oci_error($stmt_update);
                error_log("Failed to execute update query: " . $e['message']);
                $_SESSION['error_message'] = "Failed to update status: " . htmlentities($e['message']);
            } else {
                $rows_affected = oci_num_rows($stmt_update);
                error_log("Update attempted: rows_affected=$rows_affected, new_status=$toggle_status, product_id=$product_id, shop_id=$shop_id, current_status=$current_status");
                if ($rows_affected == 0) {
                    error_log("No rows updated for product_id=$product_id, shop_id=$shop_id");
                    $_SESSION['error_message'] = "Status update failed: Product not found or unchanged.";
                } else {
                    // Force commit to ensure update persists
                    if (oci_commit($conn)) {
                        error_log("Transaction committed successfully for product_id=$product_id");
                        $_SESSION['success_message'] = "Product status updated to $toggle_status successfully.";
                    } else {
                        error_log("Failed to commit transaction for product_id=$product_id");
                        $_SESSION['error_message'] = "Status update failed: Could not commit transaction.";
                        oci_rollback($conn);
                    }
                }
            }
            oci_free_statement($stmt_update);
        } else {
            error_log("No product found for product_id=$product_id, shop_id=$shop_id");
            $_SESSION['error_message'] = "Product not found or you do not own it.";
        }
    } else {
        error_log("Invalid or missing product ID: " . ($product_id ?? 'not set'));
        $_SESSION['error_message'] = "Invalid product ID.";
    }

    header("Location: trader_manage_products.php?t=" . time() . "&nocache=" . uniqid());
    exit;
}

// Handle product deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $product_id = $_POST['product_id'] ?? '';

    if (!empty($product_id) && is_numeric($product_id)) {
        $query_update_action = "UPDATE PRODUCT SET action = 'Delete' 
                               WHERE product_id = :product_id AND fk1_shop_id = :shop_id";
        $stmt_update_action = oci_parse($conn, $query_update_action);
        oci_bind_by_name($stmt_update_action, ':product_id', $product_id);
        oci_bind_by_name($stmt_update_action, ':shop_id', $shop_id);
        oci_execute($stmt_update_action);
        oci_free_statement($stmt_update_action);

        $query_delete = "DELETE FROM PRODUCT WHERE product_id = :product_id AND fk1_shop_id = :shop_id";
        $stmt_delete = oci_parse($conn, $query_delete);
        oci_bind_by_name($stmt_delete, ':product_id', $product_id);
        oci_bind_by_name($stmt_delete, ':shop_id', $shop_id);
        oci_execute($stmt_delete);
        oci_free_statement($stmt_delete);
        oci_commit($conn);
        $_SESSION['success_message'] = "Product deleted successfully.";
    } else {
        $_SESSION['error_message'] = "Invalid product ID for deletion.";
    }

    header("Location: trader_manage_products.php?t=" . time());
    exit;
}

// Get products for this shop with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

// Build the query based on filters
$where_clause = "WHERE p.fk1_shop_id = :shop_id";

if ($filter_status === 'active') {
    $where_clause .= " AND p.status = 'Enable'";
} elseif ($filter_status === 'inactive') {
    $where_clause .= " AND p.status = 'Disable'";
}

if (!empty($search_term)) {
    $where_clause .= " AND LOWER(p.name) LIKE LOWER('%' || :search_term || '%')";
}

if (!empty($category_filter)) {
    $where_clause .= " AND p.fk2_category_id = :category_id";
}

// Count total products for pagination
$query_count = "SELECT COUNT(*) as total FROM PRODUCT p $where_clause";
$stmt_count = oci_parse($conn, $query_count);
oci_bind_by_name($stmt_count, ':shop_id', $shop_id);

if (!empty($search_term)) {
    oci_bind_by_name($stmt_count, ':search_term', $search_term);
}

if (!empty($category_filter)) {
    oci_bind_by_name($stmt_count, ':category_id', $category_filter);
}

oci_execute($stmt_count);
$row_count = oci_fetch_assoc($stmt_count);
$total_products = $row_count['TOTAL'];
$total_pages = ceil($total_products / $items_per_page);
oci_free_statement($stmt_count);

// Get products with category name
$query_products = "SELECT p.product_id, p.name, p.price, p.stock, p.status, pc.name as category_name
                  FROM PRODUCT p
                  JOIN PRODUCT_CATEGORY pc ON p.fk2_category_id = pc.category_id
                  $where_clause
                  ORDER BY p.product_id DESC";

$query_products = "SELECT * FROM (
                    SELECT a.*, rownum rnum FROM (
                        $query_products
                    ) a WHERE rownum <= :max_row
                  ) WHERE rnum > :min_row";

$stmt_products = oci_parse($conn, $query_products);
oci_bind_by_name($stmt_products, ':shop_id', $shop_id);
$max_row = $offset + $items_per_page;
$min_row = $offset;
oci_bind_by_name($stmt_products, ':max_row', $max_row);
oci_bind_by_name($stmt_products, ':min_row', $min_row);

if (!empty($search_term)) {
    oci_bind_by_name($stmt_products, ':search_term', $search_term);
}

if (!empty($category_filter)) {
    oci_bind_by_name($stmt_products, ':category_id', $category_filter);
}

oci_execute($stmt_products);

// Get all categories for filter dropdown
$categories = [];
$query_categories = "SELECT category_id, name FROM PRODUCT_CATEGORY";
$stmt_categories = oci_parse($conn, $query_categories);
oci_execute($stmt_categories);
while ($row = oci_fetch_assoc($stmt_categories)) {
    $categories[] = $row;
}
oci_free_statement($stmt_categories);

include_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CleckBasket Trader Center - Manage Products</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            overflow-x: hidden;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: #f97316;
        }

        input:checked+.slider:before {
            transform: translateX(26px);
        }

        .loading {
            display: none;
            font-size: 0.75rem;
            color: #666;
        }

        @media (max-width: 640px) {
            .mobile-stack {
                display: block;
                width: 100%;
            }

            .mobile-hidden {
                display: none;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="flex flex-col md:flex-row min-h-screen">
        <!-- Sidebar -->
        <aside id="sidebar" class="w-full md:w-64 bg-white shadow-md md:min-h-screen hidden md:block">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center mr-3">
                        <img src="assets/images/CLeckBasketLogo.jpg" alt="CleckBasket Logo" class="w-full h-full object-contain rounded-lg">
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">CleckBasket</h1>
                        <h2 class="text-lg text-gray-600">Trader Center</h2>
                    </div>
                </div>
            </div>
            <nav class="mt-6">
                <div class="px-4 py-2">
                    <button class="w-full flex items-center justify-between text-gray-700 hover:bg-gray-100 rounded-md p-2 sidebar-item">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 mr-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <rect x="2" y="3" width="20" height="18" rx="2" stroke-width="2" />
                                <line x1="8" y1="3" x2="8" y2="21" stroke-width="2" />
                            </svg>
                            <span class="text-lg font-medium">Products</span>
                        </div>
                        <svg class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="ml-9 mt-2 hidden sidebar-content">
                        <a href="trader_manage_products.php" class="block py-2 text-orange-500 font-semibold">Manage Products</a>
                        <a href="trader_add_product.php" class="block py-2 text-gray-600 hover:text-orange-500">Add Products</a>
                    </div>
                </div>
                <div class="px-4 py-2">
                    <button class="w-full flex items-center justify-between text-gray-700 hover:bg-gray-100 rounded-md p-2 sidebar-item">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 mr-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            <span class="text-lg font-medium">Orders & Review</span>
                        </div>
                        <svg class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="ml-9 mt-2 hidden sidebar-content">
                        <a href="trader_orders.php" class="block py-2 text-gray-600 hover:text-orange-500">Orders</a>
                        <a href="#" class="block py-2 text-gray-600 hover:text-orange-500">Return Orders</a>
                        <a href="#" class="block py-2 text-gray-600 hover:text-orange-500">Reviews</a>
                    </div>
                </div>
                <div class="px-4 py-2">
                    <button class="w-full flex items-center justify-between text-gray-700 hover:bg-gray-100 rounded-md p-2 sidebar-item">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 mr-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-lg font-medium">Finance</span>
                        </div>
                        <svg class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="ml-9 mt-2 hidden sidebar-content">
                        <a href="#" class="block py-2 text-gray-600 hover:text-orange-500">My Income</a>
                    </div>
                </div>
                <div class="px-4 py-2">
                    <button class="w-full flex items-center justify-between text-gray-700 hover:bg-gray-100 rounded-md p-2 sidebar-item">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 mr-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <span class="text-lg font-medium">My Account</span>
                        </div>
                        <svg class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="ml-9 mt-2 hidden sidebar-content">
                        <a href="trader_profile.php" class="block py-2 text-gray-600 hover:text-orange-500">Settings</a>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1">
            <div class="container mx-auto px-4 py-6 sm:px-6 sm:py-8">
                <!-- Banner -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6 text-center">
                    <h2 class="text-3xl font-bold text-gray-800">Manage Your Products</h2>
                    <p class="text-gray-600 mt-2">View, edit, and organize your product listings.</p>
                </div>

                <!-- Success/Error Messages -->
                <?php if (!empty($_SESSION['success_message'])): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (!empty($_SESSION['error_message'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Tabs -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="border-b border-gray-200 mb-4">
                        <div class="flex flex-wrap space-x-4 sm:space-x-6">
                            <a href="?status=all" class="py-2 px-4 <?php echo $filter_status === 'all' ? 'border-b-2 border-orange-500 text-orange-500' : 'text-gray-500'; ?>">All</a>
                            <a href="?status=active" class="py-2 px-4 <?php echo $filter_status === 'active' ? 'border-b-2 border-orange-500 text-orange-500' : 'text-gray-500'; ?>">Active</a>
                            <a href="?status=inactive" class="py-2 px-4 <?php echo $filter_status === 'inactive' ? 'border-b-2 border-orange-500 text-orange-500' : 'text-gray-500'; ?>">Inactive</a>
                            <a href="#" class="py-2 px-4 text-gray-500">Draft</a>
                            <a href="#" class="py-2 px-4 text-gray-500">Violation</a>
                            <a href="#" class="py-2 px-4 text-gray-500">Deleted</a>
                        </div>
                    </div>

                    <!-- Search and Filter -->
                    <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
                        <form action="" method="GET" class="flex-1">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                            <div class="flex items-center">
                                <div class="bg-gray-200 px-4 py-2 rounded-l-md flex items-center text-gray-600">Product Name</div>
                                <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search_term); ?>" class="flex-1 border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500 rounded-r-md">
                                <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-md ml-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </button>
                            </div>
                        </form>
                        <div class="w-full sm:w-auto">
                            <select name="category" onchange="this.form.submit()" form="category-form" class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['CATEGORY_ID']; ?>" <?php echo $category_filter === $category['CATEGORY_ID'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['NAME']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <form id="category-form" method="GET">
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Products List -->
                <div class="bg-white rounded-lg shadow-md overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Details</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Content Score</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Active</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php
                            if (oci_fetch_all($stmt_products, $products, 0, -1, OCI_FETCHSTATEMENT_BY_ROW) > 0) {
                                foreach ($products as $product) {
                            ?>
                                    <tr class="mobile-stack">
                                        <td class="px-4 py-4 whitespace-nowrap sm:table-cell block">
                                            <div class="flex items-center sm:block">
                                                <div class="flex-shrink-0 h-10 w-10 bg-gray-200 rounded sm:mb-2">
                                                    <?php if (isset($product['PRODUCT_ID'])): ?>
                                                        <a href="product_detail.php?id=<?php echo htmlspecialchars($product['PRODUCT_ID']); ?>">
                                                            <img src="get_product_image.php?id=<?php echo htmlspecialchars($product['PRODUCT_ID']); ?>"
                                                                alt="<?php echo htmlspecialchars($product['NAME']); ?>"
                                                                class="h-10 w-10 object-cover rounded">
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="ml-4 sm:ml-0">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['NAME']); ?></div>
                                                    <div class="text-sm text-gray-500">Category: <?php echo htmlspecialchars($product['CATEGORY_NAME']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap sm:table-cell block">
                                            <span class="text-sm text-gray-900 sm:table-cell block"><?php echo $product['STATUS'] == 'Enable' ? 'Qualified' : 'To be Improved'; ?></span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap sm:table-cell block">
                                            <span class="text-sm text-gray-900 sm:table-cell block">$<?php echo number_format($product['PRICE'], 2); ?></span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap sm:table-cell block">
                                            <span class="text-sm text-gray-900 sm:table-cell block"><?php echo $product['STOCK']; ?></span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap sm:table-cell block">
                                            <form method="POST" action="" class="inline" id="toggle-form-<?php echo $product['PRODUCT_ID']; ?>">
                                                <input type="hidden" name="product_id" value="<?php echo $product['PRODUCT_ID']; ?>">
                                                <label class="toggle-switch">
                                                    <input type="checkbox" name="toggle_status" <?php echo $product['STATUS'] == 'Enable' ? 'checked' : ''; ?>
                                                        onchange="submitToggleForm(<?php echo $product['PRODUCT_ID']; ?>)">
                                                    <span class="slider"></span>
                                                </label>
                                                <span id="loading-<?php echo $product['PRODUCT_ID']; ?>" class="loading">Updating...</span>
                                            </form>
                                            <div class="text-xs text-gray-500 mt-1"><?php echo $product['STATUS']; ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap sm:table-cell block text-sm font-medium">
                                            <div class="space-y-2 sm:space-y-0 sm:space-x-2">
                                                <a href="trader_edit_product.php?id=<?php echo $product['PRODUCT_ID']; ?>" class="text-blue-600 hover:text-blue-900 block sm:inline">Edit</a>
                                                <form method="POST" action="" class="inline" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['PRODUCT_ID']; ?>">
                                                    <button type="submit" name="delete_product" class="text-red-600 hover:text-red-900 block sm:inline">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php
                                }
                            } else {
                                ?>
                                <tr class="mobile-stack">
                                    <td colspan="6" class="px-4 py-4 text-center text-gray-500 block">
                                        No products found. <a href="trader_add_product.php" class="text-blue-600">Add a product</a>
                                    </td>
                                </tr>
                            <?php
                            }
                            oci_free_statement($stmt_products);
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="flex justify-center mt-6">
                        <nav class="inline-flex rounded-md shadow">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search_term); ?>&category=<?php echo $category_filter; ?>" class="px-3 py-2 bg-gray-300 text-gray-700 rounded-l-md hover:bg-gray-400">Previous</a>
                            <?php else: ?>
                                <span class="px-3 py-2 bg-gray-200 text-gray-400 rounded-l-md">Previous</span>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?page=<?php echo $i; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search_term); ?>&category=<?php echo $category_filter; ?>"
                                    class="px-3 py-2 <?php echo $i === $page ? 'bg-orange-500 text-white' : 'bg-gray-300 text-gray-700 hover:bg-gray-400'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search_term); ?>&category=<?php echo $category_filter; ?>" class="px-3 py-2 bg-gray-300 text-gray-700 rounded-r-md hover:bg-gray-400">Next</a>
                            <?php else: ?>
                                <span class="px-3 py-2 bg-gray-200 text-gray-400 rounded-r-md">Next</span>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Sidebar item toggle
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarItems = document.querySelectorAll('.sidebar-item');
            sidebarItems.forEach(item => {
                item.addEventListener('click', function() {
                    const content = this.nextElementSibling;
                    const arrow = this.querySelector('svg:last-child');
                    content.classList.toggle('hidden');
                    arrow.classList.toggle('rotate-180');
                });
            });
        });

        // Toggle form submission with loading indicator
        function submitToggleForm(productId) {
            const form = document.getElementById(`toggle-form-${productId}`);
            const checkbox = form.querySelector('input[name="toggle_status"]');
            const loading = document.getElementById(`loading-${productId}`);
            if (form && checkbox && loading) {
                checkbox.disabled = true;
                loading.style.display = 'inline';
                form.submit();
            }
        }
    </script>
</body>

</html>

<?php
if (isset($conn)) oci_close($conn);
include_once 'includes/footer.php';
?>