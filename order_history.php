<?php
$page_title = "Order History";
require_once 'includes/header.php';


if (!isLoggedIn()) {
    $_SESSION['redirect_url'] = 'order_history.php';
    set_message("Please log in to view your order history.", "warning");
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$orders = [];


$sql = "SELECT order_id, order_date, total_amount, status FROM orders WHERE user_id = ? ORDER BY order_date DESC";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
} else {
     set_message("Error retrieving order history.", "error");
     error_log("Order history DB error: " . $stmt->error);
}

?>

<div class="order-history-page">
    <h1>My Order History</h1>

    <?php if (!empty($orders)): ?>
        <table class="order-history-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Date Placed</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td data-label="Order ID">#<?php echo $order['order_id']; ?></td>
                        <td data-label="Date Placed"><?php echo date("M d, Y - h:i A", strtotime($order['order_date'])); ?></td>
                        <td data-label="Total Amount"><?php echo formatCurrency($order['total_amount']); ?></td>
                        <td data-label="Status"><?php echo htmlspecialchars($order['status']); ?></td>
                        <td data-label="Details">
                            <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-secondary order-details-link">View Details</a>
                           
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif (isset($stmt)):  ?>
         <div class="empty-cart" style="background: none; box-shadow: none; padding: 20px;">
             <i class="fas fa-receipt" style="font-size: 3rem; color: var(--secondary-color);"></i>
            <p>You haven't placed any orders yet.</p>
            <a href="products.php" class="btn">Start Shopping</a>
        </div>
    <?php endif; ?>

</div>

<?php

require_once 'includes/footer.php';
?>