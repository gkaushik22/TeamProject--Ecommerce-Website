<?php
session_start();
require_once 'php_logic/connect.php'; // Ensure DB connection
ini_set('default_charset', 'UTF-8');

// Security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Redirect to login if no 2FA session data
if (!isset($_SESSION["login_email_attempt"]) || !isset($_SESSION["login_verification_code"]) || !isset($_SESSION["login_user_id_attempt"])) {
    $_SESSION["login_error"] = "Verification process not initiated or session expired. Please log in again.";
    unset($_SESSION["login_verification_code"], $_SESSION["login_email_attempt"], $_SESSION["login_first_name_attempt"], $_SESSION["login_user_type_attempt"], $_SESSION["login_code_timestamp"], $_SESSION["login_user_id_attempt"]);
    oci_close($conn);
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["login_code"])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['login_code_error'] = "Invalid CSRF token.";
        oci_close($conn);
        header("Location: verify_login_code.php");
        exit;
    }

    $entered_code = trim($_POST["login_code"]);
    $stored_code = $_SESSION["login_verification_code"];
    $user_id = $_SESSION["login_user_id_attempt"];
    $email = $_SESSION["login_email_attempt"];
    $first_name = $_SESSION["login_first_name_attempt"];
    $user_type = $_SESSION["login_user_type_attempt"];
    $code_timestamp = $_SESSION["login_code_timestamp"] ?? 0;

    // Check code expiry (5 minutes)
    $code_valid_duration = 300;
    if (time() - $code_timestamp > $code_valid_duration) {
        $_SESSION["login_code_error"] = "Verification code has expired. Please try logging in again to get a new code.";
        unset($_SESSION["login_verification_code"], $_SESSION["login_email_attempt"], $_SESSION["login_first_name_attempt"], $_SESSION["login_user_type_attempt"], $_SESSION["login_code_timestamp"], $_SESSION["login_user_id_attempt"]);
        oci_close($conn);
        header("Location: login.php");
        exit;
    }

    if (empty($entered_code)) {
        $_SESSION["login_code_error"] = "Please enter the verification code.";
        oci_close($conn);
        header("Location: verify_login_code.php");
        exit;
    }

    if ($entered_code == $stored_code) {
        // Code is correct, complete login
        $_SESSION["loggedin"] = true;
        $_SESSION["user_id"] = $user_id;
        $_SESSION["email"] = $email;
        $_SESSION["first_name"] = $first_name;
        $_SESSION["usertype"] = $user_type; // Changed from user_type to usertype

        // Update last_login_date
        $query_update_login_time = "UPDATE Users SET last_login_date = CURRENT_TIMESTAMP WHERE user_id = :user_id_bv";
        $stmt_update_login_time = oci_parse($conn, $query_update_login_time);
        oci_bind_by_name($stmt_update_login_time, ":user_id_bv", $user_id);
        if (!oci_execute($stmt_update_login_time)) {
            $e = oci_error($stmt_update_login_time);
            error_log("OCI Error in process_login_code.php (update last_login_date): " . $e["message"]);
        }
        oci_free_statement($stmt_update_login_time);

        // Clear login attempt session variables
        unset($_SESSION["login_verification_code"], $_SESSION["login_email_attempt"], $_SESSION["login_first_name_attempt"], $_SESSION["login_user_type_attempt"], $_SESSION["login_code_timestamp"], $_SESSION["login гордичattempt"], $_SESSION["login_code_error"], $_SESSION["info_message"], $_SESSION["form_data"], $_SESSION["csrf_token"]);

        $_SESSION["login_success_message"] = "Login successful! Welcome back, " . htmlspecialchars($first_name) . ".";

        // Redirect based on user type
        if ($user_type === 'TRADER') {
            oci_close($conn);
            header("Location: trader_manage_products.php");
            exit;
        } else {
            oci_close($conn);
            header("Location: index.php");
            exit;
        }
    } else {
        $_SESSION["login_code_error"] = "Invalid verification code. Please try again.";
        oci_close($conn);
        header("Location: verify_login_code.php");
        exit;
    }
} else {
    $_SESSION["login_code_error"] = "Invalid request. Please enter your code.";
    oci_close($conn);
    header("Location: verify_login_code.php");
    exit;
}
