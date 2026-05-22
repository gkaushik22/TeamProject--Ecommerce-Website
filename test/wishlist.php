<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'php_logic/connect.php'; // Connect to Oracle DB
include_once 'includes/header.php';

// Initialize session wishlist if not exists
if (!isset($_SESSION['wishlist_quantities'])) {
    $_SESSION['wishlist_quantities'] = [];
}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['login_error'] = "Please log in to use the wishlist feature.";
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    $_SESSION['login_error'] = "User session is invalid. Please log in again.";
    header("Location: login.php");
    exit;
}

// Initialize or get wishlist ID
$wishlist_id = null;
$query_get_wishlist = "SELECT wishlist_id FROM WISHLIST WHERE fk1_user_id = :user_id";
$stmt_get_wishlist = oci_parse($conn, $query_get_wishlist);
oci_bind_by_name($stmt_get_wishlist, ':user_id', $user_id);
oci_execute($stmt_get_wishlist);
$wishlist_row = oci_fetch_assoc($stmt_get_wishlist);
oci_free_statement($stmt_get_wishlist);

if ($wishlist_row) {
    $wishlist_id = $wishlist_row['WISHLIST_ID'];
} else {
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

if (!$wishlist_id) {
    $_SESSION['wishlist_error'] = "Unable to retrieve or create wishlist.";
    header("Location: index.php");
    exit;
}

// Handle removal from wishlist
if (isset($_POST['remove_from_wishlist']) && isset($_POST['product_id'])) {
    $remove_id = filter_var($_POST['product_id'], FILTER_SANITIZE_STRING);
    $query_remove = "DELETE FROM PRODUCT_WISHLIST WHERE wishlist_id = :wishlist_id AND product_id = :product_id";
    $stmt_remove = oci_parse($conn, $query_remove);
    oci_bind_by_name($stmt_remove, ':wishlist_id', $wishlist_id);
    oci_bind_by_name($stmt_remove, ':product_id', $remove_id);
    if (oci_execute($stmt_remove)) {
        $_SESSION['wishlist_success'] = "Product removed from wishlist.";
    } else {
        $_SESSION['wishlist_error'] = "Error removing product from wishlist.";
    }
    oci_free_statement($stmt_remove);
    header("Location: wishlist.php");
    exit;
}

// Handle add to cart from wishlist
if (isset($_POST['add_to_cart_from_wishlist']) && isset($_POST['product_id'])) {
    $cart_product_id = filter_var($_POST['product_id'], FILTER_SANITIZE_STRING);
    
    // Get quantity from POST or session storage
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    // If we have this product in session wishlist quantities, use that value
    if (isset($_SESSION['wishlist_quantities'][$cart_product_id])) {
        $quantity = (int)$_SESSION['wishlist_quantities'][$cart_product_id];
    }

    // Fetch product details, including status
    $query = "SELECT product_id, name AS product_name, price, stock, status
              FROM PRODUCT 
              WHERE product_id = :product_id";
    $stmt = oci_parse($conn, $query);
    oci_bind_by_name($stmt, ':product_id', $cart_product_id);
    oci_execute($stmt);
    $product = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    if ($product && isset($product['STATUS']) && $product['STATUS'] === 'Enable') {
        if ($quantity <= 0 || $quantity > $product['STOCK']) {
            $_SESSION['wishlist_error'] = "Invalid quantity or insufficient stock.";
            header("Location: wishlist.php");
            exit;
        }

        // Get or create cart
        $cart_id = null;
        $query_get_cart = "SELECT cart_id FROM CART WHERE fk1_user_id = :user_id";
        $stmt_get_cart = oci_parse($conn, $query_get_cart);
        oci_bind_by_name($stmt_get_cart, ':user_id', $user_id);
        oci_execute($stmt_get_cart);
        $cart_row = oci_fetch_assoc($stmt_get_cart);
        oci_free_statement($stmt_get_cart);

        if ($cart_row) {
            $cart_id = $cart_row['CART_ID'];
        } else {
            $query_create_cart = "INSERT INTO CART (fk1_user_id) VALUES (:user_id)";
            $stmt_create_cart = oci_parse($conn, $query_create_cart);
            oci_bind_by_name($stmt_create_cart, ':user_id', $user_id);
            if (oci_execute($stmt_create_cart)) {
                $query_get_new_cart = "SELECT cart_id FROM CART WHERE fk1_user_id = :user_id";
                $stmt_get_new_cart = oci_parse($conn, $query_get_new_cart);
                oci_bind_by_name($stmt_get_new_cart, ':user_id', $user_id);
                oci_execute($stmt_get_new_cart);
                $new_cart_row = oci_fetch_assoc($stmt_get_new_cart);
                $cart_id = $new_cart_row['CART_ID'];
                oci_free_statement($stmt_get_new_cart);
            }
            oci_free_statement($stmt_create_cart);
        }

        if ($cart_id) {
            // Check if product is already in cart
            $query_check_cart = "SELECT quantity FROM CART_PRODUCT WHERE cart_id = :cart_id AND product_id = :product_id";
            $stmt_check_cart = oci_parse($conn, $query_check_cart);
            oci_bind_by_name($stmt_check_cart, ':cart_id', $cart_id);
            oci_bind_by_name($stmt_check_cart, ':product_id', $cart_product_id);
            oci_execute($stmt_check_cart);
            $existing_cart_item = oci_fetch_assoc($stmt_check_cart);
            oci_free_statement($stmt_check_cart);

            try {
                if ($existing_cart_item) {
                    $new_quantity = $existing_cart_item['QUANTITY'] + $quantity;
                    if ($new_quantity > $product['STOCK']) {
                        $_SESSION['wishlist_error'] = "Total quantity exceeds available stock.";
                        header("Location: wishlist.php");
                        exit;
                    }
                    $query_update_cart = "UPDATE CART_PRODUCT SET quantity = :quantity 
                                         WHERE cart_id = :cart_id AND product_id = :product_id";
                    $stmt_update_cart = oci_parse($conn, $query_update_cart);
                    oci_bind_by_name($stmt_update_cart, ':quantity', $new_quantity);
                    oci_bind_by_name($stmt_update_cart, ':cart_id', $cart_id);
                    oci_bind_by_name($stmt_update_cart, ':product_id', $cart_product_id);
                    oci_execute($stmt_update_cart);
                    oci_free_statement($stmt_update_cart);
                } else {
                    $query_add_to_cart = "INSERT INTO CART_PRODUCT (cart_id, product_id, quantity) 
                                         VALUES (:cart_id, :product_id, :quantity)";
                    $stmt_add_to_cart = oci_parse($conn, $query_add_to_cart);
                    oci_bind_by_name($stmt_add_to_cart, ':cart_id', $cart_id);
                    oci_bind_by_name($stmt_add_to_cart, ':product_id', $cart_product_id);
                    oci_bind_by_name($stmt_add_to_cart, ':quantity', $quantity);
                    oci_execute($stmt_add_to_cart);
                    oci_free_statement($stmt_add_to_cart);
                }

                // Update session cart
                if (!isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }
                if (isset($_SESSION['cart'][$cart_product_id])) {
                    $_SESSION['cart'][$cart_product_id]['quantity'] += $quantity;
                } else {
                    $_SESSION['cart'][$cart_product_id] = [
                        'name' => $product['PRODUCT_NAME'],
                        'price' => $product['PRICE'],
                        'quantity' => $quantity
                    ];
                }

                $_SESSION['wishlist_success'] = "Product added to cart.";
                // Optional: Keep item in wishlist
                // $query_remove = "DELETE FROM PRODUCT_WISHLIST WHERE wishlist_id = :wishlist_id AND product_id = :product_id";
                // $stmt_remove = oci_parse($conn, $query_remove);
                // oci_bind_by_name($stmt_remove, ':wishlist_id', $wishlist_id);
                // oci_bind_by_name($stmt_remove, ':product_id', $cart_product_id);
                // oci_execute($stmt_remove);
                // oci_free_statement($stmt_remove);
            } catch (Exception $e) {
                $_SESSION['wishlist_error'] = "Error adding to cart: " . $e->getMessage();
            }
        } else {
            $_SESSION['wishlist_error'] = "Failed to create or retrieve cart.";
        }
    } else {
        $_SESSION['wishlist_error'] = "Product not found or not available.";
    }
    header("Location: wishlist.php");
    exit;
}

// Fetch wishlist products
$wishlist_products = [];
if ($wishlist_id) {
    $query = "SELECT p.product_id, p.name AS product_name, p.price, p.unit, p.stock, p.image, p.status,
                     pc.name AS category_name, s.name AS trader_name
              FROM PRODUCT p
              JOIN PRODUCT_WISHLIST pw ON p.product_id = pw.product_id
              JOIN PRODUCT_CATEGORY pc ON p.fk2_category_id = pc.category_id
              JOIN SHOP s ON p.fk1_shop_id = s.shop_id
              WHERE pw.wishlist_id = :wishlist_id";
    $stmt = oci_parse($conn, $query);
    oci_bind_by_name($stmt, ':wishlist_id', $wishlist_id);
    if (oci_execute($stmt)) {
        while ($row = oci_fetch_assoc($stmt)) {
            $wishlist_products[] = $row;
        }
    }
    oci_free_statement($stmt);
}
?>

<div class="container mx-auto px-6 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">My Wishlist</h1>

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

    <?php if (empty($wishlist_products)): ?>
        <p class="text-gray-600 text-center py-10">Your wishlist is empty. <a href="shop.php" class="text-orange-500 hover:text-orange-700 underline">Start shopping now!</a></p>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($wishlist_products as $product): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition duration-300 flex flex-col">
                    <a href="product_detail.php?id=<?php echo htmlspecialchars($product['PRODUCT_ID']); ?>" class="block">
                        <div class="h-48 bg-gray-200 flex items-center justify-center text-gray-500">
                            <?php if ($product['IMAGE']): ?>
                                <img src="get_product_image.php?id=<?php echo htmlspecialchars($product['PRODUCT_ID']); ?>" alt="<?php echo htmlspecialchars($product['PRODUCT_NAME']); ?>" class="h-full w-full object-cover">
                            <?php else: ?>
                                <img src="assets/images/placeholder.jpg" alt="No Image Available" class="h-full w-full object-cover">
                            <?php endif; ?>
                        </div>
                    </a>
                    <div class="p-6 flex flex-col flex-grow">
                        <h3 class="text-xl font-semibold text-gray-800 mb-2"><a href="product_detail.php?id=<?php echo htmlspecialchars($product['PRODUCT_ID']); ?>" class="hover:text-orange-600"><?php echo htmlspecialchars($product['PRODUCT_NAME']); ?></a></h3>
                        <p class="text-sm text-gray-500 mb-1">Category: <?php echo htmlspecialchars($product['CATEGORY_NAME']); ?></p>
                        <p class="text-sm text-gray-500 mb-1">Sold by: <?php echo htmlspecialchars($product['TRADER_NAME']); ?></p>
                        <p class="text-orange-500 font-bold text-lg mb-1">$<?php echo htmlspecialchars(number_format($product['PRICE'], 2)); ?> <?php if ($product['UNIT']) echo "/ " . htmlspecialchars($product['UNIT']); ?></p>
                        <p class="text-sm text-gray-600 mb-3">Status: <?php echo htmlspecialchars($product['STOCK'] > 0 && $product['STATUS'] === 'Enable' ? 'In Stock (' . $product['STOCK'] . ')' : 'Out of Stock or Unavailable'); ?></p>

                        <div class="mt-auto flex space-x-2">
                            <!-- Add to Cart Form -->
                            <form action="" method="POST" class="flex-1">
                                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['PRODUCT_ID']); ?>">
                                <input type="hidden" name="add_to_cart_from_wishlist" value="1">
                                <?php if ($product['STOCK'] > 0 && $product['STATUS'] === 'Enable'): ?>
                                    <div class="flex items-center mb-2">
                                        <label for="quantity_wishlist_<?php echo htmlspecialchars($product['PRODUCT_ID']); ?>" class="sr-only">Quantity</label>
                                        <div class="flex items-center border border-gray-300 rounded-md">
                                            <button type="button" onclick="updateWishlistQuantity('<?php echo htmlspecialchars($product['PRODUCT_ID']); ?>', -1)" class="px-2 py-1 text-gray-600 hover:bg-gray-100">-</button>
                                            <input type="number" id="quantity_wishlist_<?php echo htmlspecialchars($product['PRODUCT_ID']); ?>" name="quantity" value="1" min="1" max="<?php echo htmlspecialchars($product['STOCK']); ?>" class="w-16 text-center border-none focus:ring-0 py-1 px-2" readonly>
                                            <button type="button" onclick="updateWishlistQuantity('<?php echo htmlspecialchars($product['PRODUCT_ID']); ?>', 1)" class="px-2 py-1 text-gray-600 hover:bg-gray-100">+</button>
                                        </div>
                                    </div>
                                    <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2 px-4 rounded-md transition duration-300">Add to Cart</button>
                                <?php else: ?>
                                    <p class="text-red-500 font-semibold">Out of Stock or Unavailable</p>
                                <?php endif; ?>
                            </form>
                            <!-- Remove from Wishlist -->
                            <form action="" method="POST">
                                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['PRODUCT_ID']); ?>">
                                <input type="hidden" name="remove_from_wishlist" value="1">
                                <button type="submit" class="p-2 rounded-md hover:bg-gray-100 transition duration-300" title="Remove from Wishlist">
                                    <svg class="h-6 w-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    function updateWishlistQuantity(productId, change) {
        const quantityInput = document.getElementById('quantity_wishlist_' + productId);
        let quantity = parseInt(quantityInput.value);
        const max = parseInt(quantityInput.max);
        quantity += change;
        if (quantity < 1) quantity = 1;
        if (quantity > max) quantity = max;
        quantityInput.value = quantity;
    }
</script</div>

<!-- Include wishlist JavaScript -->
<script src="js/wishlist_cart.js"></script>

<?php
if (isset($conn)) oci_close($conn);
include_once 'includes/footer.php';
?>