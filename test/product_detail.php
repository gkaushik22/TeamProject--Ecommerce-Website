<?php
// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'php_logic/connect.php'; // Connect to Oracle DB
include_once 'includes/header.php';

// Validate and sanitize product ID
$product_id = isset($_GET['id']) ? filter_var(trim($_GET['id']), FILTER_SANITIZE_STRING) : null;
if (!preg_match('/^\d+$/', $product_id)) {
    $product_id = null;
    $error_message = "Invalid product ID.";
}

// Initialize variables
$product = null;
$wishlist_id = null;

// Fetch or create wishlist ID for the logged-in customer
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $user_id = $_SESSION['user_id']; // Assume user_id is stored in session
    $query_wishlist = "SELECT wishlist_id FROM WISHLIST WHERE fk1_user_id = :user_id";
    $stmt_wishlist = oci_parse($conn, $query_wishlist);
    oci_bind_by_name($stmt_wishlist, ':user_id', $user_id);
    if (oci_execute($stmt_wishlist)) {
        $row = oci_fetch_assoc($stmt_wishlist);
        $wishlist_id = $row ? $row['WISHLIST_ID'] : null;
    }
    oci_free_statement($stmt_wishlist);

    // Create wishlist if none exists
    if (!$wishlist_id) {
        $query_create_wishlist = "INSERT INTO WISHLIST (fk1_user_id) VALUES (:user_id)";
        $stmt_create_wishlist = oci_parse($conn, $query_create_wishlist);
        oci_bind_by_name($stmt_create_wishlist, ':user_id', $user_id);
        if (oci_execute($stmt_create_wishlist)) {
            $query_get_new_wishlist = "SELECT wishlist_id FROM WISHLIST WHERE fk1_user_id = :user_id";
            $stmt_get_new_wishlist = oci_parse($conn, $query_get_new_wishlist);
            oci_bind_by_name($stmt_get_new_wishlist, ':user_id', $user_id);
            oci_execute($stmt_get_new_wishlist);
            $new_wishlist_row = oci_fetch_assoc($stmt_get_new_wishlist);
            $wishlist_id = $new_wishlist_row['WISHLIST_ID'];
            oci_free_statement($stmt_get_new_wishlist);
        }
        oci_free_statement($stmt_create_wishlist);
    }
}

// Fetch product details
if ($product_id !== null && !isset($error_message)) {
    $query = "SELECT p.product_id, p.name AS product_name, p.price, p.unit, p.stock, p.description,
                     pc.name AS category_name, s.name AS shop_name, p.image
              FROM PRODUCT p
              JOIN PRODUCT_CATEGORY pc ON p.fk2_category_id = pc.category_id
              JOIN SHOP s ON p.fk1_shop_id = s.shop_id
              WHERE p.product_id = :product_id
              AND p.status = 'Enable'";

    $stmt = oci_parse($conn, $query);
    if (!$stmt) {
        $e = oci_error($conn);
        error_log("OCI Parse Error in product_detail.php: " . $e['message']);
        $error_message = "An error occurred while fetching product details.";
    } else {
        oci_bind_by_name($stmt, ':product_id', $product_id);
        if (oci_execute($stmt)) {
            $product = oci_fetch_assoc($stmt);
            if (!$product) {
                $error_message = "Product not found or is not active.";
            }
        } else {
            $e = oci_error($stmt);
            error_log("OCI Execute Error in product_detail.php: " . $e['message']);
            $error_message = "An error occurred while fetching product details.";
        }
        oci_free_statement($stmt);
    }
}

// Check if product is in wishlist (database-driven)
$is_in_wishlist = false;
if ($wishlist_id && $product_id) {
    $query_check_wishlist = "SELECT COUNT(*) AS count FROM PRODUCT_WISHLIST WHERE wishlist_id = :wishlist_id AND product_id = :product_id";
    $stmt_check_wishlist = oci_parse($conn, $query_check_wishlist);
    oci_bind_by_name($stmt_check_wishlist, ':wishlist_id', $wishlist_id);
    oci_bind_by_name($stmt_check_wishlist, ':product_id', $product_id);
    if (oci_execute($stmt_check_wishlist)) {
        $row = oci_fetch_assoc($stmt_check_wishlist);
        $is_in_wishlist = $row['COUNT'] > 0;
    }
    oci_free_statement($stmt_check_wishlist);
}

// Handle wishlist toggle
if (isset($_POST['toggle_wishlist']) && $product_id !== null && $wishlist_id) {
    try {
        if ($is_in_wishlist) {
            // Remove from wishlist
            $query_remove = "DELETE FROM PRODUCT_WISHLIST WHERE wishlist_id = :wishlist_id AND product_id = :product_id";
            $stmt_remove = oci_parse($conn, $query_remove);
            oci_bind_by_name($stmt_remove, ':wishlist_id', $wishlist_id);
            oci_bind_by_name($stmt_remove, ':product_id', $product_id);
            oci_execute($stmt_remove);
            oci_free_statement($stmt_remove);
            $_SESSION['wishlist_success'] = "Product removed from wishlist.";
        } else {
            // Add to wishlist
            $query_add = "INSERT INTO PRODUCT_WISHLIST (wishlist_id, product_id) VALUES (:wishlist_id, :product_id)";
            $stmt_add = oci_parse($conn, $query_add);
            oci_bind_by_name($stmt_add, ':wishlist_id', $wishlist_id);
            oci_bind_by_name($stmt_add, ':product_id', $product_id);
            oci_execute($stmt_add);
            oci_free_statement($stmt_add);
            $_SESSION['wishlist_success'] = "Product added to wishlist.";
        }
        // Redirect to avoid form resubmission
        header("Location: product_detail.php?id=$product_id");
        exit;
    } catch (Exception $e) {
        $_SESSION['wishlist_error'] = "Error updating wishlist: " . $e->getMessage();
        header("Location: product_detail.php?id=$product_id");
        exit;
    }
}

// Handle Buy Now
if (isset($_POST['buy_now']) && $product_id !== null) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        $_SESSION['login_error'] = "Please log in to proceed with your purchase.";
        header("Location: login.php");
        exit;
    }
    // Validate quantity
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    if ($quantity < 1 || $quantity > $product['STOCK']) {
        $error_message = "Invalid quantity selected.";
    } else {
        $_SESSION['buy_now'] = [
            'product_id' => $product_id,
            'quantity' => $quantity,
            'price' => $product['PRICE']
        ];
        header("Location: checkout.php");
        exit;
    }
}
?>

<div class="container mx-auto px-6 py-8">
    <!-- Breadcrumbs -->
    <nav class="mb-6 text-sm text-gray-600">
        <a href="index.php" class="hover:text-orange-500">Home</a> >
        <a href="shop.php" class="hover:text-orange-500">Shop</a> >
        <span class="text-gray-800"><?php echo $product ? htmlspecialchars($product['PRODUCT_NAME']) : 'Product'; ?></span>
    </nav>

    <!-- Display success/error messages -->
    <?php if (isset($_SESSION['wishlist_success'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-6">
            <p><?php echo htmlspecialchars($_SESSION['wishlist_success']); ?></p>
        </div>
        <?php unset($_SESSION['wishlist_success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['wishlist_error'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6">
            <p><?php echo htmlspecialchars($_SESSION['wishlist_error']); ?></p>
        </div>
        <?php unset($_SESSION['wishlist_error']); ?>
    <?php endif; ?>
    <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6">
            <p><?php echo htmlspecialchars($error_message); ?></p>
            <p><a href="shop.php" class="text-orange-500 hover:text-orange-700 underline">Return to Shop</a></p>
        </div>
    <?php elseif ($product): ?>
        <!-- Product Detail Section -->
        <div class="bg-white rounded-lg shadow-md p-8 flex flex-col md:flex-row gap-8">
            <!-- Product Image Section -->
            <div class="md:w-1/2">
                <div class="relative h-96 bg-gray-200 flex items-center justify-center text-gray-500 rounded-md overflow-hidden mb-4">
                    <?php if ($product['IMAGE']): ?>
                        <img src="get_product_image.php?id=<?php echo htmlspecialchars($product['PRODUCT_ID']); ?>" alt="<?php echo htmlspecialchars($product['PRODUCT_NAME']); ?>" class="h-full w-full object-cover">
                    <?php else: ?>
                        <img src="assets/images/placeholder.jpg" alt="No Image Available" class="h-full w-full object-cover">
                    <?php endif; ?>
                </div>
                <!-- Thumbnail gallery -->
                <div class="flex space-x-2">
                    <div class="w-16 h-16 bg-gray-200 rounded-md flex items-center justify-center text-gray-500">
                        <?php if ($product['IMAGE']): ?>
                            <img src="get_product_image.php?id=<?php echo htmlspecialchars($product['PRODUCT_ID']); ?>" alt="Thumbnail" class="h-full w-full object-cover rounded-md">
                        <?php else: ?>
                            <img src="assets/images/placeholder.jpg" alt="No Image Available" class="h-full w-full object-cover rounded-md">
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Product Details Section -->
            <div class="md:w-1/2 flex flex-col">
                <h1 class="text-3xl font-bold text-gray-800 mb-3"><?php echo htmlspecialchars($product['PRODUCT_NAME']); ?></h1>
                <div class="flex items-center mb-3">
                    <span class="text-orange-500 font-bold text-2xl mr-2">$<?php echo htmlspecialchars(number_format($product['PRICE'], 2)); ?></span>
                    <?php if ($product['UNIT']): ?>
                        <span class="text-gray-500 text-sm">/ <?php echo htmlspecialchars($product['UNIT']); ?></span>
                    <?php endif; ?>
                </div>
                <p class="text-sm text-gray-500 mb-2">Category: <span class="text-gray-700"><?php echo htmlspecialchars($product['CATEGORY_NAME']); ?></span></p>
                <p class="text-sm text-gray-500 mb-2">Sold by: <span class="text-gray-700"><?php echo htmlspecialchars($product['SHOP_NAME']); ?></span></p>
                <p class="text-sm mb-4">
                    <span class="font-semibold text-gray-700">Availability: </span>
                    <span class="<?php echo $product['STOCK'] > 0 ? 'text-green-500' : 'text-red-500'; ?>">
                        <?php echo $product['STOCK'] > 0 ? 'In Stock (' . htmlspecialchars($product['STOCK']) . ' available)' : 'Out of Stock'; ?>
                    </span>
                </p>
                <div class="text-gray-700 mb-6 leading-relaxed">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Description</h3>
                    <p><?php echo nl2br(htmlspecialchars($product['DESCRIPTION'] ?? 'No description available.')); ?></p>
                </div>

                <!-- Add to Cart, Buy Now, and Wishlist Forms -->
                <div class="mt-auto flex items-center space-x-3">
                    <!-- Quantity and Add to Cart Form -->
                    <?php if ($product['STOCK'] > 0): ?>
                        <form id="cart-form" action="add_to_cart.php" method="POST" class="flex items-center space-x-3">
                            <div class="flex items-center">
                                <label for="quantity_detail" class="text-gray-700 mr-2 font-semibold text-sm">Quantity:</label>
                                <div class="flex items-center border border-gray-300 rounded-md">
                                    <button type="button" onclick="updateQuantity(-1)" class="px-2 py-1 text-gray-600 hover:bg-gray-100">-</button>
                                    <input type="number" id="quantity_detail" name="quantity" value="1" min="1" max="<?php echo htmlspecialchars($product['STOCK']); ?>" class="w-12 text-center border-none focus:ring-0 text-sm" readonly>
                                    <button type="button" onclick="updateQuantity(1)" class="px-2 py-1 text-gray-600 hover:bg-gray-100">+</button>
                                </div>
                            </div>
                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['PRODUCT_ID']); ?>">
                            <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($product['PRODUCT_NAME']); ?>">
                            <input type="hidden" name="price" value="<?php echo htmlspecialchars($product['PRICE']); ?>">
                            <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2 px-4 rounded-md transition duration-300 text-sm">Add to Cart</button>
                        </form>
                    <?php else: ?>
                        <p class="text-red-500 font-semibold">Out of Stock</p>
                    <?php endif; ?>

                    <!-- Buy Now Form -->
                    <?php if ($product['STOCK'] > 0): ?>
                        <form id="buy-now-form" action="" method="POST" class="inline">
                            <input type="hidden" name="buy_now" value="1">
                            <input type="hidden" name="quantity" id="buy_now_quantity" value="1">
                            <button type="submit" class="bg-yellow-400 hover:bg-yellow-500 text-black font-semibold py-2 px-4 rounded-md transition duration-300 text-sm flex items-center">
                                <img src="https://www.paypalobjects.com/webstatic/mktg/logo/AM_SbyPP_mc_vs_dc_ae.jpg" alt="PayPal" class="h-5 mr-1">
                                Buy Now
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Wishlist Button -->
                    <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                        <form action="" method="POST" class="inline">
                            <input type="hidden" name="toggle_wishlist" value="1">
                            <button type="submit" class="p-2 rounded-md hover:bg-gray-100 transition duration-300" title="<?php echo $is_in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                <svg class="h-5 w-5 <?php echo $is_in_wishlist ? 'text-orange-500' : 'text-gray-500'; ?>" fill="<?php echo $is_in_wishlist ? 'currentColor' : 'none'; ?>" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Product Description and Q&A Tabs Section -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <!-- Tabs -->
            <div class="flex border-b border-gray-200">
                <button id="description-tab" class="tab-button px-4 py-2 text-gray-700 font-semibold border-b-2 border-transparent hover:border-orange-500 transition duration-300 active-tab">Product Description</button>
                <button id="qa-tab" class="tab-button px-4 py-2 text-gray-700 font-semibold border-b-2 border-transparent hover:border-orange-500 transition duration-300">Q&A</button>
            </div>

            <!-- Tab Content -->
            <div id="description-content" class="tab-content mt-4 text-gray-700 leading-relaxed space-y-4">
                <p><?php echo nl2br(htmlspecialchars($product['DESCRIPTION'] ?? 'No description available.')); ?></p>
            </div>
            <div id="qa-content" class="tab-content mt-4 text-gray-700 leading-relaxed hidden">
                <p class="italic">Q&A section is coming soon. Check back later for customer questions and answers!</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    function updateQuantity(change) {
        const quantityInput = document.getElementById('quantity_detail');
        const buyNowQuantity = document.getElementById('buy_now_quantity');
        let quantity = parseInt(quantityInput.value);
        const max = parseInt(quantityInput.max);
        quantity += change;
        if (quantity < 1) quantity = 1;
        if (quantity > max) quantity = max;
        quantityInput.value = quantity;
        buyNowQuantity.value = quantity;
    }

    // Tab Switching Logic
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('.tab-button');
        const contents = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                tabs.forEach(t => {
                    t.classList.remove('active-tab');
                    t.classList.add('border-transparent');
                });
                contents.forEach(content => content.classList.add('hidden'));

                this.classList.add('active-tab');
                this.classList.remove('border-transparent');
                const targetContent = document.getElementById(this.id.replace('-tab', '-content'));
                targetContent.classList.remove('hidden');
            });
        });
    });
</script>

<style>
    .active-tab {
        border-bottom: 2px solid #f97316 !important;
        background-color: #fff7ed;
        transition: background-color 0.3s ease;
    }

    .tab-content {
        transition: opacity 0.3s ease;
    }

    .tab-content.hidden {
        opacity: 0;
    }

    .tab-content:not(.hidden) {
        opacity: 1;
    }
</style>

<?php
if (isset($conn) && $conn) {
    oci_close($conn);
}
include_once 'includes/footer.php';
?>