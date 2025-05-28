<?php
require_once __DIR__ . '/includes/config.php'; 
require_once __DIR__ . '/includes/db_connect.php'; 
require_once __DIR__ . '/includes/functions.php'; 

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_message('Invalid request method.', 'error');
    redirect(BASE_URL . "products.php"); 
}

if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
     set_message('Invalid security token. Please try again.', 'error');
     $redirect_url = $_SERVER['HTTP_REFERER'] ?? BASE_URL . "products.php";
     redirect($redirect_url);
}


$product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);


$redirect_on_error = $_SERVER['HTTP_REFERER'] ?? BASE_URL . "products.php";


if (!$product_id || $product_id <= 0) {
    set_message('Invalid product selected.', 'error');
    redirect($redirect_on_error);
}
if (!$quantity || $quantity <= 0) {
   
    $quantity = 1;
}

if (!$mysqli || $mysqli->connect_error) {
     error_log("Database connection error in buy_now_process: " . ($mysqli->connect_error ?? 'Unknown error'));
     set_message('A database error occurred. Please try again later. [Code: DB_CONN]', 'error');
     redirect($redirect_on_error);
}

$sql_check = "SELECT product_id, name, stock FROM products WHERE product_id = ? AND is_active = 1";
$stmt_check = $mysqli->prepare($sql_check);

if (!$stmt_check) {
    error_log("Buy Now Check Prepare Error: " . $mysqli->error);
    set_message('An error occurred. Please try again later. [Code: DB_PREP_CHECK]', 'error');
    redirect($redirect_on_error);
}

$stmt_check->bind_param("i", $product_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$product = $result_check->fetch_assoc();
$stmt_check->close();


if (!$product) {
     set_message('The selected product is not available.', 'warning');
     redirect($redirect_on_error);
}


if ($product['stock'] < $quantity) {
     set_message('Sorry, only ' . $product['stock'] . ' of "' . htmlspecialchars($product['name']) . '" is available. Cannot proceed with "Buy Now" for the requested quantity.', 'warning');
     redirect($redirect_on_error);
}


try {
    unset($_SESSION['cart']);
    $_SESSION['cart'] = []; 

 
    $_SESSION['cart'][$product_id] = $quantity;

  

   
    if ($mysqli) { $mysqli->close(); } 
    redirect(BASE_URL . 'checkout.php');

} catch (Exception $e) {

    error_log("Error during Buy Now session processing: " . $e->getMessage());
    set_message('An unexpected error occurred while preparing your order. Please try adding to cart instead.', 'error');
    if ($mysqli) { $mysqli->close(); }
    redirect($redirect_on_error);
}

?>