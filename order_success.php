<?php
$page_title = "Order Successful";
require_once 'includes/header.php';
if (!isset($_SESSION['last_order_id'])) {
 
    set_message("No recent order found.", "info");
    redirect(isLoggedIn() ? 'order_history.php' : 'index.php');
}

$order_id = $_SESSION['last_order_id'];
$order_details = null;
$stmt = $mysqli->prepare("SELECT order_id, total_amount, status, guest_email, user_id FROM orders WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $order_details = $result->fetch_assoc();
       
    }
    $stmt->close();
}

?>

<div class="order-success-page" style="text-align: center; padding: 40px; background-color: var(--white-color); border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
    <i class="fas fa-check-circle" style="font-size: 5rem; color: var(--success-color); margin-bottom: 20px;"></i>
    <h1>Thank You For Your Order!</h1>

    <?php if ($order_details): ?>
        <p>Your order (ID: <strong>#<?php echo htmlspecialchars($order_details['order_id']); ?></strong>) has been placed successfully.</p>
         <p>Total Amount: <strong><?php echo formatCurrency($order_details['total_amount']); ?></strong></p>
         <p>Current Status: <strong><?php echo htmlspecialchars($order_details['status']); ?></strong></p>

         <?php
            
             $confirmation_email = '';
             if (!empty($order_details['guest_email'])) {
                 $confirmation_email = $order_details['guest_email'];
             } elseif (isLoggedIn() && isset($_SESSION['email'])) {
                 $confirmation_email = $_SESSION['email'];
             }

             if ($confirmation_email) {
                 echo "<p>A confirmation email has been sent to <strong>" . htmlspecialchars($confirmation_email) . "</strong>.</p>";
             }
         ?>

        <p style="margin-top: 20px;">You will receive updates regarding your order status.</p>

         <?php if (isLoggedIn()): ?>
             <a href="order_history.php" class="btn" style="margin-top: 20px;">View Order History</a>
         <?php endif; ?>

    <?php else: ?>
         <p>Your order has been placed successfully.</p>
         <p>We encountered an issue retrieving the full order details at this moment, but your order is confirmed.</p>
    <?php endif; ?>

    <a href="products.php" class="btn btn-secondary" style="margin-left: 10px; margin-top: 20px;">Continue Shopping</a>

</div>

<?php

unset($_SESSION['last_order_id']);

require_once 'includes/footer.php';
?>