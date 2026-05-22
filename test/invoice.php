<?php
// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'php_logic/connect.php';

// Check if order_id is provided
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;

// If no order_id provided and user is logged in, try to get their latest order
if (!$order_id && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $user_id = $_SESSION['user_id'];
    $query_latest = "SELECT order_id FROM ORDERR 
                    WHERE fk1_user_id = :user_id 
                    AND status IN ('Pending', 'Booked', 'Delivered') 
                    ORDER BY placed_on DESC";
    $stmt_latest = oci_parse($conn, $query_latest);
    oci_bind_by_name($stmt_latest, ':user_id', $user_id);
    if (oci_execute($stmt_latest)) {
        $row = oci_fetch_assoc($stmt_latest);
        if ($row) {
            $order_id = $row['ORDER_ID'];
        }
    }
    oci_free_statement($stmt_latest);
}

// If still no order_id, redirect to home
if (!$order_id) {
    $_SESSION['error_message'] = "No order specified for invoice generation.";
    header("Location: index.php");
    exit;
}

// Get order details
$order = null;
$query_order = "SELECT o.order_id, o.total_amount, o.status, o.placed_on, 
                p.payment_id, p.method, p.payment_status, p.paid_on,
                u.user_id, u.first_name, u.last_name, u.email, u.phone,
                cs.scheduled_day, cs.scheduled_date, cs.scheduled_time
                FROM ORDERR o
                JOIN PAYMENT p ON o.fk3_payment_id = p.payment_id
                JOIN USERS u ON o.fk1_user_id = u.user_id
                JOIN COLLECTION_SLOT cs ON o.fk4_slot_id = cs.slot_id
                WHERE o.order_id = :order_id";
$stmt_order = oci_parse($conn, $query_order);
oci_bind_by_name($stmt_order, ':order_id', $order_id);
if (oci_execute($stmt_order)) {
    $order = oci_fetch_assoc($stmt_order);
}
oci_free_statement($stmt_order);

// Get order items
$order_items = [];
if ($order) {
    $query_items = "SELECT op.product_id, op.quantity, op.price_at_purchase, 
                    p.name, p.unit
                    FROM ORDER_PRODUCT op
                    JOIN PRODUCT p ON op.product_id = p.product_id
                    WHERE op.order_id = :order_id";
    $stmt_items = oci_parse($conn, $query_items);
    oci_bind_by_name($stmt_items, ':order_id', $order_id);
    if (oci_execute($stmt_items)) {
        while ($row = oci_fetch_assoc($stmt_items)) {
            $order_items[] = $row;
        }
    }
    oci_free_statement($stmt_items);
}

// If no order found, redirect to home
if (!$order) {
    $_SESSION['error_message'] = "Order not found or you don't have permission to view it.";
    header("Location: index.php");
    exit;
}

// Check if user has permission to view this invoice
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['usertype'] ?? '';
    
    // Allow if user is the order owner or an admin/trader
    if ($order['USER_ID'] != $user_id && $user_type !== 'ADMIN' && $user_type !== 'TRADER') {
        $_SESSION['error_message'] = "You don't have permission to view this invoice.";
        header("Location: index.php");
        exit;
    }
} else {
    // Not logged in
    $_SESSION['login_error'] = "Please log in to view invoices.";
    header("Location: login.php");
    exit;
}

// Format date for display
$order_date = new DateTime($order['PLACED_ON']);
$formatted_order_date = $order_date->format('F j, Y');

// Format collection date and time
$collection_date = new DateTime($order['SCHEDULED_DATE']);
$formatted_collection_date = $collection_date->format('F j, Y');
$collection_time = $order['SCHEDULED_TIME'];
$collection_time_parts = explode('-', $collection_time);
$start_time = date('g:i A', strtotime($collection_time_parts[0]));
$end_time = date('g:i A', strtotime($collection_time_parts[1]));
$formatted_collection_time = "$start_time - $end_time";

// Generate PDF invoice
require_once 'vendor/autoload.php';

// Create PDF instance
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();

// Set document information
$pdf->SetTitle('Invoice #' . $order_id);
$pdf->SetAuthor('CleckBasket');
$pdf->SetCreator('CleckBasket E-commerce');

// Add logo
$pdf->Image('assets/images/CLeckBasketLogo.jpg', 10, 10, 30);

// Set font
$pdf->SetFont('Arial', 'B', 16);

// Title
$pdf->Cell(190, 10, 'INVOICE', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(190, 10, 'Order #' . $order_id, 0, 1, 'C');
$pdf->Ln(10);

// Company and customer details
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(95, 7, 'CleckBasket', 0, 0);
$pdf->Cell(95, 7, 'BILL TO:', 0, 1);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 7, 'Cleckheaton, West Yorkshire', 0, 0);
$pdf->Cell(95, 7, $order['FIRST_NAME'] . ' ' . $order['LAST_NAME'], 0, 1);

$pdf->Cell(95, 7, 'United Kingdom', 0, 0);
$pdf->Cell(95, 7, 'Email: ' . $order['EMAIL'], 0, 1);

$pdf->Cell(95, 7, 'support@cleckbasket.com', 0, 0);
$pdf->Cell(95, 7, 'Phone: ' . $order['PHONE'], 0, 1);

$pdf->Ln(10);

// Invoice details
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(47.5, 7, 'Invoice Date', 1, 0, 'C');
$pdf->Cell(47.5, 7, 'Payment Method', 1, 0, 'C');
$pdf->Cell(47.5, 7, 'Payment Status', 1, 0, 'C');
$pdf->Cell(47.5, 7, 'Collection Slot', 1, 1, 'C');

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(47.5, 7, $formatted_order_date, 1, 0, 'C');
$pdf->Cell(47.5, 7, $order['METHOD'], 1, 0, 'C');
$pdf->Cell(47.5, 7, $order['PAYMENT_STATUS'], 1, 0, 'C');
$pdf->Cell(47.5, 7, $order['SCHEDULED_DAY'], 1, 1, 'C');

$pdf->Ln(5);

// Collection details
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(190, 7, 'Collection Details', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(190, 7, 'Date: ' . $formatted_collection_date . ' (' . $order['SCHEDULED_DAY'] . ')', 0, 1);
$pdf->Cell(190, 7, 'Time: ' . $formatted_collection_time, 0, 1);
$pdf->Cell(190, 7, 'Location: CleckBasket Store, Cleckheaton, West Yorkshire', 0, 1);

$pdf->Ln(10);

// Order items
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(80, 7, 'Product', 1, 0, 'C');
$pdf->Cell(30, 7, 'Unit Price', 1, 0, 'C');
$pdf->Cell(30, 7, 'Quantity', 1, 0, 'C');
$pdf->Cell(50, 7, 'Total', 1, 1, 'C');

$pdf->SetFont('Arial', '', 10);
$subtotal = 0;

foreach ($order_items as $item) {
    $unit_price = $item['PRICE_AT_PURCHASE'];
    $quantity = $item['QUANTITY'];
    $total = $unit_price * $quantity;
    $subtotal += $total;
    
    $product_name = $item['NAME'];
    if ($item['UNIT']) {
        $product_name .= ' / ' . $item['UNIT'];
    }
    
    $pdf->Cell(80, 7, $product_name, 1, 0);
    $pdf->Cell(30, 7, '$' . number_format($unit_price, 2), 1, 0, 'R');
    $pdf->Cell(30, 7, $quantity, 1, 0, 'C');
    $pdf->Cell(50, 7, '$' . number_format($total, 2), 1, 1, 'R');
}

// Calculate discount if any
$discount = $subtotal - $order['TOTAL_AMOUNT'];
$discount_percent = 0;
if ($discount > 0 && $subtotal > 0) {
    $discount_percent = round(($discount / $subtotal) * 100);
}

// Totals
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(140, 7, 'Subtotal', 1, 0, 'R');
$pdf->Cell(50, 7, '$' . number_format($subtotal, 2), 1, 1, 'R');

if ($discount > 0) {
    $pdf->Cell(140, 7, 'Discount (' . $discount_percent . '%)', 1, 0, 'R');
    $pdf->Cell(50, 7, '-$' . number_format($discount, 2), 1, 1, 'R');
}

$pdf->Cell(140, 7, 'Total', 1, 0, 'R');
$pdf->Cell(50, 7, '$' . number_format($order['TOTAL_AMOUNT'], 2), 1, 1, 'R');

$pdf->Ln(10);

// Thank you note
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(190, 7, 'Thank you for shopping with CleckBasket!', 0, 1, 'C');
$pdf->Cell(190, 7, 'For any questions regarding your order, please contact support@cleckbasket.com', 0, 1, 'C');

// Output PDF
$pdf->Output('I', 'Invoice_' . $order_id . '.pdf');
exit;
?>
