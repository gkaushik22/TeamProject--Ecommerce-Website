<?php 
// Ensure session is started at the very beginning
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'php_logic/connect.php'; // Connect to Oracle DB
include_once 'includes/header.php'; 

$category_id = null;
$category_name = "All Categories";
$products = [];
$categories_list = []; // For displaying all categories if no specific one is selected

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $category_id = (int)$_GET['id'];

    // Fetch specific category details
    $query_cat_name = "SELECT name as category_name FROM PRODUCT_CATEGORY WHERE category_id = :cat_id_bv";
    $stmt_cat_name = oci_parse($conn, $query_cat_name);
    oci_bind_by_name($stmt_cat_name, ":cat_id_bv", $category_id);
    if (oci_execute($stmt_cat_name)) {
        $cat_row = oci_fetch_assoc($stmt_cat_name);
        if ($cat_row) {
            $category_name = $cat_row['CATEGORY_NAME'];
        }
    }
    oci_free_statement($stmt_cat_name);

    // Fetch products for the selected category
    // TODO: Implement pagination later if needed
    $query_products = "SELECT p.product_id, p.name as product_name, p.price, 'unit' as unit, 
                              'assets/images/products/default.png' as image_path, p.stock as stock_available, 
                              'Product description' as description, s.name AS trader_name 
                       FROM PRODUCT p 
                       JOIN SHOP s ON p.fk1_shop_id = s.shop_id
                       WHERE p.fk2_category_id = :cat_id_bv AND p.stock > 0 
                       ORDER BY p.name ASC";
    $stmt_products = oci_parse($conn, $query_products);
    oci_bind_by_name($stmt_products, ":cat_id_bv", $category_id);
    if (oci_execute($stmt_products)) {
        while ($row = oci_fetch_assoc($stmt_products)) {
            $products[] = $row;
        }
    } else {
        $e = oci_error($stmt_products);
        error_log("OCI Error fetching products for category ID " . $category_id . ": " . $e['message']);
    }
    oci_free_statement($stmt_products);
} else {
    // No specific category ID, so fetch all categories to display
    $query_all_categories = "SELECT category_id, name as category_name FROM PRODUCT_CATEGORY ORDER BY name ASC";
    $stmt_all_categories = oci_parse($conn, $query_all_categories);
    if (oci_execute($stmt_all_categories)) {
        while ($row = oci_fetch_assoc($stmt_all_categories)) {
            // Get product count for each category
            $count_query = "SELECT COUNT(*) AS PRODUCT_COUNT FROM PRODUCT WHERE fk2_category_id = :cat_id_bv AND stock > 0";
            $stmt_count = oci_parse($conn, $count_query);
            oci_bind_by_name($stmt_count, ":cat_id_bv", $row['CATEGORY_ID']);
            oci_execute($stmt_count);
            $product_count_row = oci_fetch_assoc($stmt_count);
            $row['PRODUCT_COUNT'] = $product_count_row ? $product_count_row['PRODUCT_COUNT'] : 0;
            oci_free_statement($stmt_count);
            $categories_list[] = $row;
        }
    } else {
        $e = oci_error($stmt_all_categories);
        error_log("OCI Error fetching all categories: " . $e['message']);
    }
    oci_free_statement($stmt_all_categories);
}

?>

<div class="container mx-auto px-6 py-8">
    <section class="text-center mb-12">
        <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">
            <?php echo htmlspecialchars($category_name); ?>
        </h1>
        <?php if ($category_id && empty($products)): ?>
            <p class="text-lg text-gray-600">No products currently available in this category. Please check back later.</p>
        <?php elseif (!$category_id && empty($categories_list)): ?>
             <p class="text-lg text-gray-600">No categories available at the moment. Please check back later.</p>
        <?php elseif (!$category_id): ?>
            <p class="text-lg text-gray-600">Discover a wide range of products across our vibrant categories. Click on a category to explore.</p>
        <?php endif; ?>
    </section>

    <?php if ($category_id): // Display products if a specific category is selected ?>
        <?php if (!empty($products)): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
            <?php foreach ($products as $product): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition duration-300 flex flex-col">
                <a href="product_detail.php?id=<?php echo $product['PRODUCT_ID']; ?>" class="block">
                    <div class="h-48 bg-gray-200 flex items-center justify-center text-gray-500">
                        <img src="get_product_image.php?id=<?php echo $product['PRODUCT_ID']; ?>" alt="<?php echo htmlspecialchars($product['PRODUCT_NAME']); ?>" class="h-full w-full object-cover">
                    </div>
                </a>
                <div class="p-6 flex flex-col flex-grow">
                    <h3 class="text-xl font-semibold text-gray-800 mb-2"><a href="product_detail.php?id=<?php echo $product['PRODUCT_ID']; ?>" class="hover:text-orange-600"><?php echo htmlspecialchars($product['PRODUCT_NAME']); ?></a></h3>
                    <p class="text-sm text-gray-500 mb-1">Sold by: <?php echo htmlspecialchars($product['TRADER_NAME']); ?></p>
                    <p class="text-orange-500 font-bold text-lg mb-1">$<?php echo htmlspecialchars(number_format($product['PRICE'], 2)); ?> <?php if($product['UNIT']) echo "/ " . htmlspecialchars($product['UNIT']); ?></p>
                    <p class="text-sm text-gray-600 mb-3 truncate" title="<?php echo htmlspecialchars($product['DESCRIPTION']); ?>"><?php echo nl2br(htmlspecialchars(substr($product['DESCRIPTION'], 0, 70))) . (strlen($product['DESCRIPTION']) > 70 ? '...' : ''); ?></p>
                    <p class="text-sm text-gray-600 mb-3">In Stock: <?php echo htmlspecialchars($product['STOCK_AVAILABLE'] > 0 ? $product['STOCK_AVAILABLE'] : 'Out of Stock'); ?></p>
                    
                    <form action="add_to_cart.php" method="POST" class="mt-auto">
                        <input type="hidden" name="product_id" value="<?php echo $product['PRODUCT_ID']; ?>">
                        <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($product['PRODUCT_NAME']); ?>">
                        <input type="hidden" name="price" value="<?php echo htmlspecialchars($product['PRICE']); ?>">
                        <?php if ($product['STOCK_AVAILABLE'] > 0): ?>
                        <div class="flex items-center mb-4">
                            <label for="quantity_<?php echo $product['PRODUCT_ID']; ?>" class="sr-only">Quantity</label>
                            <input type="number" id="quantity_<?php echo $product['PRODUCT_ID']; ?>" name="quantity" value="1" min="1" max="<?php echo htmlspecialchars($product['STOCK_AVAILABLE']); ?>" class="w-16 text-center border border-gray-300 rounded-md py-1 px-2 mr-2">
                        </div>
                        <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2 px-4 rounded-md transition duration-300">Add to Cart</button>
                        <?php else: ?>
                        <p class="text-red-500 font-semibold">Out of Stock</p>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <!-- This message is now handled by the top section -->
        <?php endif; ?>
    <?php elseif (!empty($categories_list)): // Display all categories if no specific category ID ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php foreach ($categories_list as $category_item): ?>
            <div class="bg-white rounded-lg shadow-md p-6 text-center hover:shadow-xl transition duration-300">
                <h3 class="text-xl font-semibold text-gray-700 mb-2"><?php echo htmlspecialchars($category_item['CATEGORY_NAME']); ?></h3>
                <p class="text-sm text-gray-500 mb-4"><?php echo htmlspecialchars($category_item['PRODUCT_COUNT']); ?> products</p>
                <a href="category.php?id=<?php echo urlencode($category_item['CATEGORY_ID']); ?>" class="bg-orange-500 hover:bg-orange-600 text-white font-medium py-2 px-4 rounded-md text-sm transition duration-300">Explore <?php echo htmlspecialchars($category_item['CATEGORY_NAME']); ?></a>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
         <!-- This message is now handled by the top section -->
    <?php endif; ?>

</div>

<?php 
if(isset($conn)) oci_close($conn);
include_once 'includes/footer.php'; 
?>
