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

// Get trader status
$trader_status = 'Pending';
$query_trader = "SELECT status FROM TRADER WHERE user_id = :user_id";
$stmt_trader = oci_parse($conn, $query_trader);
oci_bind_by_name($stmt_trader, ':user_id', $user_id);
oci_execute($stmt_trader);
$trader_row = oci_fetch_assoc($stmt_trader);
if ($trader_row) {
    $trader_status = $trader_row['STATUS'];
}
oci_free_statement($stmt_trader);

// Get shop information for this trader
$shop_id = null;
$shop_name = "";
$shop_action = null;
$query_shop = "SELECT shop_id, name, ACTION FROM SHOP WHERE fk1_user_id = :user_id";
$stmt_shop = oci_parse($conn, $query_shop);
oci_bind_by_name($stmt_shop, ':user_id', $user_id);
oci_execute($stmt_shop);
$shop_row = oci_fetch_assoc($stmt_shop);
oci_free_statement($stmt_shop);

if ($shop_row) {
    $shop_id = $shop_row['SHOP_ID'];
    $shop_name = $shop_row['NAME'];
    $shop_action = $shop_row['ACTION'];
} else {
    header("Location: create_shop.php");
    exit;
}

// Get order statistics
$unpaid_count = 0;
$pending_count = 0;
$to_review_count = 0;

$query_unpaid = "SELECT COUNT(*) as count FROM ORDERR o
                 JOIN ORDER_PRODUCT op ON o.order_id = op.order_id
                 JOIN PRODUCT p ON op.product_id = p.product_id
                 WHERE p.fk1_shop_id = :shop_id AND o.status = 'Unpaid' AND p.status = 'Enable'";
$stmt_unpaid = oci_parse($conn, $query_unpaid);
oci_bind_by_name($stmt_unpaid, ':shop_id', $shop_id);
oci_execute($stmt_unpaid);
$row_unpaid = oci_fetch_assoc($stmt_unpaid);
if ($row_unpaid) {
    $unpaid_count = $row_unpaid['COUNT'];
}
oci_free_statement($stmt_unpaid);

$query_pending = "SELECT COUNT(*) as count FROM ORDERR o
                  JOIN ORDER_PRODUCT op ON o.order_id = op.order_id
                  JOIN PRODUCT p ON op.product_id = p.product_id
                  WHERE p.fk1_shop_id = :shop_id AND o.status = 'Pending' AND p.status = 'Enable'";
$stmt_pending = oci_parse($conn, $query_pending);
oci_bind_by_name($stmt_pending, ':shop_id', $shop_id);
oci_execute($stmt_pending);
$row_pending = oci_fetch_assoc($stmt_pending);
if ($row_pending) {
    $pending_count = $row_pending['COUNT'];
}
oci_free_statement($stmt_pending);

$query_review = "SELECT COUNT(*) as count FROM ORDERR o
                 JOIN ORDER_PRODUCT op ON o.order_id = op.order_id
                 JOIN PRODUCT p ON op.product_id = p.product_id
                 WHERE p.fk1_shop_id = :shop_id AND o.status = 'Delivered' AND p.status = 'Enable'";
$stmt_review = oci_parse($conn, $query_review);
oci_bind_by_name($stmt_review, ':shop_id', $shop_id);
oci_execute($stmt_review);
$row_review = oci_fetch_assoc($stmt_review);
if ($row_review) {
    $to_review_count = $row_review['COUNT'];
}
oci_free_statement($stmt_review);

include_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CleckBasket Trader Center - Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-50">
    <div class="flex flex-col md:flex-row min-h-screen">
        <!-- Sidebar -->
        <aside id="sidebar" class="w-full md:w-64 bg-white shadow-md md:min-h-screen hidden md:block">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center mr-3">
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
                        <svg class="w-5 h-5 transition-transform rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="ml-9 mt-2 sidebar-content">
                        <a href="trader_profile.php" class="block py-2 text-orange-500 font-semibold">Settings</a>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1">
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

            <div class="container mx-auto px-6 py-8">
                <!-- Banner -->
                <div class="bg-white rounded-lg shadow-md p-8 mb-8 text-center">
                    <h2 class="text-3xl font-bold text-gray-800">Welcome to Your Trader Dashboard</h2>
                    <p class="text-gray-600 mt-2">Manage your shop, products, and orders with ease.</p>
                </div>

                <!-- Trader Info and Notifications -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <!-- Trader Info -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center mr-4">
                                <img src="assets/images/CLeckBasketLogo.jpg" alt="CleckBasket Logo" class="w-full h-full object-contain rounded-lg">
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($shop_name); ?></h3>
                                <p class="text-sm text-gray-600">Trader Status: <?php echo htmlspecialchars($trader_status); ?></p>
                                <p class="text-sm text-gray-600">Last Shop Action: <?php echo htmlspecialchars($shop_action ?? 'None'); ?></p>
                            </div>
                        </div>
                    </div>
                    <!-- Notifications -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Important Notifications</h3>
                        <div class="flex items-start mb-4">
                            <svg class="w-5 h-5 text-gray-600 mr-2 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke-width="2" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01" />
                            </svg>
                            <div>
                                <p class="text-sm text-gray-600">Reminder: Stock up packaging materials for 9.9 campaign by 12th March</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <a href="#" class="text-orange-500 hover:text-orange-600 text-sm font-medium">View all</a>
                        </div>
                    </div>
                </div>

                <!-- Order Statistics -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <h3 class="text-lg font-bold text-gray-800 mb-6">My Orders</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div class="text-center hover:shadow-lg transition-shadow p-4 rounded-lg">
                            <h2 class="text-3xl font-bold text-orange-500"><?php echo $unpaid_count; ?></h2>
                            <p class="text-gray-600 mt-2">Unpaid Orders</p>
                        </div>
                        <div class="text-center hover:shadow-lg transition-shadow p-4 rounded-lg">
                            <h2 class="text-3xl font-bold text-orange-500"><?php echo $pending_count; ?></h2>
                            <p class="text-gray-600 mt-2">Pending Orders</p>
                        </div>
                        <div class="text-center hover:shadow-lg transition-shadow p-4 rounded-lg">
                            <h2 class="text-3xl font-bold text-orange-500"><?php echo $to_review_count; ?></h2>
                            <p class="text-gray-600 mt-2">To Be Reviewed</p>
                        </div>
                    </div>
                </div>

                <!-- Account Health -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Account Health</h3>
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <h4 class="text-md font-bold text-gray-800 mb-2">Non-Compliance Points (NCP)</h4>
                        <div class="flex items-baseline mb-2">
                            <span class="text-3xl font-bold text-gray-800 mr-2">0</span>
                            <span class="text-gray-600">Need To Improve</span>
                        </div>
                        <p class="text-sm text-gray-600">Status reflects a combination of Non-Compliance Points (NCP) and store operational metric performance.</p>
                    </div>
                </div>

                <!-- Campaign Events -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 mb-6">Campaign Events</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-xl transition-shadow">
                                <div class="text-center mb-4">
                                    <h4 class="text-xl font-bold text-gray-800"><?php echo sprintf("%02d:%02d:%02d", rand(0, 9), rand(0, 23), rand(0, 59)); ?></h4>
                                    <p class="text-xs text-gray-600">DAYS:HOURS:MINS</p>
                                </div>
                                <div class="bg-gray-200 h-32 mb-4 rounded-lg flex items-center justify-center">
                                    <svg class="w-12 h-12 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <p class="text-sm text-gray-600 mb-4">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
                                <button class="w-full bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2 rounded-md transition duration-300">Submit Deal</button>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
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