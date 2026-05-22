<?php
session_start();
require_once 'php_logic/connect.php';
require_once 'vendor/autoload.php'; // For PHPMailer if needed for notifications
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["user_id"])) {
    $_SESSION['login_error'] = "Please log in to update your profile.";
    if(isset($conn)) oci_close($conn);
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_details'])) {
        // Handle update of user details
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $phone_number = trim($_POST['phone_number']);
        $address_line1 = trim($_POST['address_line1']);
        $address_line2 = trim($_POST['address_line2']); // Optional
        $city = trim($_POST['city']);
        $postcode = trim($_POST['postcode']);

        // Basic validation
        if (empty($first_name) || empty($last_name)) {
            $_SESSION['profile_error'] = "First name and last name are required.";
        } elseif (!empty($phone_number) && !preg_match("/^[0-9\s\+\-()]{7,20}$/", $phone_number)) {
            $_SESSION['profile_error'] = "Invalid phone number format.";
        } else {
            $query_update_details = "UPDATE Users SET 
                                        first_name = :fname_bv, 
                                        last_name = :lname_bv, 
                                        phone_number = :phone_bv, 
                                        address_line1 = :addr1_bv, 
                                        address_line2 = :addr2_bv, 
                                        city = :city_bv, 
                                        postcode = :postcode_bv
                                    WHERE user_id = :user_id_bv";
            $stmt_update_details = oci_parse($conn, $query_update_details);

            oci_bind_by_name($stmt_update_details, ":fname_bv", $first_name);
            oci_bind_by_name($stmt_update_details, ":lname_bv", $last_name);
            oci_bind_by_name($stmt_update_details, ":phone_bv", $phone_number);
            oci_bind_by_name($stmt_update_details, ":addr1_bv", $address_line1);
            oci_bind_by_name($stmt_update_details, ":addr2_bv", $address_line2);
            oci_bind_by_name($stmt_update_details, ":city_bv", $city);
            oci_bind_by_name($stmt_update_details, ":postcode_bv", $postcode);
            oci_bind_by_name($stmt_update_details, ":user_id_bv", $user_id);

            if (oci_execute($stmt_update_details)) {
                $_SESSION['profile_success_message'] = "Your details have been updated successfully.";
                // Update session first_name if it changed, for display in header
                if ($_SESSION['first_name'] !== $first_name) {
                    $_SESSION['first_name'] = $first_name;
                }
            } else {
                $e = oci_error($stmt_update_details);
                $_SESSION['profile_error'] = "Database error updating details: " . htmlentities($e['message']);
                error_log("OCI Error in update_profile.php (update details): " . $e['message']);
            }
            oci_free_statement($stmt_update_details);
        }
    } elseif (isset($_POST['change_password'])) {
        // Handle password change
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_new_password = $_POST['confirm_new_password'];

        if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
            $_SESSION['profile_error'] = "All password fields are required.";
        } elseif (strlen($new_password) < 8 || !preg_match("/[A-Z]/", $new_password) || !preg_match("/[a-z]/", $new_password) || !preg_match("/[0-9]/", $new_password) || !preg_match("/[!@#$%^&*()_+\-=\[\]{};':\"\\|,.<>\/?~`]|\[|\]/", $new_password)) {
            $_SESSION['profile_error'] = "New password must be at least 8 characters long and include uppercase, lowercase, number, and special character.";
        } elseif ($new_password !== $confirm_new_password) {
            $_SESSION['profile_error'] = "New passwords do not match.";
        } else {
            // Fetch current password hash from DB
            $query_fetch_pass = "SELECT password_hash FROM Users WHERE user_id = :user_id_bv";
            $stmt_fetch_pass = oci_parse($conn, $query_fetch_pass);
            oci_bind_by_name($stmt_fetch_pass, ":user_id_bv", $user_id);
            
            if (oci_execute($stmt_fetch_pass)) {
                $user_pass_data = oci_fetch_assoc($stmt_fetch_pass);
                if ($user_pass_data && password_verify($current_password, $user_pass_data['PASSWORD_HASH'])) {
                    // Current password is correct, proceed to update
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $query_update_pass = "UPDATE Users SET password_hash = :new_pass_bv WHERE user_id = :user_id_bv";
                    $stmt_update_pass = oci_parse($conn, $query_update_pass);
                    oci_bind_by_name($stmt_update_pass, ":new_pass_bv", $new_password_hash);
                    oci_bind_by_name($stmt_update_pass, ":user_id_bv", $user_id);

                    if (oci_execute($stmt_update_pass)) {
                        $_SESSION['profile_success_message'] = "Password changed successfully.";
                        // Optionally, send an email notification about password change here
                    } else {
                        $e = oci_error($stmt_update_pass);
                        $_SESSION['profile_error'] = "Database error changing password: " . htmlentities($e['message']);
                        error_log("OCI Error in update_profile.php (update password): " . $e['message']);
                    }
                    oci_free_statement($stmt_update_pass);
                } else {
                    $_SESSION['profile_error'] = "Incorrect current password.";
                }
            } else {
                $e = oci_error($stmt_fetch_pass);
                $_SESSION['profile_error'] = "Database error verifying current password: " . htmlentities($e['message']);
                error_log("OCI Error in update_profile.php (fetch password): " . $e['message']);
            }
            oci_free_statement($stmt_fetch_pass);
        }
    } else {
        $_SESSION['profile_error'] = "Invalid profile update request.";
    }
} else {
    // Not a POST request
    $_SESSION['profile_error'] = "Invalid request method.";
}

if(isset($conn)) oci_close($conn);
header("location: profile.php");
exit;
?>
