<?php
session_start();
require_once 'php_logic/connect.php'; // Connect to Oracle DB
require_once 'vendor/autoload.php'; // Include PHPMailer
require_once 'config.php'; // Include SMTP config
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Set UTF-8 encoding
ini_set('default_charset', 'UTF-8');

// Security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Ensure session is active
if (session_status() !== PHP_SESSION_ACTIVE) {
    error_log("Session not active in register_process.php");
    $_SESSION['register_error'] = "Session error. Please try again.";
    header("Location: register.php");
    exit;
}

// Clear previous messages
if (isset($_SESSION['register_error'])) unset($_SESSION['register_error']);
if (isset($_SESSION['register_success_message'])) unset($_SESSION['register_success_message']);
if (isset($_SESSION['form_data'])) unset($_SESSION['form_data']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF token validation failed. POST token: " . ($_POST['csrf_token'] ?? 'none') . ", Session token: " . ($_SESSION['csrf_token'] ?? 'none'));
        $_SESSION['register_error'] = "Invalid CSRF token.";
        header("Location: register.php");
        exit;
    }

    $first_name = trim($_POST["first_name"] ?? '');
    $last_name = trim($_POST["last_name"] ?? '');
    $gender = trim($_POST["gender"] ?? '');
    $contact = trim($_POST["contact"] ?? '');
    $address = trim($_POST["address"] ?? '');
    $usertype = trim($_POST["usertype"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $password = $_POST["password"] ?? ''; // Plain password
    $confirm_password = $_POST["confirm_password"] ?? '';
    $license = trim($_POST["license"] ?? '');
    $shop_name = trim($_POST["shop_name"] ?? '');
    $shop_type = trim($_POST["shop_type"] ?? '');

    // Store form data for repopulation
    $_SESSION['form_data'] = $_POST;

    // Validation
    if (empty($first_name) || empty($last_name) || empty($gender) || empty($contact) || empty($address) || empty($usertype) || empty($email) || empty($password) || empty($confirm_password)) {
        $_SESSION['register_error'] = "All required fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['register_error'] = "Invalid email format.";
    } elseif (!preg_match("/^[0-9]{10}$/", $contact)) {
        $_SESSION['register_error'] = "Contact number must be exactly 10 digits.";
    } elseif (strlen($password) < 8 || !preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password) || !preg_match("/[0-9]/", $password) || !preg_match("/[!@#$%^&*()_+\-=\[\]{};':\"\\|,.<>\/?~`]/", $password)) {
        $_SESSION['register_error'] = "Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character.";
    } elseif ($password !== $confirm_password) {
        $_SESSION['register_error'] = "Passwords do not match.";
    } elseif ($usertype === 'TRADER' && (empty($license) || empty($shop_name) || empty($shop_type))) {
        $_SESSION['register_error'] = "License number, shop name, and shop type are required for traders.";
    } else {
        // Check database connection
        if (!$conn) {
            $_SESSION['register_error'] = "Database connection failed.";
            error_log("Database connection failed in register_process.php");
            header("Location: register.php");
            exit;
        }

        // Check if email already exists
        $query_check_email = "SELECT COUNT(*) AS email_count FROM USERS WHERE email = :email_bv";
        $stmt_check_email = oci_parse($conn, $query_check_email);
        if (!$stmt_check_email) {
            $e = oci_error($conn);
            $_SESSION['register_error'] = "Failed to parse email check query: " . htmlentities($e['message']);
            error_log("Email check parse error: " . json_encode($e));
            header("Location: register.php");
            exit;
        }
        oci_bind_by_name($stmt_check_email, ":email_bv", $email);
        if (!oci_execute($stmt_check_email)) {
            $e = oci_error($stmt_check_email);
            $_SESSION['register_error'] = "Database error checking email: " . htmlentities($e['message']);
            error_log("Email check execute error: " . json_encode($e));
            oci_free_statement($stmt_check_email);
            header("Location: register.php");
            exit;
        }
        $row_email_count = oci_fetch_assoc($stmt_check_email);
        oci_free_statement($stmt_check_email);

        if ($row_email_count && $row_email_count['EMAIL_COUNT'] > 0) {
            $_SESSION['register_error'] = "An account with this email already exists.";
        } else {
            // Check if contact already exists
            $query_check_contact = "SELECT COUNT(*) AS contact_count FROM USERS WHERE contact = :contact_bv";
            $stmt_check_contact = oci_parse($conn, $query_check_contact);
            if (!$stmt_check_contact) {
                $e = oci_error($conn);
                $_SESSION['register_error'] = "Failed to parse contact check query: " . htmlentities($e['message']);
                error_log("Contact check parse error: " . json_encode($e));
                header("Location: register.php");
                exit;
            }
            oci_bind_by_name($stmt_check_contact, ":contact_bv", $contact);
            if (!oci_execute($stmt_check_contact)) {
                $e = oci_error($stmt_check_contact);
                $_SESSION['register_error'] = "Database error checking contact: " . htmlentities($e['message']);
                error_log("Contact check execute error: " . json_encode($e));
                oci_free_statement($stmt_check_contact);
                header("Location: register.php");
                exit;
            }
            $row_contact_count = oci_fetch_assoc($stmt_check_contact);
            oci_free_statement($stmt_check_contact);

            if ($row_contact_count && $row_contact_count['CONTACT_COUNT'] > 0) {
                $_SESSION['register_error'] = "An account with this contact number already exists.";
            } elseif ($usertype === 'TRADER') {
                // Check if license already exists
                $query_check_license = "SELECT COUNT(*) AS count FROM TRADER WHERE license = :license";
                $stmt_check_license = oci_parse($conn, $query_check_license);
                if (!$stmt_check_license) {
                    $e = oci_error($conn);
                    $_SESSION['register_error'] = "Failed to parse license check query: " . htmlentities($e['message']);
                    error_log("License check parse error: " . json_encode($e));
                    header("Location: register.php");
                    exit;
                }
                oci_bind_by_name($stmt_check_license, ":license", $license);
                if (!oci_execute($stmt_check_license)) {
                    $e = oci_error($stmt_check_license);
                    $_SESSION['register_error'] = "Database error checking license: " . htmlentities($e['message']);
                    error_log("License check execute error: " . json_encode($e));
                    oci_free_statement($stmt_check_license);
                    header("Location: register.php");
                    exit;
                }
                $row_license = oci_fetch_assoc($stmt_check_license);
                oci_free_statement($stmt_check_license);

                if ($row_license && $row_license['COUNT'] > 0) {
                    $_SESSION['register_error'] = "License already exists. Please use a different license.";
                } else {
                    // Check if shop name already exists
                    $query_check_shop = "SELECT COUNT(*) AS count FROM SHOP WHERE name = :shop_name";
                    $stmt_check_shop = oci_parse($conn, $query_check_shop);
                    if (!$stmt_check_shop) {
                        $e = oci_error($conn);
                        $_SESSION['register_error'] = "Failed to parse shop name check query: " . htmlentities($e['message']);
                        error_log("Shop name check parse error: " . json_encode($e));
                        header("Location: register.php");
                        exit;
                    }
                    oci_bind_by_name($stmt_check_shop, ":shop_name", $shop_name);
                    if (!oci_execute($stmt_check_shop)) {
                        $e = oci_error($stmt_check_shop);
                        $_SESSION['register_error'] = "Database error checking shop name: " . htmlentities($e['message']);
                        error_log("Shop name check execute error: " . json_encode($e));
                        oci_free_statement($stmt_check_shop);
                        header("Location: register.php");
                        exit;
                    }
                    $row_shop = oci_fetch_assoc($stmt_check_shop);
                    oci_free_statement($stmt_check_shop);

                    if ($row_shop && $row_shop['COUNT'] > 0) {
                        $_SESSION['register_error'] = "Shop name already exists. Please use a different name.";
                    }
                }
            }

            if (!isset($_SESSION['register_error'])) {
                // Generate email verification token
                $email_verification_token = bin2hex(random_bytes(32));

                // Hash password with PHP
                $bcrypt_hash = password_hash($password, PASSWORD_DEFAULT);
                error_log("Generated bcrypt hash: " . substr($bcrypt_hash, 0, 20) . "...");

                // Use a placeholder password to satisfy TRIG_HASH_PASSWORD
                $placeholder_password = bin2hex(random_bytes(16)); // Random 32-char hex string

                try {
                    // Insert user into USERS with placeholder password
                    $query_insert_user = "INSERT INTO USERS (first_name, last_name, gender, contact, address, usertype, email, password, email_verification_token, email_verified)
                                        VALUES (:first_name, :last_name, :gender, :contact, :address, :usertype, :email, :password, :email_verification_token, 'N')
                                        RETURNING user_id INTO :user_id";
                    $stmt_insert_user = oci_parse($conn, $query_insert_user);
                    if (!$stmt_insert_user) {
                        $e = oci_error($conn);
                        throw new Exception("Failed to parse user insert query: " . htmlentities($e['message']));
                    }

                    $user_id = null;
                    oci_bind_by_name($stmt_insert_user, ":first_name", $first_name);
                    oci_bind_by_name($stmt_insert_user, ":last_name", $last_name);
                    oci_bind_by_name($stmt_insert_user, ":gender", $gender);
                    oci_bind_by_name($stmt_insert_user, ":contact", $contact);
                    oci_bind_by_name($stmt_insert_user, ":address", $address);
                    oci_bind_by_name($stmt_insert_user, ":usertype", $usertype);
                    oci_bind_by_name($stmt_insert_user, ":email", $email);
                    oci_bind_by_name($stmt_insert_user, ":password", $placeholder_password);
                    oci_bind_by_name($stmt_insert_user, ":email_verification_token", $email_verification_token);
                    oci_bind_by_name($stmt_insert_user, ":user_id", $user_id, 10, SQLT_CHR);

                    error_log("Attempting to insert user with email: $email, usertype: $usertype");
                    if (!oci_execute($stmt_insert_user)) {
                        $e = oci_error($stmt_insert_user);
                        error_log("User insert error: " . json_encode($e));
                        throw new Exception("Database error inserting user: " . htmlentities($e['message']));
                    }
                    oci_free_statement($stmt_insert_user);

                    // Update USERS.password with bcrypt hash
                    $query_update_password = "UPDATE USERS SET password = :bcrypt_hash WHERE user_id = :user_id";
                    $stmt_update_password = oci_parse($conn, $query_update_password);
                    if (!$stmt_update_password) {
                        $e = oci_error($conn);
                        throw new Exception("Failed to parse password update query: " . htmlentities($e['message']));
                    }
                    oci_bind_by_name($stmt_update_password, ":bcrypt_hash", $bcrypt_hash);
                    oci_bind_by_name($stmt_update_password, ":user_id", $user_id);
                    error_log("Attempting to update password for user_id: $user_id with bcrypt hash");
                    if (!oci_execute($stmt_update_password)) {
                        $e = oci_error($stmt_update_password);
                        error_log("Password update error: " . json_encode($e));
                        throw new Exception("Database error updating password: " . htmlentities($e['message']));
                    }
                    oci_free_statement($stmt_update_password);

                    // For TRADER, update TRADER with provided license
                    if ($usertype === 'TRADER') {
                        $query_update_trader = "UPDATE TRADER SET license = :license WHERE user_id = :user_id";
                        $stmt_update_trader = oci_parse($conn, $query_update_trader);
                        if (!$stmt_update_trader) {
                            $e = oci_error($conn);
                            throw new Exception("Failed to parse trader update query: " . htmlentities($e['message']));
                        }
                        oci_bind_by_name($stmt_update_trader, ":license", $license);
                        oci_bind_by_name($stmt_update_trader, ":user_id", $user_id);
                        error_log("Attempting to update trader with user_id: $user_id, license: $license");
                        if (!oci_execute($stmt_update_trader)) {
                            $e = oci_error($stmt_update_trader);
                            error_log("Trader update error: " . json_encode($e));
                            throw new Exception("Database error updating trader: " . htmlentities($e['message']));
                        }
                        oci_free_statement($stmt_update_trader);

                        // Insert into SHOP
                        $current_date = date('Y-m-d');
                        $approval_status = 'Pending';
                        $query_insert_shop = "INSERT INTO SHOP (name, type, registered, fk1_user_id, approval_status)
                                            VALUES (:name, :type, TO_DATE(:registered, 'YYYY-MM-DD'), :user_id, :approval_status)";
                        $stmt_insert_shop = oci_parse($conn, $query_insert_shop);
                        if (!$stmt_insert_shop) {
                            $e = oci_error($conn);
                            throw new Exception("Failed to parse shop insert query: " . htmlentities($e['message']));
                        }
                        oci_bind_by_name($stmt_insert_shop, ":name", $shop_name);
                        oci_bind_by_name($stmt_insert_shop, ":type", $shop_type);
                        oci_bind_by_name($stmt_insert_shop, ":registered", $current_date);
                        oci_bind_by_name($stmt_insert_shop, ":user_id", $user_id);
                        oci_bind_by_name($stmt_insert_shop, ":approval_status", $approval_status);

                        error_log("Attempting to insert shop with name: $shop_name, user_id: $user_id");
                        if (!oci_execute($stmt_insert_shop)) {
                            $e = oci_error($stmt_insert_shop);
                            error_log("Shop insert error: " . json_encode($e));
                            throw new Exception("Database error inserting shop: " . htmlentities($e['message']));
                        }
                        oci_free_statement($stmt_insert_shop);
                    }

                    // Clear form data and CSRF token on success
                    if (isset($_SESSION['form_data'])) unset($_SESSION['form_data']);
                    unset($_SESSION['csrf_token']);

                    // Generate verification link
                    $verification_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/verify_email.php?email=" . urlencode($email) . "&token=" . urlencode($email_verification_token);

                    // Send verification email
                    $email_sent = false;
                    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
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
                            $mail->addAddress($email, $first_name . ' ' . $last_name);

                            $mail->isHTML(true);
                            $mail->Subject = 'Verify Your Email Address - CleckBasket';
                            $mail->Body = "<html><body><h3>Hello " . htmlspecialchars($first_name) . ",</h3><p>Thank you for registering at CleckBasket. Please click the link below to verify your email address:</p><p><a href='" . $verification_link . "'>" . $verification_link . "</a></p><p>If you did not request this, please ignore this email.</p><p>Regards,<br>The CleckBasket Team</p></body></html>";
                            $mail->AltBody = "Hello " . htmlspecialchars($first_name) . ",\n\nThank you for registering at CleckBasket. Please copy and paste the following link into your browser to verify your email address:\n" . $verification_link . "\n\nIf you did not request this, please ignore this email.\n\nRegards,\nThe CleckBasket Team";

                            error_log("Attempting to send verification email to: $email with link: $verification_link");
                            $mail->send();
                            $email_sent = true;
                            $_SESSION['register_success_message'] = "Registration successful! A verification email has been sent to " . htmlspecialchars($email) . ". Please check your inbox (and spam folder). You can now log in once verified.";
                        } catch (Exception $e) {
                            error_log("PHPMailer Error: " . $mail->ErrorInfo);
                            $_SESSION['register_success_message'] = "Registration successful! We couldn't send the verification email at this time. Please contact support or try to verify later. Verification Link: " . $verification_link;
                        }
                    } else {
                        $_SESSION['register_success_message'] = "Registration successful (PHPMailer not configured). Verification Link: " . $verification_link;
                    }
                    header("Location: login.php");
                    exit;
                } catch (Exception $e) {
                    $_SESSION['register_error'] = $e->getMessage();
                    error_log("Registration error: " . $e->getMessage());
                    header("Location: register.php");
                    exit;
                }
            }
        }
    }
    header("Location: register.php");
    exit;
}
header("Location: register.php");
exit;
