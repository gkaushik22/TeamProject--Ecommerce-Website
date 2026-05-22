<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as trader
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['usertype'] !== 'TRADER') {
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
$query_shop = "SELECT shop_id, name, ACTION FROM SHOP WHERE fk1_user_id = :user_id";
$stmt_shop = oci_parse($conn, $query_shop);
oci_bind_by_name($stmt_shop, ':user_id', $user_id);
oci_execute($stmt_shop);
$shop_row = oci_fetch_assoc($stmt_shop);
oci_free_statement($stmt_shop);

if ($shop_row) {
    $shop_id = $shop_row['SHOP_ID'];
    $shop_name = $shop_row['NAME'];
} else {
    header("Location: create_shop.php");
    exit;
}

// Handle order status update
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'] ?? '';
    $new_status = $_POST['new_status'] ?? '';

    if (!empty($order_id) && !empty($new_status)) {
        $query_verify = "SELECT COUNT(*) as count FROM ORDERR o
                         JOIN ORDER_PRODUCT op ON o.order_id = op.order_id
                         JOIN PRODUCT p ON op.product_id = p.product_id
                         WHERE o.order_id = :order_id AND p.fk1_shop_id = :shop_id AND p.status = 'Enable'";
        $stmt_verify = oci_parse($conn, $query_verify);
        oci_bind_by_name($stmt_verify, ':order_id', $order_id);
        oci_bind_by_name($stmt_verify, ':shop_id', $shop_id);
        oci_execute($stmt_verify);
        $row_verify = oci_fetch_assoc($stmt_verify);
        oci_free_statement($stmt_verify);

        if ($row_verify && $row_verify['COUNT'] > 0) {
            $query_update = "UPDATE ORDERR SET status = :status WHERE order_id = :order_id";
            $stmt_update = oci_parse($conn, $query_update);
            oci_bind_by_name($stmt_update, ':status', $new_status);
            oci_bind_by_name($stmt_update, ':order_id', $order_id);

            if (oci_execute($stmt_update)) {
                $success_message = "Order status updated successfully!";
            } else {
                $error_message = "Failed to update order status.";
            }

            oci_free_statement($stmt_update);
        } else {
            $error_message = "You don't have permission to update this order.";
        }
    } else {
        $error_message = "Invalid order information.";
    }
}

// Get orders for this shop with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

// Build the query based on filters
$where_clause = "WHERE p.fk1_shop_id = :shop_id AND p.status = 'Enable'";

if ($filter_status === 'unpaid') {
    $where_clause .= " AND o.status = 'Unpaid'";
} elseif ($filter_status === 'to_ship') {
    $where_clause .= " AND o.status = 'Pending'";
} elseif ($filter_status === 'delivered') {
    $where_clause .= " AND o.status = 'Delivered'";
} elseif ($filter_status === 'return') {
    $where_clause .= " AND o.status = 'Return' OR o.status = 'Refund'";
}

if (!empty($search_term)) {
    $where_clause .= " AND (LOWER(p.name) LIKE LOWER('%' || :search_term || '%') OR LOWER(u.first_name || ' ' || u.last_name) LIKE LOWER('%' || :search_term || '%'))";
}

if (!empty($category_filter)) {
    $where_clause .= " AND p.fk2_category_id = :category_id";
}

// Count total orders for pagination
$query_count = "SELECT COUNT(DISTINCT o.order_id) as total 
                FROM ORDERR o
                JOIN ORDER_PRODUCT op ON o.order_id = op.order_id
                JOIN PRODUCT p ON op.product_id = p.product_id
                JOIN USERS u ON o.fk1_user_id = u.user_id
                $where_clause";
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
$total_orders = $row_count['TOTAL'];
$total_pages = ceil($total_orders / $items_per_page);
oci_free_statement($stmt_count);

// Get orders with product and customer details
$query_orders = "SELECT DISTINCT o.order_id, o.total_amount, o.status, o.placed_on,
                u.first_name, u.last_name, cs.scheduled_day, cs.scheduled_time
                FROM ORDERR o
                JOIN ORDER_PRODUCT op ON o.order_id = op.order_id
                JOIN PRODUCT p ON op.product_id = p.product_id
                JOIN USERS u ON o.fk1_user_id = u.user_id
                JOIN COLLECTION_SLOT cs ON o.fk4_slot_id = cs.slot_id
                $where_clause
                ORDER BY o.placed_on DESC";

$query_orders = "SELECT * FROM (
                    SELECT a.*, rownum rnum FROM (
                        $query_orders
                    ) a WHERE rownum <= :max_row
                  ) WHERE rnum > :min_row";

$stmt_orders = oci_parse($conn, $query_orders);
oci_bind_by_name($stmt_orders, ':shop_id', $shop_id);
$max_row = $offset + $items_per_page;
$min_row = $offset;
oci_bind_by_name($stmt_orders, ':max_row', $max_row);
oci_bind_by_name($stmt_orders, ':min_row', $min_row);

if (!empty($search_term)) {
    oci_bind_by_name($stmt_orders, ':search_term', $search_term);
}

if (!empty($category_filter)) {
    oci_bind_by_name($stmt_orders, ':category_id', $category_filter);
}

oci_execute($stmt_orders);

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
    <title>CleckBasket Trader Center - Order Management</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            overflow-x: hidden;
        }

        .sidebar-item:hover {
            background-color: #f3f4f6;
        }

        .active-tab {
            border-bottom: 2px solid #f97316;
            color: #f97316;
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
                <!-- Products Section -->
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
                        <a href="trader_manage_products.php" class="block py-2 text-gray-600 hover:text-orange-500">Manage Products</a>
                        <a href="trader_add_product.php" class="block py-2 text-gray-600 hover:text-orange-500">Add Products</a>
                        <a href="#" class="block py-2 text-gray-600 hover:text-orange-500">Brand Management</a>
                    </div>
                </div>
                <!-- Orders & Review Section -->
                <div class="px-4 py-2">
                    <button class="w-full flex items-center justify-between text-gray-700 hover:bg-gray-100 rounded-md p-2 sidebar-item">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 mr-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            <span class="text-lg font-medium">Orders & Review</span>
                        </div>
                        <svg class="w-5 h-5 transition-transform rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="ml-9 mt-2 sidebar-content">
                        <a href="trader_orders.php" class="block py-2 text-orange-500 font-semibold">Orders</a>
                        <a href="#" class="block py-2 text-gray-600 hover:text-orange-500">Return Orders</a>
                        <a href="#" class="block py-2 text-gray-600 hover:text-orange-500">Reviews</a>
                    </div>
                </div>
                <!-- Store Section -->
                <div class="px-4 py-2">
                    <button class="w-full flex items-center justify-between text-gray-700 hover:bg-gray-100 rounded-md p-2 sidebar-item">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 mr-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                            </svg>
                            <span class="text-lg font-medium">Store</span>
                        </div>
                        <svg class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="ml-9 mt-2 hidden sidebar-content">
                        <a href="#" class="block py-2 text-gray-600 hover:text-orange-500">Store Decoration</a>
                        <a href="#" class="block py-2 text-gray-600 hover:text-orange-500">Store Settings</a>
                    </div>
                </div>
                <!-- Finance Section -->
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
                <!-- My Account Section -->
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
                <!-- Mobile Header -->
                <header class="md:hidden bg-white shadow-md p-4 flex justify-between items-center">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-gray-200 rounded-lg flex items-center justify-center mr-3">
                            <img src="assets/images/CLeckBasketLogo.jpg" alt="CleckBasket Logo" class="w-full h-full object-contain rounded-lg">
                        </div>
                        <h1 class="text-lg font-bold text-gray-800">CleckBasket</h1>
                    </div>
                    <button id="menu-toggle" class="text-gray-600 focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                        </svg>
                    </button>
                </header>

                <!-- Banner -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6 text-center">
                    <h2 class="text-3xl font-bold text-gray-800">Order Management</h2>
                    <p class="text-gray-600 mt-2">View and manage your customer orders.</p>
                </div>

                <!-- Messages -->
                <?php if (!empty($success_message)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Tabs -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="border-b border-gray-200 mb-4">
                        <div class="flex flex-wrap space-x-4 sm:space-x-6">
                            <a href="?status=all" class="py-2 px-4 <?php echo $filter_status === 'all' ? 'active-tab' : 'text-gray-500'; ?>">All</a>
                            <a href="?status=unpaid" class="py-2 px-4 <?php echo $filter_status === 'unpaid' ? 'active-tab' : 'text-gray-500'; ?>">Unpaid</a>
                            <a href="?status=to_ship" class="py-2 px-4 <?php echo $filter_status === 'to_ship' ? 'active-tab' : 'text-gray-500'; ?>">To Ship</a>
                            <a href="?status=delivered" class="py-2 px-4 <?php echo $filter_status === 'delivered' ? 'active-tab' : 'text-gray-500'; ?>">Delivered</a>
                            <a href="?status=return" class="py-2 px-4 <?php echo $filter_status === 'return' ? 'active-tab' : 'text-gray-500'; ?>">Return/Refund</a>
                        </div>
                    </div>

                    <!-- Search and Filter -->
                    <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
                        <form action="" method="GET" class="flex-1">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                            <div class="flex items-center">
                                <div class="bg-gray-200 px-4 py-2 rounded-l-md flex items-center text-gray-600">Search</div>
                                <input type="text" name="search" placeholder="Search by product or customer..." value="<?php echo htmlspecialchars($search_term); ?>" class="flex-1 border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500 rounded-r-md">
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

                <!-- Orders List -->
                <div class="bg-white rounded-lg shadow-md overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Placed On</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Collection</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php
                            if (oci_fetch_all($stmt_orders, $orders, 0, -1, OCI_FETCHSTATEMENT_BY_ROW) > 0) {
                                foreach ($orders as $order) {
                            ?>
                                    <tr class="mobile-stack">
                                        <td class="px-4 py-4 whitespace-nowrap sm:table-cell block">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($order['ORDER_ID']); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap sm:table-cell block">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($order['FIRST_NAME'] . ' ' . $order['LAST_NAME']); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap sm:table-cell block">
                                            <div class="text-sm text-gray-900">$<?php echo number_format($order['TOTAL_AMOUNT'], 2); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap sm:table-cell block">
                                            <form method="POST" action="" class="inline">
                                                <input type="hidden" name="order_id" value="<?php echo $order['ORDER_ID']; ?>">
                                                <select name="new_status" onchange="this.form.submit()" class="border border-gray-300 rounded-md px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                                                    <option value="Unpaid" <?php echo $order['STATUS'] === 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                                                    <option value="Pending" <?php echo $order['STATUS'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="Delivered" <?php echo $order['STATUS'] === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                    <option value="Return" <?php echo $order['STATUS'] === 'Return' ? 'selected' : ''; ?>>Return</option>
                                                    <option value="Refund" <?php echo $order['STATUS'] === 'Refund' ? 'selected' : ''; ?>>Refund</option>
                                                </select>
                                                <input type="hidden" name="update_status" value="1">
                                            </form>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap sm:table-cell block">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($order['PLACED_ON']))); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap sm:table-cell block">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($order['SCHEDULED_DAY'] . ' ' . $order['SCHEDULED_TIME']); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap sm:table-cell block text-sm font-medium">
                                            <a href="order_details.php?id=<?php echo $order['ORDER_ID']; ?>" class="text-blue-600 hover:text-blue-900">View</a>
                                        </td>
                                    </tr>
                                <?php
                                }
                            } else {
                                ?>
                                <tr class="mobile-stack">
                                    <td colspan="7" class="px-4 py-4 text-center text-gray-500 block">
                                        No orders found.
                                    </td>
                                </tr>
                            <?php
                            }
                            oci_free_statement($stmt_orders);
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
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle for mobile
            const menuToggle = document.getElementById('menu-toggle');
            const sidebar = document.getElementById('sidebar');
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('hidden');
            });

            // Sidebar item toggle
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
    </script>
</body>

</html>

<?php
if (isset($conn)) oci_close($conn);
include_once 'includes/footer.php';
?>