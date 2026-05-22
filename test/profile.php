<?php
session_start();
require_once 'php_logic/connect.php';
require_once 'includes/header.php';

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    $_SESSION['login_error'] = "Please log in to access your profile.";
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$first_name = '';
$last_name = '';
$email = '';
$phone_number = '';
$address_line1 = '';
$address_line2 = '';
$city = '';
$postcode = '';

// Fetch user details from the database
$query_user_details = "SELECT first_name, last_name, email, phone_number, address_line1, address_line2, city, postcode FROM Users WHERE user_id = :user_id_bv";
$stmt_user_details = oci_parse($conn, $query_user_details);
oci_bind_by_name($stmt_user_details, ":user_id_bv", $user_id);

if (oci_execute($stmt_user_details)) {
    $user = oci_fetch_assoc($stmt_user_details);
    if ($user) {
        $first_name = $user['FIRST_NAME'];
        $last_name = $user['LAST_NAME'];
        $email = $user['EMAIL'];
        $phone_number = $user['PHONE_NUMBER'];
        $address_line1 = $user['ADDRESS_LINE1'];
        $address_line2 = $user['ADDRESS_LINE2'];
        $city = $user['CITY'];
        $postcode = $user['POSTCODE'];
    } else {
        // Should not happen if user_id in session is valid
        $_SESSION['profile_error'] = "Could not retrieve user details.";
    }
} else {
    $e = oci_error($stmt_user_details);
    $_SESSION['profile_error'] = "Database error fetching profile: " . htmlentities($e['message']);
    error_log("OCI Error in profile.php (fetch user details): " . $e['message']);
}
oci_free_statement($stmt_user_details);

// Retrieve and clear messages
$profile_success_message = $_SESSION['profile_success_message'] ?? null;
$profile_error_message = $_SESSION['profile_error'] ?? null;
if ($profile_success_message) unset($_SESSION['profile_success_message']);
if ($profile_error_message) unset($_SESSION['profile_error']);

?>

<div class="container mx-auto px-6 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Your Profile</h1>

    <?php if ($profile_success_message): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p class="font-bold">Success</p>
            <p><?php echo htmlspecialchars($profile_success_message); ?></p>
        </div>
    <?php endif; ?>
    <?php if ($profile_error_message): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">Error</p>
            <p><?php echo htmlspecialchars($profile_error_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow-md rounded-lg p-8">
        <h2 class="text-2xl font-semibold text-gray-700 mb-6">Account Details</h2>
        <form action="update_profile.php" method="POST">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="first_name" class="block text-gray-700 text-sm font-bold mb-2">First Name</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div>
                    <label for="last_name" class="block text-gray-700 text-sm font-bold mb-2">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
            </div>
            <div class="mb-6">
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email Address (cannot be changed)</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-gray-100" readonly>
            </div>
            <div class="mb-6">
                <label for="phone_number" class="block text-gray-700 text-sm font-bold mb-2">Phone Number</label>
                <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-6">
                <label for="address_line1" class="block text-gray-700 text-sm font-bold mb-2">Address Line 1</label>
                <input type="text" id="address_line1" name="address_line1" value="<?php echo htmlspecialchars($address_line1); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-6">
                <label for="address_line2" class="block text-gray-700 text-sm font-bold mb-2">Address Line 2 (Optional)</label>
                <input type="text" id="address_line2" name="address_line2" value="<?php echo htmlspecialchars($address_line2); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="city" class="block text-gray-700 text-sm font-bold mb-2">City</label>
                    <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($city); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label for="postcode" class="block text-gray-700 text-sm font-bold mb-2">Postcode</label>
                    <input type="text" id="postcode" name="postcode" value="<?php echo htmlspecialchars($postcode); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" name="update_details" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Update Details
                </button>
            </div>
        </form>

        <hr class="my-8">

        <h2 class="text-2xl font-semibold text-gray-700 mb-6">Change Password</h2>
        <form action="update_profile.php" method="POST">
            <div class="mb-4">
                <label for="current_password" class="block text-gray-700 text-sm font-bold mb-2">Current Password</label>
                <input type="password" id="current_password" name="current_password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="mb-4">
                <label for="new_password" class="block text-gray-700 text-sm font-bold mb-2">New Password</label>
                <input type="password" id="new_password" name="new_password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                <p class="text-xs text-gray-500 mt-1">Must be at least 8 characters, with uppercase, lowercase, number, and special character.</p>
            </div>
            <div class="mb-6">
                <label for="confirm_new_password" class="block text-gray-700 text-sm font-bold mb-2">Confirm New Password</label>
                <input type="password" id="confirm_new_password" name="confirm_new_password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="flex justify-end">
                <button type="submit" name="change_password" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Change Password
                </button>
            </div>
        </form>
    </div>
</div>

<?php 
if(isset($conn)) oci_close($conn);
require_once 'includes/footer.php'; 
?>

