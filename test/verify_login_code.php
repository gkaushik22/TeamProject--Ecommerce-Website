<?php
session_start();
ini_set('default_charset', 'UTF-8');

// Security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Redirect to login if no email attempt is in session
if (!isset($_SESSION["login_email_attempt"]) || !isset($_SESSION["login_verification_code"]) || !isset($_SESSION["login_user_id_attempt"])) {
    $_SESSION['login_error'] = "Verification process not initiated or session expired. Please log in again.";
    header("Location: login.php");
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once "includes/header.php";

$error_message = $_SESSION["login_code_error"] ?? null;
$info_message = $_SESSION["info_message"] ?? null;

// Clear messages after displaying them once
if ($error_message) unset($_SESSION["login_code_error"]);
if ($info_message) unset($_SESSION["info_message"]);
?>

<div class="container mx-auto mt-10 mb-10">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md mx-auto">
        <h2 class="text-2xl font-bold text-center mb-6">Enter Verification Code</h2>

        <?php if ($info_message): ?>
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($info_message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <p class="text-gray-600 text-center mb-4">
            A verification code has been sent to <strong><?php echo htmlspecialchars($_SESSION["login_email_attempt"]); ?></strong>.
            Please enter the code below to complete your login.
        </p>

        <form action="process_login_code.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-4">
                <label for="login_code" class="block text-gray-700 text-sm font-bold mb-2">Verification Code</label>
                <input type="text" id="login_code" name="login_code" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required autofocus maxlength="6" pattern="[0-9]{6}" title="Enter the 6-digit code.">
            </div>

            <div class="flex items-center justify-center">
                <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Verify and Log In
                </button>
            </div>
        </form>
        <div class="text-center mt-4">
            <a href="login.php" class="text-sm text-orange-500 hover:text-orange-700">Cancel and return to login</a>
        </div>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>