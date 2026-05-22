<?php
session_start();
require_once 'php_logic/connect.php'; // Connect to Oracle DB
require_once 'includes/header.php'; // To show the consistent site header

// Set UTF-8 encoding
ini_set('default_charset', 'UTF-8');

// Security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

$verification_message = "";
$message_type = "error"; // Can be 'success', 'info', or 'error'

if (isset($_GET['email']) && isset($_GET['token'])) {
    $email = urldecode($_GET['email']);
    $token = urldecode($_GET['token']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $verification_message = "Invalid email format provided in the verification link.";
    } else {
        // Find the user by email and token, and check if not already verified
        $query_verify = "SELECT user_id, email_verified, email_verification_token FROM Users WHERE email = :email_bv AND email_verification_token = :token_bv";
        $stmt_verify = oci_parse($conn, $query_verify);
        oci_bind_by_name($stmt_verify, ":email_bv", $email);
        oci_bind_by_name($stmt_verify, ":token_bv", $token);

        if (!oci_execute($stmt_verify)) {
            $e = oci_error($stmt_verify);
            $verification_message = "Database error during verification: " . htmlentities($e['message']);
            error_log("OCI Error in verify_email.php (select): " . $e['message']);
        } else {
            $user_data = oci_fetch_assoc($stmt_verify);

            if ($user_data) {
                if ($user_data['EMAIL_VERIFIED'] == 'Y') {
                    $verification_message = "This email address (<strong>" . htmlspecialchars($email) . "</strong>) has already been verified.";
                    $message_type = "info";
                } else {
                    // Email and token match, and not yet verified. Proceed to verify.
                    $query_update_user = "UPDATE Users SET email_verified = 'Y', email_verification_token = NULL WHERE user_id = :user_id_bv";

                    $stmt_update_user = oci_parse($conn, $query_update_user);
                    oci_bind_by_name($stmt_update_user, ":user_id_bv", $user_data['USER_ID']);

                    if (oci_execute($stmt_update_user)) {
                        $verification_message = "Your email address (<strong>" . htmlspecialchars($email) . "</strong>) has been successfully verified! You can now log in.";
                        $message_type = "success";
                        $_SESSION['login_success_message'] = "Email verified successfully. Please log in.";
                    } else {
                        $e = oci_error($stmt_update_user);
                        $verification_message = "Database error updating verification status: " . htmlentities($e['message']);
                        error_log("OCI Error in verify_email.php (update): " . $e['message']);
                    }
                    oci_free_statement($stmt_update_user);
                }
            } else {
                // Check if the email exists and is already verified
                $query_check_verified = "SELECT email_verified FROM Users WHERE email = :email_bv";
                $stmt_check_verified = oci_parse($conn, $query_check_verified);
                oci_bind_by_name($stmt_check_verified, ":email_bv", $email);
                oci_execute($stmt_check_verified);
                $existing_user = oci_fetch_assoc($stmt_check_verified);
                oci_free_statement($stmt_check_verified);

                if ($existing_user && $existing_user['EMAIL_VERIFIED'] == 'Y') {
                    $verification_message = "This email address (<strong>" . htmlspecialchars($email) . "</strong>) has already been verified. The link may have expired or been used.";
                    $message_type = "info";
                } else {
                    $verification_message = "Invalid or expired verification link. Please ensure you copied the full link, or try registering again.";
                }
            }
        }
        oci_free_statement($stmt_verify);
    }
} else {
    $verification_message = "Missing verification details. Please use the link sent to your email.";
}

oci_close($conn);
?>

<div class="container mx-auto px-6 py-12">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-lg p-8 text-center">
        <?php if ($message_type === 'success'): ?>
            <h2 class="text-2xl font-bold text-green-600 mb-4">Verification Successful!</h2>
        <?php elseif ($message_type === 'info'): ?>
            <h2 class="text-2xl font-bold text-blue-600 mb-4">Information</h2>
        <?php else: ?>
            <h2 class="text-2xl font-bold text-red-600 mb-4">Verification Failed</h2>
        <?php endif; ?>

        <p class="text-gray-700 mb-6"><?php echo $verification_message; ?></p>

        <?php if ($message_type === 'success' || $message_type === 'info'): ?>
            <a href="login.php" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2 px-6 rounded-md transition duration-300">Go to Login</a>
        <?php else: ?>
            <a href="register.php" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2 px-6 rounded-md transition duration-300">Go to Registration</a>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>