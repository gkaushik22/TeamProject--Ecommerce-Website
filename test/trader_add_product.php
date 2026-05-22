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
$query_shop = "SELECT shop_id, name FROM SHOP WHERE fk1_user_id = :user_id";
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

// Get all categories for dropdown
$categories = [];
$query_categories = "SELECT category_id, name FROM PRODUCT_CATEGORY";
$stmt_categories = oci_parse($conn, $query_categories);
oci_execute($stmt_categories);
while ($row = oci_fetch_assoc($stmt_categories)) {
    $categories[] = $row;
}
oci_free_statement($stmt_categories);

// Get all non-expired discounts for dropdown
$discounts = [];
$query_discounts = "SELECT discount_id, percent, valid_upto FROM DISCOUNT WHERE valid_upto > SYSDATE ORDER BY percent";
$stmt_discounts = oci_parse($conn, $query_discounts);
oci_execute($stmt_discounts);
while ($row = oci_fetch_assoc($stmt_discounts)) {
    $discounts[] = $row;
}
oci_free_statement($stmt_discounts);

// Handle form submission for adding a product
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $product_name = trim($_POST['product_name'] ?? '');
    $category_id = $_POST['category'] ?? '';
    $discount_id = $_POST['discount'] ?? ''; // New discount field
    $price = $_POST['price'] ?? '';
    $stock = $_POST['stock'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $status = isset($_POST['active']) ? 'Enable' : 'Disable';
    $action = 'Add';

    // Validate inputs
    if (empty($product_name) || empty($category_id) || empty($price) || empty($stock)) {
        $error_message = "All required fields must be filled out.";
    } elseif (!is_numeric($price) || $price < 0) {
        $error_message = "Price must be a valid non-negative number.";
    } elseif (!is_numeric($stock) || $stock < 0 || floor($stock) != $stock) {
        $error_message = "Stock must be a valid non-negative integer.";
    } elseif (strlen($product_name) > 30) {
        $error_message = "Product name must not exceed 30 characters.";
    } else {
        // Check for duplicate product name
        $query_check_name = "SELECT COUNT(*) AS name_count FROM PRODUCT WHERE fk1_shop_id = :shop_id AND name = :name";
        $stmt_check_name = oci_parse($conn, $query_check_name);
        oci_bind_by_name($stmt_check_name, ':shop_id', $shop_id);
        oci_bind_by_name($stmt_check_name, ':name', $product_name);
        oci_execute($stmt_check_name);
        $row_check_name = oci_fetch_assoc($stmt_check_name);
        oci_free_statement($stmt_check_name);

        if ($row_check_name['NAME_COUNT'] > 0) {
            $error_message = "Same name cannot be used. Please choose a different name.";
        } else {
            // Validate discount if selected
            $discount_id_to_use = ($discount_id === '' || $discount_id === 'none') ? null : $discount_id;
            if ($discount_id_to_use !== null) {
                $query_check_discount = "SELECT valid_upto FROM DISCOUNT WHERE discount_id = :discount_id AND valid_upto > SYSDATE";
                $stmt_check_discount = oci_parse($conn, $query_check_discount);
                oci_bind_by_name($stmt_check_discount, ':discount_id', $discount_id_to_use);
                oci_execute($stmt_check_discount);
                $discount_row = oci_fetch_assoc($stmt_check_discount);
                oci_free_statement($stmt_check_discount);

                if (!$discount_row) {
                    $error_message = "Selected discount is invalid or expired.";
                }
            }

            if (empty($error_message)) {
                // Insert new product
                $query_insert = "INSERT INTO PRODUCT (name, price, stock, fk1_shop_id, fk2_category_id, fk3_discount_id, description, unit, status, action) 
                                VALUES (:name, :price, :stock, :shop_id, :category_id, :discount_id, :description, :unit, :status, :action)";
                $stmt_insert = oci_parse($conn, $query_insert);
                if (!$stmt_insert) {
                    $e = oci_error($conn);
                    $error_message = "Failed to prepare statement: " . htmlentities($e['message']);
                } else {
                    oci_bind_by_name($stmt_insert, ':name', $product_name);
                    oci_bind_by_name($stmt_insert, ':price', $price);
                    oci_bind_by_name($stmt_insert, ':stock', $stock);
                    oci_bind_by_name($stmt_insert, ':shop_id', $shop_id);
                    oci_bind_by_name($stmt_insert, ':category_id', $category_id);
                    oci_bind_by_name($stmt_insert, ':discount_id', $discount_id_to_use);
                    oci_bind_by_name($stmt_insert, ':description', $description);
                    oci_bind_by_name($stmt_insert, ':unit', $unit);
                    oci_bind_by_name($stmt_insert, ':status', $status);
                    oci_bind_by_name($stmt_insert, ':action', $action);

                    if (oci_execute($stmt_insert)) {
                        $success_message = "Product added successfully!";

                        // Handle image upload
                        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                            // Get the last inserted product ID
                            $query_last_id = "SELECT MAX(product_id) as last_id FROM PRODUCT WHERE fk1_shop_id = :shop_id";
                            $stmt_last_id = oci_parse($conn, $query_last_id);
                            oci_bind_by_name($stmt_last_id, ':shop_id', $shop_id);
                            oci_execute($stmt_last_id);
                            $row_last_id = oci_fetch_assoc($stmt_last_id);
                            oci_free_statement($stmt_last_id);

                            if ($row_last_id) {
                                $product_id = $row_last_id['LAST_ID'];
                                $image_data = file_get_contents($_FILES['product_image']['tmp_name']);
                                $query_update_image = "UPDATE PRODUCT SET image = EMPTY_BLOB(), action = 'Update' 
                                                    WHERE product_id = :product_id RETURNING image INTO :image_blob";
                                $stmt_update_image = oci_parse($conn, $query_update_image);
                                $blob = oci_new_descriptor($conn, OCI_D_LOB);
                                oci_bind_by_name($stmt_update_image, ':product_id', $product_id);
                                oci_bind_by_name($stmt_update_image, ':image_blob', $blob, -1, OCI_B_BLOB);

                                if (oci_execute($stmt_update_image, OCI_DEFAULT)) {
                                    if ($blob->write($image_data) === false) {
                                        $error_message = "Failed to write product image to database.";
                                        oci_rollback($conn);
                                    } else {
                                        oci_commit($conn);
                                    }
                                } else {
                                    $error_message = "Failed to upload product image.";
                                    oci_rollback($conn);
                                }

                                $blob->free();
                                oci_free_statement($stmt_update_image);
                            }
                        } else {
                            oci_commit($conn);
                        }
                    } else {
                        $e = oci_error($stmt_insert);
                        if (strpos($e['message'], 'ORA-20001') !== false) {
                            $error_message = "Same name cannot be used. Please choose a different name.";
                        } elseif (strpos($e['message'], 'ORA-20007') !== false) {
                            $error_message = "Selected discount is expired.";
                        } else {
                            $error_message = "Failed to add product: " . htmlentities($e['message']);
                        }
                        oci_rollback($conn);
                    }

                    oci_free_statement($stmt_insert);
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
    <title>CleckBasket Trader Center - Add Product</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .sidebar-item {
            transition: all 0.3s;
        }

        .sidebar-item:hover {
            background-color: rgba(0, 0, 0, 0.05);
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

        .input-error {
            border-color: #ef4444 !important;
        }

        input:checked+.slider {
            background-color: #f97316;
        }

        input:checked+.slider:before {
            transform: translateX(26px);
        }

        .required:after {
            content: "*";
            color: red;
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
                        <a href="trader_add_product.php" class="block py-2 text-orange-500 font-semibold">Add Products</a>
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
                <!-- Orders Section -->
                <div class="px-4 py-2">
                    <button class="w-full flex items-center justify-between text-gray-700 hover:bg-gray-100 rounded-md p-2 sidebar-item">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 mr-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                            </svg>
                            <span class="text-lg font-medium">Orders</span>
                        </div>
                        <svg class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="ml-9 mt-2 hidden sidebar-content">
                        <a href="trader_orders.php" class="block py-2 text-gray-600 hover:text-orange-500">View Orders</a>
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
            <div class="container mx-auto px-6 py-8">
                <!-- Banner -->
                <div class="bg-white rounded-lg shadow-md p-8 mb-8 text-center">
                    <h2 class="text-3xl font-bold text-gray-800">Add Products</h2>
                    <p class="text-gray-600 mt-2">Create and upload new product listings for your shop.</p>
                </div>

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

                <form method="POST" action="trader_add_product.php" enctype="multipart/form-data" class="bg-white rounded-lg shadow-md p-6" id="add-product-form">
                    <!-- Product Name -->
                    <div class="mb-6">
                        <label for="product_name" class="block text-gray-700 text-lg mb-2 required">Product Name</label>
                        <input type="text" id="product_name" name="product_name" placeholder="Ex. Nikon Digital Camera" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                        <p class="text-sm text-gray-500 mt-1">Must be unique for your shop and not exceed 30 characters.</p>
                        <p id="name-error" class="text-sm text-red-600 mt-1 hidden">Same name cannot be used. Please choose a different name.</p>
                    </div>

                    <!-- Category -->
                    <div class="mb-6">
                        <label for="category" class="block text-gray-700 text-lg mb-2 required">Category</label>
                        <select id="category" name="category" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['CATEGORY_ID']); ?>">
                                    <?php echo htmlspecialchars($category['NAME']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Discount -->
                    <div class="mb-6">
                        <label for="discount" class="block text-gray-700 text-lg mb-2">Discount</label>
                        <select id="discount" name="discount" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="none">None</option>
                            <?php foreach ($discounts as $discount): ?>
                                <option value="<?php echo htmlspecialchars($discount['DISCOUNT_ID']); ?>">
                                    <?php echo htmlspecialchars($discount['PERCENT'] . '% (Valid until ' . $discount['VALID_UPTO'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Only non-expired discounts are shown.</p>
                    </div>

                    <!-- Product Images -->
                    <div class="mb-6">
                        <label for="product_image" class="block text-gray-700 text-lg mb-2 required">Product Images</label>
                        <div class="flex items-center space-x-4">
                            <div class="w-32 h-32 border border-gray-300 rounded-md flex items-center justify-center bg-gray-100">
                                <img id="preview_image" src="#" alt="Preview" class="max-w-full max-h-full hidden">
                                <div id="placeholder" class="text-gray-400">
                                    <svg class="w-10 h-10 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                            </div>
                            <label for="product_image" class="w-32 h-32 border border-gray-300 rounded-md flex items-center justify-center cursor-pointer bg-gray-100">
                                <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                <input type="file" id="product_image" name="product_image" class="hidden" accept="image/*" required>
                            </label>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-6">
                        <label for="description" class="block text-gray-700 text-lg mb-2">Description</label>
                        <textarea id="description" name="description" placeholder="Enter product description" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500" rows="4"></textarea>
                    </div>

                    <!-- Price, Stock, Unit & Active Status -->
                    <div class="mb-6">
                        <h3 class="text-gray-700 text-lg mb-4 required">Price, Stock & Unit</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            <div>
                                <label for="price" class="block text-gray-600 mb-1">Price</label>
                                <input type="number" id="price" name="price" placeholder="$200" min="0" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                            </div>
                            <div>
                                <label for="stock" class="block text-gray-600 mb-1">Stock</label>
                                <input type="number" id="stock" name="stock" placeholder="21" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                            </div>
                            <div>
                                <label for="unit" class="block text-gray-600 mb-1">Unit</label>
                                <input type="text" id="unit" name="unit" placeholder="e.g., Piece, Kg" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                            </div>
                            <div class="flex items-end">
                                <label class="toggle-switch mb-2">
                                    <input type="checkbox" name="active" checked>
                                    <span class="slider"></span>
                                </label>
                                <span class="ml-2 text-gray-600">Active</span>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="text-right">
                        <button type="submit" name="add_product" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-6 rounded-lg text-lg" id="submit-button">Submit</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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

            // Image preview
            const productImage = document.getElementById('product_image');
            const previewImage = document.getElementById('preview_image');
            const placeholder = document.getElementById('placeholder');

            productImage.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        previewImage.classList.remove('hidden');
                        placeholder.classList.add('hidden');
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Product name validation
            const productNameInput = document.getElementById('product_name');
            const nameError = document.getElementById('name-error');
            const form = document.getElementById('add-product-form');
            const submitButton = document.getElementById('submit-button');
            let isNameValid = true;

            async function checkProductName(name) {
                try {
                    const formData = new FormData();
                    formData.append('product_name', name);

                    const response = await fetch('check_product_name.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.error) {
                        console.error('Error:', data.error);
                        return false;
                    }

                    return data.exists;
                } catch (error) {
                    console.error('Fetch error:', error);
                    return false;
                }
            }

            productNameInput.addEventListener('input', async function() {
                const name = this.value.trim();
                if (name.length === 0) {
                    nameError.classList.add('hidden');
                    productNameInput.classList.remove('input-error');
                    isNameValid = true;
                    submitButton.disabled = false;
                    return;
                }

                const exists = await checkProductName(name);
                if (exists) {
                    nameError.classList.remove('hidden');
                    productNameInput.classList.add('input-error');
                    isNameValid = false;
                    submitButton.disabled = true;
                } else {
                    nameError.classList.add('hidden');
                    productNameInput.classList.remove('input-error');
                    isNameValid = true;
                    submitButton.disabled = false;
                }
            });

            form.addEventListener('submit', async function(event) {
                if (!isNameValid) {
                    event.preventDefault();
                    nameError.classList.remove('hidden');
                    productNameInput.classList.add('input-error');
                } else {
                    const name = productNameInput.value.trim();
                    if (name.length > 0) {
                        const exists = await checkProductName(name);
                        if (exists) {
                            event.preventDefault();
                            nameError.classList.remove('hidden');
                            productNameInput.classList.add('input-error');
                            isNameValid = false;
                            submitButton.disabled = true;
                        }
                    }
                }
            });
        });
    </script>
</body>

</html>

<?php
if (isset($conn)) {
    oci_close($conn);
}
include_once 'includes/footer.php';
?>