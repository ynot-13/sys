<?php
require_once 'includes/config.php'; 
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';


if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['cart_id'])) {

     
    if (!isset($_GET['token']) || !verifyCsrfToken($_GET['token'])) {
        set_message('Invalid request or token expired. Please try again.', 'error');
        redirect('cart.php');
    }

    
    $cart_id = filter_input(INPUT_GET, 'cart_id', FILTER_VALIDATE_INT);

    if ($cart_id === false || $cart_id <= 0) {
        set_message('Invalid item specified.', 'error');
        redirect('cart.php');
    }


    $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
    $session_id = !$user_id ? session_id() : null;

  
    $sql = "DELETE FROM cart WHERE cart_id = ? ";
    if ($user_id) {
        $sql .= " AND user_id = ?";
    } else {
        $sql .= " AND session_id = ? AND user_id IS NULL";
    }

    $stmt = $mysqli->prepare($sql);

    if ($stmt) {
        if ($user_id) {
            $stmt->bind_param("ii", $cart_id, $user_id);
        } else {
            $stmt->bind_param("is", $cart_id, $session_id);
        }

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                set_message('Item removed from cart successfully.', 'success');
            } else {
               
                 set_message('Item not found in your cart or could not be removed.', 'warning');
            }
        } else {
            error_log("Error removing item from cart: " . $stmt->error);
            set_message('Error removing item. Please try again.', 'error');
        }
        $stmt->close();
    } else {
         error_log("Prepare statement failed for cart removal: " . $mysqli->error);
         set_message('An error occurred. Please try again.', 'error');
    }

    $mysqli->close();


    redirect('cart.php');

} else {
    redirect('cart.php');
}
?>
