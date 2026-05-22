<?php




$db_user = 'CleckBasket1';


$db_pass = 'CleckBasket1';

$db_conn_str = '//localhost/XE';


$conn = @oci_connect($db_user, $db_pass, $db_conn_str);
// Check if the connection was successful
if (!$conn) {
    $e = oci_error();


    $error_message = "Oracle Database Connection Failed!\n";
    $error_message .= "-------------------------------------\n";
    $error_message .= "Attempted Username: " . htmlentities($db_user) . "\n";
    $error_message .= "Attempted Connection String: " . htmlentities($db_conn_str) . "\n";
    $error_message .= "-------------------------------------\n";
    if ($e) {
        $error_message .= "Oracle Error Code: " . htmlentities($e['code']) . "\n";
        $error_message .= "Oracle Error Message: " . htmlentities($e['message']) . "\n";
        if (isset($e['offset'])) {
            $error_message .= "Error Position: " . htmlentities($e['offset']) . "\n";
        }
        if (isset($e['sqltext'])) {
            $error_message .= "Relevant SQL Text: " . htmlentities($e['sqltext']) . "\n";
        }
    } else {
        $error_message .= "An unknown error occurred while trying to connect to Oracle. \n";
        $error_message .= "Please ensure the OCI8 PHP extension is installed, enabled, and configured correctly. \n";
        $error_message .= "Also, verify that the Oracle Instant Client libraries are accessible by PHP. \n";
    }
    $error_message .= "-------------------------------------\n";
    $error_message .= "Troubleshooting Tips (Please review these carefully):\n";
    $error_message .= "1. VERIFY CREDENTIALS: Double-check that `$db_user`, `$db_pass`, and `$db_conn_str` in `php_logic/connect.php` are correct for YOUR Oracle database.
";
    $error_message .= "2. ORACLE SERVER STATUS: Ensure your Oracle database server is running and accessible from the machine where this PHP script is executed.
";
    $error_message .= "3. OCI8 PHP EXTENSION: Confirm the OCI8 extension is enabled in your `php.ini` file (e.g., `extension=oci8_12c.so` or `extension=php_oci8_12c.dll`). Restart your web server/PHP-FPM after changes.
";
    $error_message .= "4. ORACLE INSTANT CLIENT: Make sure Oracle Instant Client (or a full Oracle Client) is installed AND its libraries directory is in your system's PATH (Windows) or LD_LIBRARY_PATH (Linux/macOS). The Instant Client version should be compatible with your PHP OCI8 extension and Oracle database version.
";
    $error_message .= "5. ORACLE LISTENER: Check the Oracle listener status on the database server (e.g., `lsnrctl status` on the server). The listener must be running and configured for the service name or SID you are trying to connect to.
";
    $error_message .= "6. TNSNAMES.ORA: If using a TNS alias for `$db_conn_str`, ensure your `tnsnames.ora` file is correctly configured and accessible (check `TNS_ADMIN` environment variable).
";
    $error_message .= "7. FIREWALL: Check for any firewalls (local or network) that might be blocking the connection to the Oracle database port (default is 1521).
";

    // Displaying detailed error for development purposes
    if (php_sapi_name() !== 'cli') {
        echo "<pre style='color:red; background-color:#fdd; border:1px solid red; padding:10px;'>" . htmlspecialchars($error_message) . "</pre>";
    } else {
        echo $error_message;
    }

    exit;
}
