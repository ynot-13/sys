<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';




if (!isLoggedIn()) {
    $_SESSION['redirect_url'] = 'cart.php'; 
    set_message("Please log in to reorder items.", "warning");
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_message("Invalid request method.", "error");
    redirect('order_history.php');
}

// CSRF Protection
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    set_message("CSRF token validation failed. Please try again.", "error");
    redirect('order_history.php'); 
}


$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];

if (!$order_id || $order_id <= 0) {
    set_message("Invalid order ID for reorder.", "error");
    redirect('order_history.php');
}


$stmt_verify = $mysqli->prepare("SELECT order_id FROM orders WHERE order_id = ? AND user_id = ?");
if (!$stmt_verify) {
    error_log("Reorder - Verify order prepare error: " . $mysqli->error);
    set_message("Error verifying order ownership.", "error");
    redirect('order_history.php');
}
$stmt_verify->bind_param("ii", $order_id, $user_id);
$stmt_verify->execute();
$result_verify = $stmt_verify->get_result();
if ($result_verify->num_rows === 0) {
    set_message("You do not have permission to reorder this order, or the order does not exist.", "warning");
    redirect('order_history.php');
}
$stmt_verify->close();



$order_items_to_reorder = [];
$sql_items = "SELECT oi.product_id, oi.quantity 
              FROM order_items oi
              WHERE oi.order_id = ?";

if ($stmt_items = $mysqli->prepare($sql_items)) {
    $stmt_items->bind_param("i", $order_id);
    if ($stmt_items->execute()) {
        $result_items = $stmt_items->get_result();
        while ($row = $result_items->fetch_assoc()) {
          
            if ($row['product_id']) {
                $order_items_to_reorder[] = $row;
            }
        }
        $result_items->free();
    } else {
        error_log("Reorder - Order Items Fetch Execute Error (Order ID: $order_id): " . $stmt_items->error);
        set_message("Error retrieving items to reorder.", "error");
        redirect("order_details.php?id=" . $order_id);
    }
    $stmt_items->close();
} else {
    error_log("Reorder - Order Items Fetch Prepare Error: " . $mysqli->error);
    set_message("A database error occurred fetching items to reorder.", "error");
    redirect("order_details.php?id=" . $order_id);
}

if (empty($order_items_to_reorder)) {
    set_message("No reorderable items found in this order (some products might no longer be available).", "info");
    redirect("order_details.php?id=" . $order_id);
}

$messages = ['success' => [], 'error' => [], 'info' => []];
$all_successful = true;

foreach ($order_items_to_reorder as $item) {
    $product_id = $item['product_id'];
    $quantity = $item['quantity'];

   
    $cart_result = addToCart($product_id, $quantity, $mysqli);

    if ($cart_result['success']) {
  
    } else {
        $all_successful = false;
        $messages['error'][] = $cart_result['message']; 
    }
}

if ($all_successful && !empty($order_items_to_reorder)) {
    set_message("All items from order #{$order_id} have been added to your cart. Please review current prices and availability.", "success");
} elseif (!$all_successful && !empty($messages['error'])) {
    $summary_message = "Some items from order #{$order_id} were processed. ";
    $summary_message .= "Details: " . implode("; ", $messages['error']);
    if (count($order_items_to_reorder) > count($messages['error'])) {
         $summary_message .= " Other items were added successfully. Please review your cart.";
    }
    set_message($summary_message, "warning");
} else {
  
    set_message("Could not reorder items from order #{$order_id}. Please check product availability.", "error");
}

redirect('cart.php');
?>