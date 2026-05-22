<?php
// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set UTF-8 encoding
ini_set('default_charset', 'UTF-8');

// Security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Generate CSRF token on every page load
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Debug: Log session data
error_log("login.php - CSRF token: " . $_SESSION['csrf_token']);

include_once 'includes/header.php';

// Retrieve and clear messages from session
$messages = [
    'register_success' => $_SESSION['register_success_message'] ?? null,
    'login_error' => $_SESSION['login_error'] ?? null,
    'login_info' => $_SESSION['info_message'] ?? null
];

// Clear session messages
unset($_SESSION['register_success_message'], $_SESSION['login_error'], $_SESSION['info_message']);

// Retrieve form data for repopulation
$form_email = $_SESSION['form_data']['email'] ?? '';
unset($_SESSION['form_data']);
?>

<div class="container mx-auto mt-10 mb-10">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md mx-auto">
        <h2 class="text-2xl font-bold text-center mb-6">Login</h2>

        <?php if ($messages['register_success']): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($messages['register_success']); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($messages['login_error']): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($messages['login_error']); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($messages['login_info']): ?>
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($messages['login_info']); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['debug_hashes'])): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong>Debug Hashes (remove in production):</strong><br>
                Stored: <?php echo htmlspecialchars($_SESSION['debug_hashes']['stored']); ?><br>
                SHA-256: <?php echo htmlspecialchars($_SESSION['debug_hashes']['generated_sha256']); ?><br>
                SHA-1: <?php echo htmlspecialchars($_SESSION['debug_hashes']['generated_sha1']); ?><br>
                MD5: <?php echo htmlspecialchars($_SESSION['debug_hashes']['generated_md5']); ?><br>
                Reversal: <?php echo htmlspecialchars($_SESSION['debug_hashes']['generated_reversal']); ?>
            </div>
        <?php endif; ?>

        <form action="login_process.php" method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-4">
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email Address</label>
                <input type="email" id="email" name="email" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="<?php echo htmlspecialchars($form_email); ?>" required>
            </div>
            <div class="mb-6">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <input type="password" id="password" name="password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline" required>
                <a href="forgot_password.php" class="text-xs text-orange-500 hover:text-orange-700 float-right">Forgot Password?</a>
            </div>
            <div class="flex items-center justify-between mt-4">
                <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Sign In
                </button>
                <a href="register.php" class="inline-block align-baseline font-bold text-sm text-orange-500 hover:text-orange-800">
                    Don't have an account? Sign Up
                </a>
            </div>
        </form>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>