<?php
session_start();
ini_set('default_charset', 'UTF-8');

// Security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Ensure session is active
if (session_status() !== PHP_SESSION_ACTIVE) {
    error_log("Session not active in register.php");
    $_SESSION['register_error'] = "Session error. Please try again.";
}

// Generate fresh CSRF token on every page load
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
error_log("Generated CSRF token in register.php: " . $_SESSION['csrf_token']);

// Retrieve and clear error message
$register_error = $_SESSION['register_error'] ?? null;
if ($register_error) unset($_SESSION['register_error']);

// Retrieve form data for repopulation
$form_data = $_SESSION['form_data'] ?? [];
if (isset($_SESSION['form_data'])) unset($_SESSION['form_data']);

include_once 'includes/header.php';
?>

<div class="container mx-auto mt-10">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-lg mx-auto">
        <h2 class="text-2xl font-bold text-center mb-2">Register</h2>
        <div class="text-center mb-6">
            <span class="text-orange-500 border-b-2 border-orange-500 pb-1 px-2 text-sm font-semibold">CUSTOMER</span>
            <span class="text-gray-500 ml-4 text-sm font-semibold">TRADER</span>
        </div>

        <?php if ($register_error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($register_error); ?></span>
            </div>
        <?php endif; ?>

        <form id="registerForm" action="register_process.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="first_name" class="block text-gray-700 text-sm font-bold mb-2">First Name</label>
                    <input type="text" id="first_name" name="first_name" placeholder="Enter your first name" value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div>
                    <label for="last_name" class="block text-gray-700 text-sm font-bold mb-2">Last Name</label>
                    <input type="text" id="last_name" name="last_name" placeholder="Enter your last name" value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
            </div>

            <div class="mb-4">
                <label for="gender" class="block text-gray-700 text-sm font-bold mb-2">Gender</label>
                <select id="gender" name="gender" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Select Gender</option>
                    <option value="Male" <?php if (($form_data['gender'] ?? '') == 'Male') echo 'selected'; ?>>Male</option>
                    <option value="Female" <?php if (($form_data['gender'] ?? '') == 'Female') echo 'selected'; ?>>Female</option>
                    <option value="Other" <?php if (($form_data['gender'] ?? '') == 'Other') echo 'selected'; ?>>Other</option>
                </select>
            </div>

            <div class="mb-4">
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>

            <div class="mb-4">
                <label for="contact" class="block text-gray-700 text-sm font-bold mb-2">Contact</label>
                <input type="text" id="contact" name="contact" placeholder="Enter your contact (10 digits)" value="<?php echo htmlspecialchars($form_data['contact'] ?? ''); ?>" maxlength="10" pattern="\d{10}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                <p class="text-xs text-gray-500 mt-1">Must be exactly 10 digits.</p>
            </div>

            <div class="mb-4">
                <label for="address" class="block text-gray-700 text-sm font-bold mb-2">Address</label>
                <textarea id="address" name="address" placeholder="Enter your address" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required><?php echo htmlspecialchars($form_data['address'] ?? ''); ?></textarea>
            </div>

            <div class="mb-4">
                <label for="usertype" class="block text-gray-700 text-sm font-bold mb-2">User Type</label>
                <select id="usertype" name="usertype" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Select User Type</option>
                    <option value="CUSTOMER" <?php if (($form_data['usertype'] ?? '') == 'CUSTOMER') echo 'selected'; ?>>Customer</option>
                    <option value="TRADER" <?php if (($form_data['usertype'] ?? '') == 'TRADER') echo 'selected'; ?>>Trader</option>
                </select>
            </div>

            <!-- Trader-specific fields (hidden by default) -->
            <div id="trader_fields" class="hidden">
                <div class="mb-4">
                    <label for="license" class="block text-gray-700 text-sm font-bold mb-2">Trader License Number</label>
                    <input type="text" id="license" name="license" placeholder="Enter your license number" value="<?php echo htmlspecialchars($form_data['license'] ?? ''); ?>" maxlength="20" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <p class="text-xs text-gray-500 mt-1">Maximum 20 characters.</p>
                </div>
                <div class="mb-4">
                    <label for="shop_name" class="block text-gray-700 text-sm font-bold mb-2">Shop Name</label>
                    <input type="text" id="shop_name" name="shop_name" placeholder="Enter your shop name" value="<?php echo htmlspecialchars($form_data['shop_name'] ?? ''); ?>" maxlength="30" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <p class="text-xs text-gray-500 mt-1">Maximum 30 characters.</p>
                </div>
                <div class="mb-4">
                    <label for="shop_type" class="block text-gray-700 text-sm font-bold mb-2">Shop Type</label>
                    <select id="shop_type" name="shop_type" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">Select Shop Type</option>
                        <option value="Grocery" <?php if (($form_data['shop_type'] ?? '') == 'Grocery') echo 'selected'; ?>>Grocery</option>
                        <option value="Electronics" <?php if (($form_data['shop_type'] ?? '') == 'Electronics') echo 'selected'; ?>>Electronics</option>
                        <option value="Clothing" <?php if (($form_data['shop_type'] ?? '') == 'Clothing') echo 'selected'; ?>>Clothing</option>
                        <option value="Home & Garden" <?php if (($form_data['shop_type'] ?? '') == 'Home & Garden') echo 'selected'; ?>>Home & Garden</option>
                        <option value="Beauty & Health" <?php if (($form_data['shop_type'] ?? '') == 'Beauty & Health') echo 'selected'; ?>>Beauty & Health</option>
                        <option value="Sports & Outdoors" <?php if (($form_data['shop_type'] ?? '') == 'Sports & Outdoors') echo 'selected'; ?>>Sports & Outdoors</option>
                        <option value="Toys & Games" <?php if (($form_data['shop_type'] ?? '') == 'Toys & Games') echo 'selected'; ?>>Toys & Games</option>
                        <option value="Books & Stationery" <?php if (($form_data['shop_type'] ?? '') == 'Books & Stationery') echo 'selected'; ?>>Books & Stationery</option>
                        <option value="Food & Beverage" <?php if (($form_data['shop_type'] ?? '') == 'Food & Beverage') echo 'selected'; ?>>Food & Beverage</option>
                        <option value="Other" <?php if (($form_data['shop_type'] ?? '') == 'Other') echo 'selected'; ?>>Other</option>
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <input type="password" id="password" name="password" placeholder="Password must be at least 8 characters long..." class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                <p class="text-xs text-gray-500 mt-1">Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character (e.g. !@#$%^&*).</p>
            </div>

            <div class="mb-6">
                <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>

            <div class="flex flex-col items-center">
                <button type="submit" id="submitButton" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline mb-4">
                    REGISTER
                </button>
                <a href="login.php" class="inline-block align-baseline font-bold text-sm text-orange-500 hover:text-orange-800">
                    Already have an account? Login
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    // Toggle trader fields based on user type selection
    document.getElementById('usertype').addEventListener('change', function() {
        const traderFields = document.getElementById('trader_fields');
        const licenseInput = document.getElementById('license');
        const shopNameInput = document.getElementById('shop_name');
        const shopTypeInput = document.getElementById('shop_type');

        if (this.value === 'TRADER') {
            traderFields.classList.remove('hidden');
            licenseInput.setAttribute('required', 'required');
            shopNameInput.setAttribute('required', 'required');
            shopTypeInput.setAttribute('required', 'required');
        } else {
            traderFields.classList.add('hidden');
            licenseInput.removeAttribute('required');
            shopNameInput.removeAttribute('required');
            shopTypeInput.removeAttribute('required');
        }
    });

    // Prevent multiple form submissions
    document.getElementById('registerForm').addEventListener('submit', function() {
        const submitButton = document.getElementById('submitButton');
        submitButton.disabled = true;
        submitButton.textContent = 'Submitting...';
    });

    // Trigger change event on page load to handle repopulated form
    document.getElementById('usertype').dispatchEvent(new Event('change'));
</script>

<?php include_once 'includes/footer.php'; ?>