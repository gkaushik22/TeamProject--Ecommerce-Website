<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Calculate total quantity in cart
$cart_item_count = 0;
if (isset($_SESSION["cart"]) && is_array($_SESSION["cart"])) {
    foreach ($_SESSION["cart"] as $item) {
        if (isset($item["quantity"])) {
            $cart_item_count += $item["quantity"];
        }
    }
}

// Calculate total items in wishlist
$wishlist_item_count = 0;
if (isset($_SESSION["wishlist"]) && is_array($_SESSION["wishlist"])) {
    $wishlist_item_count = count($_SESSION["wishlist"]);
}

// Check if user is logged in as a trader
$is_trader = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && isset($_SESSION["usertype"]) && $_SESSION["usertype"] === 'TRADER';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CleckBasket</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css"> <!-- Optional: for custom styles -->
</head>

<body class="bg-gray-100">
    <header class="bg-white shadow-md sticky top-0 z-50">
        <nav class="container mx-auto px-6 py-3 flex justify-between items-center">
            <a href="<?php echo $is_trader ? 'trader_profile.php' : 'index.php'; ?>" class="text-xl font-bold text-orange-500">CleckBasket</a>
            <div class="flex items-center space-x-4">
                <?php if ($is_trader): ?>
                    <!-- Trader-specific navigation -->
                    <a href="trader_profile.php" class="text-gray-700 hover:text-orange-500">Trader Dashboard</a>
                    <a href="logout.php" class="text-gray-700 hover:text-orange-500">Logout</a>
                <?php else: ?>
                    <!-- Regular user or non-logged-in user navigation -->
                    <a href="index.php" class="text-gray-700 hover:text-orange-500">Home</a>
                    <a href="shop.php" class="text-gray-700 hover:text-orange-500">Shop</a>
                    <a href="category.php" class="text-gray-700 hover:text-orange-500">Categories</a>
                    <form action="shop.php" method="GET" class="relative hidden md:block" id="searchForm">
                        <input type="text" name="search" id="searchInput" placeholder="Search products..." class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-300 focus:border-transparent w-64" autocomplete="off">
                        <button type="submit" class="absolute right-0 top-0 mt-2 mr-2">
                            <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div id="searchSuggestions" class="absolute w-full bg-white border border-gray-300 rounded-md shadow-lg mt-1 z-50 hidden max-h-96 overflow-y-auto"></div>
                    </form>
                    <a href="wishlist.php" class="relative text-gray-700 hover:text-orange-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        <?php if ($wishlist_item_count > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5"><?php echo $wishlist_item_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                        <a href="cart.php" class="relative text-gray-700 hover:text-orange-500">
                            <svg class="h-6 w-6" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor">
                                <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <?php if ($cart_item_count > 0): ?>
                                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5"><?php echo $cart_item_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <?php if (isset($_SESSION["first_name"])): ?>
                            <a href="profile.php" class="text-gray-700 hover:text-orange-500">Welcome, <?php echo htmlspecialchars($_SESSION["first_name"]); ?></a>
                        <?php else: ?>
                            <a href="profile.php" class="text-gray-700 hover:text-orange-500">Profile</a>
                        <?php endif; ?>
                        <a href="logout.php" class="text-gray-700 hover:text-orange-500">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="text-gray-700 hover:text-orange-500">Login</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </nav>
    </header>
    <main class="container mx-auto px-6 py-8">