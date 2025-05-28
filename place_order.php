<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST['place_order'])) {
    redirect('checkout.php');
}
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    set_message('Invalid security token. Please try again.', 'error');
    redirect('checkout.php');
}

if (!isset($mysqli) || !$mysqli instanceof mysqli || $mysqli->connect_error) {
    error_log("Place Order Error: Invalid or missing DB connection object (\$mysqli).");
    set_message("A critical database error occurred. Please try again later.", "error");
    redirect('checkout.php');
}

$is_guest = !isLoggedIn();
$user_id = !$is_guest ? $_SESSION['user_id'] : null;
$session_id = session_id();

$cart_items = [];
$cart_subtotal = 0;

$sql_cart = "SELECT c.cart_id, c.quantity, p.product_id, p.name, p.price, p.stock
             FROM cart c
             JOIN products p ON c.product_id = p.product_id ";
if ($is_guest) {
    $sql_cart .= " WHERE c.session_id = ? AND c.user_id IS NULL";
    $stmt_cart = $mysqli->prepare($sql_cart);
    if ($stmt_cart) $stmt_cart->bind_param("s", $session_id);
} else {
    $sql_cart .= " WHERE c.user_id = ?";
    $stmt_cart = $mysqli->prepare($sql_cart);
    if ($stmt_cart) $stmt_cart->bind_param("i", $user_id);
}

if (!$stmt_cart) {
    error_log("Place order - cart fetch prepare error: " . $mysqli->error);
    set_message("Error preparing cart details.", "error");
    redirect('checkout.php');
}

if ($stmt_cart->execute()) {
    $result_cart = $stmt_cart->get_result();
    if ($result_cart->num_rows == 0) {
        set_message("Your cart became empty before placing the order.", "warning");
        redirect('cart.php');
    }
    while ($row = $result_cart->fetch_assoc()) {
        if ($row['stock'] < $row['quantity']) {
             set_message("Sorry, item '" . htmlspecialchars($row['name']) . "' has insufficient stock ({$row['stock']} available). Please update your cart.", "error");
             redirect('cart.php');
        }
        $cart_items[] = $row;
        $cart_subtotal += $row['price'] * $row['quantity'];
    }
    $result_cart->free();
    $stmt_cart->close();
} else {
    set_message("Error retrieving cart details for order placement.", "error");
    error_log("Place order - cart fetch execute error: " . $stmt_cart->error);
    redirect('checkout.php');
}

$errors = [];
$form_data = $_POST;

$shipping_address = sanitize_input($_POST['shipping_address'] ?? '');
$phone_number = sanitize_input($_POST['phone_number'] ?? '');
$payment_method = sanitize_input($_POST['payment_method'] ?? '');

$shipping_fee = $_POST['shipping_fee'] ?? 0;
if (!is_numeric($shipping_fee) || $shipping_fee < 0) {
    $errors['shipping_fee'] = "Invalid shipping fee provided.";
    error_log("Place Order Error: Invalid shipping_fee received: " . print_r($_POST['shipping_fee'], true));
    $shipping_fee = 0;
} else {
    $shipping_fee = (float)$shipping_fee;
}

$grand_total = $cart_subtotal + $shipping_fee;

$guest_name = null;
$guest_email = null;
if ($is_guest) {
    $guest_name = sanitize_input($_POST['guest_name'] ?? '');
    $guest_email = sanitize_input($_POST['guest_email'] ?? '');
    if (empty($guest_name)) { $errors['guest_name'] = "Full Name is required."; }
    if (empty($guest_email)) { $errors['guest_email'] = "Email is required."; }
    elseif (!filter_var($guest_email, FILTER_VALIDATE_EMAIL)) { $errors['guest_email'] = "Invalid email format."; }
}

if (empty($shipping_address)) { $errors['shipping_address'] = "Shipping Address is required."; }
if (empty($phone_number)) { $errors['phone_number'] = "Phone Number is required."; }
elseif (!preg_match('/^(09|\+639)\d{9}$/', $phone_number)) { $errors['phone_number'] = "Invalid Philippine phone format (e.g., 09171234567)."; }

$allowed_payment_methods = ['GCash', 'COD'];
if (empty($payment_method) || !in_array($payment_method, $allowed_payment_methods)) {
    $errors['payment_method'] = "Please select a valid payment method.";
}

if (!empty($errors)) {
    $_SESSION['checkout_errors'] = $errors;
    $_SESSION['checkout_form_data'] = $form_data;
    redirect('checkout.php');
}

$mysqli->begin_transaction();
$order_id = null;

try {
    $order_status = ($payment_method === 'COD') ? 'Processing' : 'Pending Payment';

    $sql_order = "INSERT INTO orders (user_id, guest_name, guest_email, total_amount, status, payment_method, shipping_address, order_date, shipping_fee)
                  VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)";

    $stmt_order = $mysqli->prepare($sql_order);

    if ($stmt_order === false) {
         throw new Exception("Database Error: Could not prepare the order statement. Check table structure. MySQL Error: " . $mysqli->error);
    }

    $full_shipping_address = $shipping_address . "\nPhone: " . $phone_number;

    $stmt_order->bind_param("issdsssd",
        $user_id,
        $guest_name,
        $guest_email,
        $grand_total,
        $order_status,
        $payment_method,
        $full_shipping_address,
        $shipping_fee
    );

    if (!$stmt_order->execute()) {
         throw new Exception("Database Error: Could not execute the order insertion. Error: " . $stmt_order->error);
    }
    $order_id = $mysqli->insert_id;
    $stmt_order->close();

    $sql_items = "INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)";
    $sql_stock = "UPDATE products SET stock = stock - ? WHERE product_id = ? AND stock >= ?";

    $stmt_items = $mysqli->prepare($sql_items);
    $stmt_stock = $mysqli->prepare($sql_stock);

    if ($stmt_items === false || $stmt_stock === false) {
        throw new Exception("Database Error: Could not prepare order items/stock update statements. MySQL Error: " . $mysqli->error);
    }

    foreach ($cart_items as $item) {
        $stmt_items->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
        if (!$stmt_items->execute()) {
            throw new Exception("Error adding item (ID:{$item['product_id']}) to order {$order_id}: " . $stmt_items->error);
        }

        $stmt_stock->bind_param("iii", $item['quantity'], $item['product_id'], $item['quantity']);
        if (!$stmt_stock->execute()) {
             error_log("Stock update execute error for PID {$item['product_id']} in Order {$order_id}: " . $stmt_stock->error);
             throw new Exception("Stock update failed for product ID {$item['product_id']}. Database error.");
        }
        if ($mysqli->affected_rows == 0) {
             throw new Exception("Stock update failed for product ID {$item['product_id']} (Order {$order_id}). Insufficient stock detected during update.");
        }
    }
    $stmt_items->close();
    $stmt_stock->close();

    $sql_clear_cart = "DELETE FROM cart ";
    $stmt_clear = null;
    if ($is_guest) {
        $sql_clear_cart .= " WHERE session_id = ? AND user_id IS NULL";
        $stmt_clear = $mysqli->prepare($sql_clear_cart);
        if($stmt_clear) $stmt_clear->bind_param("s", $session_id);
    } else {
        $sql_clear_cart .= " WHERE user_id = ?";
        $stmt_clear = $mysqli->prepare($sql_clear_cart);
        if($stmt_clear) $stmt_clear->bind_param("i", $user_id);
    }

    if ($stmt_clear === false) {
        error_log("Error preparing cart clear statement (User/Session: ".($user_id ?: $session_id).") after order $order_id: " . $mysqli->error);
    } else {
        if (!$stmt_clear->execute()) {
             error_log("Error executing cart clear (User/Session: ".($user_id ?: $session_id).") after order $order_id: " . $stmt_clear->error);
        }
        $stmt_clear->close();
    }

    $mysqli->commit();

    $_SESSION['last_order_id'] = $order_id;

    unset($_SESSION['checkout_errors'], $_SESSION['checkout_form_data']);
    redirect('order_success.php');

} catch (Exception $e) {
    $mysqli->rollback();

    error_log("Order Placement Failed (Order ID Attempted: " . ($order_id ?? 'N/A') . ", Payment: $payment_method): " . $e->getMessage());

    set_message("Order placement failed: " . $e->getMessage(), "error");

    if (!isset($errors['general'])) {
        $errors['general'] = "Order placement failed due to an internal error.";
    }
    $_SESSION['checkout_errors'] = $errors;
    $_SESSION['checkout_form_data'] = $form_data;
    redirect('checkout.php');
}

exit();
?>
