<?php
session_start();
require_once 'php_logic/connect.php'; // Connect to Oracle DB
require_once 'includes/header.php';

ini_set('default_charset', 'UTF-8');

// Security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error_message = '';
$success_message = '';
$email = '';
$token = '';

if (isset($_GET['email']) && isset($_GET['token'])) {
    $email = urldecode($_GET['email']);
    $token = urldecode($_GET['token']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format provided in the reset link.";
    } else {
        // Verify token and check expiry
        $query_verify = "SELECT user_id, reset_token_expiry FROM Users WHERE email = :email_bv AND reset_token = :token_bv";
        $stmt_verify = oci_parse($conn, $query_verify);
        oci_bind_by_name($stmt_verify, ":email_bv", $email);
        oci_bind_by_name($stmt_verify, ":token_bv", $token);

        if (oci_execute($stmt_verify)) {
            $user_data = oci_fetch_assoc($stmt_verify);
            oci_free_statement($stmt_verify);

            if ($user_data) {
                $expiry = strtotime($user_data['RESET_TOKEN_EXPIRY']);
                if ($expiry < time()) {
                    $error_message = "The password reset link has expired. Please request a new one.";
                }
            } else {
                $error_message = "Invalid or expired reset link.";
            }
        } else {
            $e = oci_error($stmt_verify);
            $error_message = "Database error: " . htmlentities($e['message']);
            error_log("OCI Error in reset_password.php (verify): " . $e['message']);
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['password']) && isset($_POST['confirm_password'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid CSRF token.";
    } else {
        $email = trim($_POST['email']);
        $token = trim($_POST['token']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($password) || empty($confirm_password)) {
            $error_message = "Both password fields are required.";
        } elseif ($password !== $confirm_password) {
            $error_message = "Passwords do not match.";
        } elseif (strlen($password) < 8 || !preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password) || !preg_match("/[0-9]/", $password) || !preg_match("/[!@#$%^&*()_+\-=\[\]{};':\"\\|,.<>\/?~`]/", $password)) {
            $error_message = "Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character.";
        } else {
            // Verify token again
            $query_verify = "SELECT user_id FROM Users WHERE email = :email_bv AND reset_token = :token_bv AND reset_token_expiry > CURRENT_TIMESTAMP";
            $stmt_verify = oci_parse($conn, $query_verify);
            oci_bind_by_name($stmt_verify, ":email_bv", $email);
            oci_bind_by_name($stmt_verify, ":token_bv", $token);

            if (oci_execute($stmt_verify)) {
                $user_data = oci_fetch_assoc($stmt_verify);
                oci_free_statement($stmt_verify);

                if ($user_data) {
                    // Update password (TRIG_HASH_PASSWORD will hash it)
                    $query_update_password = "UPDATE Users SET password = :password_bv, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = :user_id_bv";
                    $stmt_update_password = oci_parse($conn, $query_update_password);
                    oci_bind_by_name($stmt_update_password, ":password_bv", $password);
                    oci_bind_by_name($stmt_update_password, ":user_id_bv", $user_data['USER_ID']);

                    if (oci_execute($stmt_update_password)) {
                        $success_message = "Your password has been successfully reset. You can now log in with your new password.";
                        unset($_SESSION['csrf_token']);
                    } else {
                        $e = oci_error($stmt_update_password);
                        $error_message = "Database error updating password: " . htmlentities($e['message']);
                        error_log("OCI Error in reset_password.php (update): " . $e['message']);
                    }
                    oci_free_statement($stmt_update_password);
                } else {
                    $error_message = "Invalid or expired reset link.";
                }
            } else {
                $e = oci_error($stmt_verify);
                $error_message = "Database error: " . htmlentities($e['message']);
                error_log("OCI Error in reset_password.php (verify post): " . $e['message']);
            }
        }
    }
}

oci_close($conn);
?>

<div class="container mx-auto mt-10 mb-10">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md mx-auto">
        <h2 class="text-2xl font-bold text-center mb-6">Reset Your Password</h2>

        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $success_message; ?></span>
            </div>
            <div class="text-center">
                <a href="login.php" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2 px-6 rounded-md">Go to Login</a>
            </div>
        <?php elseif ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$success_message && !$error_message): ?>
            <form action="reset_password.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="mb-4">
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">New Password</label>
                    <input type="password" id="password" name="password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <p class="text-xs text-gray-500 mt-1">Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character.</p>
                </div>
                <div class="mb-6">
                    <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div class="flex items-center justify-center">
                    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Reset Password
                    </button>
                </div>
            </form>
        <?php endif; ?>
        <div class="text-center mt-4">
            <a href="login.php" class="text-sm text-orange-500 hover:text-orange-700">Back to Login</a>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>