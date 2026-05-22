<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    $_SESSION['login_error'] = "Please log in to view your cart.";
    header("Location: login.php");
    exit;
}

require_once 'php_logic/connect.php'; // Connect to Oracle DB
include_once 'includes/header.php';

$user_id = $_SESSION['user_id'];

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

if (!$cart_id) {
    $_SESSION['cart_error'] = "Unable to retrieve or create cart.";
    header("Location: index.php");
    exit;
}

// Handle cart updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_item']) && isset($_POST['product_id_to_remove'])) {
        $product_id_to_remove = filter_var($_POST['product_id_to_remove'], FILTER_SANITIZE_STRING);
        $query_remove = "DELETE FROM CART_PRODUCT WHERE cart_id = :cart_id AND product_id = :product_id";
        $stmt_remove = oci_parse($conn, $query_remove);
        oci_bind_by_name($stmt_remove, ':cart_id', $cart_id);
        oci_bind_by_name($stmt_remove, ':product_id', $product_id_to_remove);
        if (oci_execute($stmt_remove)) {
            unset($_SESSION['cart'][$product_id_to_remove]); // Update session
            $_SESSION['cart_success'] = "Item removed from cart.";
        } else {
            $_SESSION['cart_error'] = "Error removing item from cart.";
        }
        oci_free_statement($stmt_remove);
        header('Location: cart.php');
        exit;
    }

    if (isset($_POST['update_quantity']) && isset($_POST['product_id']) && isset($_POST['quantity'])) {
        $product_id = filter_var($_POST['product_id'], FILTER_SANITIZE_STRING);
        $quantity = (int)$_POST['quantity'];

        // Validate stock
        $query_stock = "SELECT stock FROM PRODUCT WHERE product_id = :product_id AND status = 'Enable'";
        $stmt_stock = oci_parse($conn, $query_stock);
        oci_bind_by_name($stmt_stock, ':product_id', $product_id);
        oci_execute($stmt_stock);
        $stock_row = oci_fetch_assoc($stmt_stock);
        oci_free_statement($stmt_stock);

        if ($stock_row && $quantity <= $stock_row['STOCK']) {
            if ($quantity > 0) {
                $query_update = "UPDATE CART_PRODUCT SET quantity = :quantity WHERE cart_id = :cart_id AND product_id = :product_id";
                $stmt_update = oci_parse($conn, $query_update);
                oci_bind_by_name($stmt_update, ':quantity', $quantity);
                oci_bind_by_name($stmt_update, ':cart_id', $cart_id);
                oci_bind_by_name($stmt_update, ':product_id', $product_id);
                if (oci_execute($stmt_update)) {
                    $_SESSION['cart'][$product_id]['quantity'] = $quantity; // Update session
                    $_SESSION['cart_success'] = "Quantity updated successfully.";
                } else {
                    $_SESSION['cart_error'] = "Error updating quantity.";
                }
                oci_free_statement($stmt_update);
            } else {
                $query_remove = "DELETE FROM CART_PRODUCT WHERE cart_id = :cart_id AND product_id = :product_id";
                $stmt_remove = oci_parse($conn, $query_remove);
                oci_bind_by_name($stmt_remove, ':cart_id', $cart_id);
                oci_bind_by_name($stmt_remove, ':product_id', $product_id);
                if (oci_execute($stmt_remove)) {
                    unset($_SESSION['cart'][$product_id]); // Update session
                    $_SESSION['cart_success'] = "Item removed from cart.";
                } else {
                    $_SESSION['cart_error'] = "Error removing item.";
                }
                oci_free_statement($stmt_remove);
            }
        } else {
            $_SESSION['cart_error'] = "Requested quantity exceeds available stock.";
        }
        header('Location: cart.php');
        exit;
    }
}

// Fetch cart items
$cart_items = [];
$cart_total = 0;

if ($cart_id) {
    $query_cart_items = "SELECT cp.product_id, cp.quantity, p.name, p.price, p.stock
                         FROM CART_PRODUCT cp
                         JOIN PRODUCT p ON cp.product_id = p.product_id
                         WHERE cp.cart_id = :cart_id AND p.status = 'Enable'";
    $stmt_cart_items = oci_parse($conn, $query_cart_items);
    oci_bind_by_name($stmt_cart_items, ':cart_id', $cart_id);
    if (oci_execute($stmt_cart_items)) {
        while ($row = oci_fetch_assoc($stmt_cart_items)) {
            $cart_items[$row['PRODUCT_ID']] = [
                'product_id' => $row['PRODUCT_ID'],
                'name' => $row['NAME'],
                'price' => $row['PRICE'],
                'quantity' => $row['QUANTITY'],
                'stock' => $row['STOCK']
            ];
        }
    }
    oci_free_statement($stmt_cart_items);
}

?>

<div class="container mx-auto px-6 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-8 text-center">Your Shopping Cart</h1>

    <!-- Display success/error messages -->
    <?php if (isset($_SESSION['cart_success'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-6">
            <p><?php echo htmlspecialchars($_SESSION['cart_success']); ?></p>
        </div>
        <?php unset($_SESSION['cart_success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['cart_error'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6">
            <p><?php echo htmlspecialchars($_SESSION['cart_error']); ?></p>
        </div>
        <?php unset($_SESSION['cart_error']); ?>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <p class="text-xl text-gray-600 mb-4">Your cart is currently empty.</p>
            <a href="shop.php" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-3 px-6 rounded-lg text-lg transition duration-300">Continue Shopping</a>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-lg shadow-md overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($cart_items as $product_id => $item): ?>
                        <?php
                        $item_total = $item['price'] * $item['quantity'];
                        $cart_total += $item_total;
                        ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['name']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">$<?php echo number_format($item['price'], 2); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <form method="POST" action="cart.php" class="flex items-center">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product_id); ?>">
                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock']; ?>" class="w-16 text-center border border-gray-300 rounded-md py-1 px-2 mr-2">
                                    <button type="submit" name="update_quantity" class="text-blue-600 hover:text-blue-900">Update</button>
                                </form>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">$<?php echo number_format($item_total, 2); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <form method="POST" action="cart.php" class="inline">
                                    <input type="hidden" name="product_id_to_remove" value="<?php echo htmlspecialchars($product_id); ?>">
                                    <button type="submit" name="remove_item" class="text-red-600 hover:text-red-900">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-8 flex flex-col md:flex-row justify-between items-start">
            <div class="w-full md:w-auto mb-4 md:mb-0">
                <a href="shop.php" class="text-orange-500 hover:text-orange-700 font-medium">← Continue Shopping</a>
            </div>
            <div class="w-full md:w-1/3 bg-gray-50 p-6 rounded-lg shadow-sm">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">Cart Summary</h2>
                <div class="flex justify-between mb-2">
                    <span class="text-gray-600">Subtotal</span>
                    <span class="text-gray-800 font-medium">$<?php echo number_format($cart_total, 2); ?></span>
                </div>
                <div class="flex justify-between mb-2">
                    <span class="text-gray-600">Shipping</span>
                    <span class="text-gray-800 font-medium">$0.00</span>
                </div>
                <hr class="my-2">
                <div class="flex justify-between font-bold text-lg mb-4">
                    <span class="text-gray-800">Total</span>
                    <span class="text-gray-800">$<?php echo number_format($cart_total, 2); ?></span>
                </div>

                <!-- PayPal Checkout Button -->
                <?php if ($cart_total > 0): ?>
                    <form action="checkout.php" method="POST">
                        <input type="hidden" name="cart_id" value="<?php echo htmlspecialchars($cart_id); ?>">
                        <button type="submit" class="w-full bg-yellow-400 hover:bg-yellow-500 text-black font-bold py-3 px-4 rounded-lg shadow-md flex items-center justify-center">
                            <img src="https://www.paypalobjects.com/webstatic/mktg/logo/AM_SbyPP_mc_vs_dc_ae.jpg" alt="PayPal" class="h-6 mr-2">
                            Proceed to Checkout
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
if (isset($conn) && $conn) {
    oci_close($conn);
}
include_once 'includes/footer.php';
?>