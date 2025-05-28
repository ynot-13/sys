<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['product_id'])) {

 
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        set_message('Invalid request. Please try again.', 'error');
        $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'products.php';
        redirect($redirect_url);
    }


    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    
    if ($product_id === false || $product_id <= 0) {
        set_message('Invalid product selected.', 'error');
        redirect('products.php');
    }
    if ($quantity === false || $quantity <= 0) {
        $quantity = 1; 
    }


    $stmt_check = $mysqli->prepare("SELECT stock, name FROM products WHERE product_id = ?");
    $stmt_check->bind_param("i", $product_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($result_check->num_rows > 0) {
        $product = $result_check->fetch_assoc();
    
        if ($product['stock'] < $quantity) {
             set_message("Sorry, only {$product['stock']} units of " . htmlspecialchars($product['name']) . " are available.", 'warning');
             $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'products.php';
             redirect($redirect_url);
        }
    } else {
        set_message('Product not found.', 'error');
        redirect('products.php');
    }
    $stmt_check->close();


    $user_id = null;
    $session_id = null;
    if (isLoggedIn()) {
        $user_id = $_SESSION['user_id'];
    } else {
        $session_id = session_id();
    }

   
    $existing_quantity = 0;
    $cart_id = null;

    if ($user_id) {
        $sql_check_cart = "SELECT cart_id, quantity FROM cart WHERE user_id = ? AND product_id = ?";
        $stmt_check_cart = $mysqli->prepare($sql_check_cart);
        $stmt_check_cart->bind_param("ii", $user_id, $product_id);
    } else {
        $sql_check_cart = "SELECT cart_id, quantity FROM cart WHERE session_id = ? AND product_id = ? AND user_id IS NULL";
        $stmt_check_cart = $mysqli->prepare($sql_check_cart);
        $stmt_check_cart->bind_param("si", $session_id, $product_id);
    }

    if ($stmt_check_cart && $stmt_check_cart->execute()) {
        $result_cart = $stmt_check_cart->get_result();
        if ($row = $result_cart->fetch_assoc()) {
            $cart_id = $row['cart_id'];
            $existing_quantity = $row['quantity'];
        }
        $stmt_check_cart->close();
    } else {
         error_log("Error checking existing cart item: " . ($stmt_check_cart ? $stmt_check_cart->error : $mysqli->error));
         set_message('Error processing cart. Please try again.', 'error');
         redirect('products.php');
    }


   
    if ($cart_id) {
       
        $new_quantity = $existing_quantity + $quantity;
        $sql_update = "UPDATE cart SET quantity = ? WHERE cart_id = ?";
        $stmt_update = $mysqli->prepare($sql_update);
        $stmt_update->bind_param("ii", $new_quantity, $cart_id);
        if (!$stmt_update->execute()) {
             error_log("Error updating cart: " . $stmt_update->error);
             set_message('Error updating cart quantity.', 'error');
        } else {
            set_message(htmlspecialchars($product['name']) . " quantity updated in your cart.", 'success');
        }
        $stmt_update->close();
    } else {
     
        if ($user_id) {
            $sql_insert = "INSERT INTO cart (user_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW())";
            $stmt_insert = $mysqli->prepare($sql_insert);
            $stmt_insert->bind_param("iii", $user_id, $product_id, $quantity);
        } else {
            $sql_insert = "INSERT INTO cart (session_id, product_id, quantity, added_at, user_id) VALUES (?, ?, ?, NOW(), NULL)";
            $stmt_insert = $mysqli->prepare($sql_insert);
            $stmt_insert->bind_param("sii", $session_id, $product_id, $quantity);
        }

         if ($stmt_insert && $stmt_insert->execute()) {
            set_message(htmlspecialchars($product['name']) . " added to your cart.", 'success');
        } else {
            error_log("Error adding to cart: " . ($stmt_insert ? $stmt_insert->error : $mysqli->error));
             set_message('Error adding item to cart.', 'error');
        }
        if($stmt_insert) $stmt_insert->close();
    }

    $mysqli->close();

    $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'cart.php';

    if (strpos($redirect_url, 'add_to_cart.php') !== false) {
        $redirect_url = 'cart.php';
    }
     redirect($redirect_url);

} else {

    redirect('products.php');
}
?>