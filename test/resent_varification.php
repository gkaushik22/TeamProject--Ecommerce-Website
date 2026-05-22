<?php
session_start();
require_once 'php_logic/connect.php'; // Connect to Oracle DB
require_once 'vendor/autoload.php'; // Include PHPMailer
require_once 'config.php'; // Include SMTP config
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

ini_set('default_charset', 'UTF-8');

// Security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

include_once 'includes/header.php';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting (max 3 requests per email per hour)
$verify_limit_key = 'verify_limit_' . md5(isset($_POST['email']) ? trim($_POST['email']) : '');
$verify_limit = $_SESSION[$verify_limit_key] ?? ['count' => 0, 'timestamp' => time()];
if (time() - $verify_limit['timestamp'] > 3600) {
    $verify_limit = ['count' => 0, 'timestamp' => time()];
}

$email = isset($_GET['email']) ? urldecode(trim($_GET['email'])) : '';
$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid CSRF token.";
    } elseif ($verify_limit['count'] >= 3) {
        $error_message = "Too many verification email requests. Please try again later.";
    } else {
        $email = trim($_POST['email']);

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            // Check if user exists and is not verified
            $query_user = "SELECT user_id, first_name, email_verified, email_verification_token FROM Users WHERE email = :email_bv";
            $stmt_user = oci_parse($conn, $query_user);
            oci_bind_by_name($stmt_user, ":email_bv", $email);

            if (oci_execute($stmt_user)) {
                $user_data = oci_fetch_assoc($stmt_user);
                oci_free_statement($stmt_user);

                if ($user_data) {
                    if ($user_data['EMAIL_VERIFIED'] == 'Y') {
                        $error_message = "This email address is already verified. Please log in.";
                    } else {
                        // Generate new verification token if needed
                        $token = $user_data['EMAIL_VERIFICATION_TOKEN'] ?: bin2hex(random_bytes(32));
                        if (!$user_data['EMAIL_VERIFICATION_TOKEN']) {
                            $query_update_token = "UPDATE Users SET email_verification_token = :token_bv WHERE email = :email_bv";
                            $stmt_update_token = oci_parse($conn, $query_update_token);
                            oci_bind_by_name($stmt_update_token, ":token_bv", $token);
                            oci_bind_by_name($stmt_update_token, ":email_bv", $email);
                            oci_execute($stmt_update_token);
                            oci_free_statement($stmt_update_token);
                        }

                        // Generate verification link
                        $verification_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/verify_email.php?email=" . urlencode($email) . "&token=" . urlencode($token);

                        // Send verification email
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
                            $mail->Subject = 'Verify Your Email Address - CleckBasket';
                            $mail->Body = "<html><body><h3>Hello " . htmlspecialchars($user_data['FIRST_NAME']) . ",</h3><p>Please click the link below to verify your email address:</p><p><a href='" . $verification_link . "'>" . $verification_link . "</a></p><p>If you did not request this, please ignore this email.</p><p>Regards,<br>The CleckBasket Team</p></body></html>";
                            $mail->AltBody = "Hello " . htmlspecialchars($user_data['FIRST_NAME']) . ",\n\nPlease copy and paste the following link into your browser to verify your email address:\n" . $verification_link . "\n\nIf you did not request this, please ignore this email.\n\nRegards,\nThe CleckBasket Team";

                            $mail->send();
                            $success_message = "A new verification email has been sent to " . htmlspecialchars($email) . ". Please check your inbox (and spam folder).";
                        } catch (Exception $e) {
                            $error_message = "Verification email could not be sent. Mailer Error: {$mail->ErrorInfo}";
                        }
                    }
                } else {
                    $error_message = "No account found with that email address.";
                }
            } else {
                $error_message = "Database error. Please try again later.";
            }
        }
        // Update rate limit
        $verify_limit['count']++;
        $verify_limit['timestamp'] = time();
        $_SESSION[$verify_limit_key] = $verify_limit;
    }
}
?>
<!-- HTML form and messages would go here -->