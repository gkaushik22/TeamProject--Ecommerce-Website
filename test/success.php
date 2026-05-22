<?php
session_start();
require_once 'php_logic/connect.php';

if (isset($_SESSION['order_id'])) {
    $order_id = $_SESSION['order_id'];

    // Update payment status to 'Paid'
    $query_payment = "UPDATE PAYMENT SET payment_status = 'Paid', paid_on = SYSDATE WHERE fk1_order_id = :order_id";
    $stmt_payment = oci_parse($conn, $query_payment);
    oci_bind_by_name($stmt_payment, ':order_id', $order_id);
    if (oci_execute($stmt_payment)) {
        oci_commit($conn);
        $success_message = "Payment successful. Order has been booked.";
    } else {
        $error = oci_error($stmt_payment);
        $error_message = "Error updating payment: " . htmlspecialchars($error['message']);
        oci_rollback($conn);
    }
    oci_free_statement($stmt_payment);

    unset($_SESSION['order_id']);
} else {
    $error_message = "No order found to process payment.";
}

oci_close($conn);
?>

<!DOCTYPE html>
<html>

<head>
    <title>Payment Success</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mx-auto px-6 py-8">
        <?php if (isset($success_message)): ?>
            <div class="success p-4 rounded-md mb-6">
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php elseif (isset($error_message)): ?>
            <div class="error p-4 rounded-md mb-6">
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>
        <a href="index.php" class="text-orange-500 hover:text-orange-700 underline">Return to Home</a>
    </div>
</body>

</html>