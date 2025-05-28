<?php
require_once 'includes/config.php'; 
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['quantity']) && is_array($_POST['quantity'])) {

     if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        set_message('Invalid request. Please try again.', 'error');
        redirect('cart.php');
    }

    
    $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
    $session_id = !$user_id ? session_id() : null;

    $updated_count = 0;
    $error_count = 0;

    
    foreach ($_POST['quantity'] as $cart_id => $quantity) {
        $cart_id = filter_var($cart_id, FILTER_VALIDATE_INT);
        $quantity = filter_var($quantity, FILTER_VALIDATE_INT);

        if ($cart_id === false || $cart_id <= 0) {
            continue; 
        }

        if ($quantity === false || $quantity < 0) {
            $quantity = 0; 
        }

        
        if ($quantity > 0) {
            
            $sql = "UPDATE cart SET quantity = ? WHERE cart_id = ? ";
            if ($user_id) {
                $sql .= " AND user_id = ?";
            } else {
                $sql .= " AND session_id = ? AND user_id IS NULL";
            }

            $stmt = $mysqli->prepare($sql);

             if ($user_id) {
                $stmt->bind_param("iii", $quantity, $cart_id, $user_id);
             } else {
                 $stmt->bind_param("iis", $quantity, $cart_id, $session_id);
             }

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $updated_count++;
                }
                
            } else {
                error_log("Error updating cart item $cart_id: " . $stmt->error);
                $error_count++;
            }
            $stmt->close();

        } else {
            
            $sql = "DELETE FROM cart WHERE cart_id = ? ";
             if ($user_id) {
                $sql .= " AND user_id = ?";
            } else {
                $sql .= " AND session_id = ? AND user_id IS NULL";
            }

             $stmt = $mysqli->prepare($sql);

             if ($user_id) {
                $stmt->bind_param("ii", $cart_id, $user_id);
             } else {
                 $stmt->bind_param("is", $cart_id, $session_id);
             }


            if ($stmt->execute()) {
                 if ($stmt->affected_rows > 0) {
                    $updated_count++; 
                 }
            } else {
                error_log("Error removing cart item $cart_id: " . $stmt->error);
                $error_count++;
            }
            $stmt->close();
        }
    }

    $mysqli->close();


    if ($error_count > 0) {
        set_message('Some items could not be updated. Please try again.', 'warning');
    } elseif ($updated_count > 0) {
        set_message('Cart updated successfully.', 'success');
    } else {
         set_message('No changes were made to the cart.', 'info');
    }

    
    redirect('cart.php');

} else {
  
    redirect('cart.php');
}
?>