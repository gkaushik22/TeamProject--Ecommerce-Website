<?php
// Function to send an HTML email
// Parameters:
// $to_email: Recipient's email address (string or comma-separated string for multiple recipients)
// $subject_line: Subject of the email (string)
// $html_message: The HTML content of the email (string)
// $from_email: Sender's email address (string, e.g., 'noreply@yourdomain.com')
// $from_name: Sender's name (string, optional, e.g., 'CleckBasket Team')
// $cc_email: CC recipient's email address (string or comma-separated string, optional)
// $bcc_email: BCC recipient's email address (string or comma-separated string, optional)

function send_custom_email($to_email, $subject_line, $html_message, $from_email, $from_name = '', $cc_email = '', $bcc_email = '') {
    // Basic validation
    if (empty($to_email) || empty($subject_line) || empty($html_message) || empty($from_email)) {
        // Log error or return false, as per your error handling strategy
        // error_log("Email sending failed: Missing required parameters.");
        return false;
    }

    // To send HTML mail, the Content-type header must be set
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

    // From header
    if (!empty($from_name)) {
        $headers .= 'From: ' . $from_name . ' <' . $from_email . '>' . "\r\n";
    } else {
        $headers .= 'From: <' . $from_email . '>' . "\r\n";
    }

    // CC header
    if (!empty($cc_email)) {
        $headers .= 'Cc: ' . $cc_email . "\r\n";
    }

    // BCC header
    if (!empty($bcc_email)) {
        $headers .= 'Bcc: ' . $bcc_email . "\r\n";
    }

    // Attempt to send the email
    if (mail($to_email, $subject_line, $html_message, $headers)) {
        return true;
    } else {
        // Log error or handle failure
        // error_log("Email sending failed to: " . $to_email . " with subject: " . $subject_line);
        return false;
    }
}

/*
// Example Usage:

$recipient = "test_recipient@example.com";
$email_subject = "Welcome to CleckBasket!";
$message_body = "
<html>
<head>
<title>Welcome!</title>
</head>
<body>
<h1>Thank you for registering!</h1>
<p>This is a confirmation email for your registration at CleckBasket.</p>
</body>
</html>
";
$sender_email = "noreply@cleckbasket.com";
$sender_name = "CleckBasket Team";

if (send_custom_email($recipient, $email_subject, $message_body, $sender_email, $sender_name)) {
    echo "Email sent successfully!";
} else {
    echo "Email sending failed.";
}

*/
?>
