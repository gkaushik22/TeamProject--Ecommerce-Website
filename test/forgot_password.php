<?php
session_start();
require_once 'php_logic/connect.php'; // Connect to Oracle DB
require_once 'vendor/autoload.php'; // Include PHPMailer
require_once 'config.php'; // Include SMTP config
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

ini_set('default_charset', 'UTF-8');

// Security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

include_once 'includes/header.php';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting (simple: max 3 requests per email per hour)
$reset_limit_key = 'reset_limit_' . md5(isset($_POST['email']) ? trim($_POST['email']) : '');
$reset_limit = $_SESSION[$reset_limit_key] ?? ['count' => 0, 'timestamp' => time()];
if (time() - $reset_limit['timestamp'] > 3600) {
    $reset_limit = ['count' => 0, 'timestamp' => time()];
}

$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid CSRF token.";
    } elseif ($reset_limit['count'] >= 3) {
        $error_message = "Too many password reset requests. Please try again later.";
    } else {
        $email = trim($_POST['email']);

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            // Check if user exists
            $query_user = "SELECT user_id, first_name, email_verified FROM Users WHERE email = :email_bv";
            $stmt_user = oci_parse($conn, $query_user);
            oci_bind_by_name($stmt_user, ":email_bv", $email);
            if (oci_execute($stmt_user)) {
                $user_data = oci_fetch_assoc($stmt_user);
                oci_free_statement($stmt_user);

                if ($user_data && $user_data['EMAIL_VERIFIED'] == 'Y') {
                    // Generate reset token
                    $reset_token = bin2hex(random_bytes(32));
                    $reset_expiry = time() + 3600; // 1 hour expiry

                    // Store reset token in database
                    $query_store_token = "UPDATE Users SET reset_token = :token_bv, reset_token_expiry = TO_TIMESTAMP(:expiry_bv, 'YYYY-MM-DD HH24:MI:SS') WHERE email = :email_bv";
                    $stmt_store_token = oci_parse($conn, $query_store_token);
                    $expiry_str = date('Y-m-d H:i:s', $reset_expiry);
                    oci_bind_by_name($stmt_store_token, ":token_bv", $reset_token);
                    oci_bind_by_name($stmt_store_token, ":expiry_bv", $expiry_str);
                    oci_bind_by_name($stmt_store_token, ":email_bv", $email);

                    if (oci_execute($stmt_store_token)) {
                        oci_free_statement($stmt_store_token);

                        // Generate reset link
                        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?email=" . urlencode($email) . "&token=" . urlencode($reset_token);

                        // Send reset email
                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host = 'smtp.gmail.com';
                            $mail->SMTPAuth = true;
                            $mail->Username = SMTP_USERNAME;
                            $mail->Password = SMTP_PASSWORD;
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port = 587;

                            $mail->setFrom('noreply@cleckbasket.local', 'CleckBasket Team');
                            $mail->addAddress($email, $user_data['FIRST_NAME']);

                            $mail->isHTML(true);
                            $mail->Subject = 'Reset Your CleckBasket Password';
                            $mail->Body = "<html><body><h3>Hello " . htmlspecialchars($user_data['FIRST_NAME']) . ",</h3><p>We received a request to reset your password. Please click the link below to reset it:</p><p><a href='" . $reset_link . "'>" . $reset_link . "</a></p><p>This link is valid for 1 hour. If you did not request a password reset, please ignore this email.</p><p>Regards,<br>The CleckBasket Team</p></body></html>";
                            $mail->AltBody = "Hello " . htmlspecialchars($user_data['FIRST_NAME']) . ",\n\nWe received a request to reset your password. Please copy and paste the following link into your browser to reset it:\n" . $reset_link . "\n\nThis link is valid for 1 hour. If you did not request a password reset, please ignore this email.\n\nRegards,\nThe CleckBasket Team";

                            $mail->send();
                            $success_message = "A password reset link has been sent to " . htmlspecialchars($email) . ". Please check your inbox (and spam folder).";

                            // Update rate limit
                            $reset_limit['count']++;
                            $reset_limit['timestamp'] = time();
                            $_SESSION[$reset_limit_key] = $reset_limit;
                        } catch (Exception $e) {
                            error_log("PHPMailer Error in forgot_password.php: " . $mail->ErrorInfo);
                            $error_message = "Could not send reset email. Please try again later or contact support. Reset Link: " . $reset_link;
                        }
                    } else {
                        $e = oci_error($stmt_store_token);
                        $error_message = "Database error: " . htmlentities($e['message']);
                        error_log("OCI Error in forgot_password.php (store token): " . $e['message']);
                        oci_free_statement($stmt_store_token);
                    }
                } else {
                    $error_message = "No verified account found with this email address.";
                }
            } else {
                $e = oci_error($stmt_user);
                $error_message = "Database error: " . htmlentities($e['message']);
                error_log("OCI Error in forgot_password.php: " . $e['message']);
            }
        }
    }
}

oci_close($conn);
?>

<div class="container mx-auto mt-10 mb-10">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md mx-auto">
        <h2 class="text-2xl font-bold text-center mb-6">Reset Password</h2>

        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $success_message; ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <form action="forgot_password.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-4">
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email Address</label>
                <input type="email" id="email" name="email" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            <div class="flex items-center justify-center">
                <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Send Reset Link
                </button>
            </div>
        </form>
        <div class="text-center mt-4">
            <a href="login.php" class="text-sm text-orange-500 hover:text-orange-700">Back to Login</a>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>