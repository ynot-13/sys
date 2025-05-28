<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';



if (!isLoggedIn()) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    set_message("Please log in to view your order details.", "warning");
    redirect('login.php');
}

$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$order_id || $order_id <= 0) {
    set_message("Invalid order ID specified.", "error");
    redirect('order_history.php');
}

$user_id = $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];


$order = null;
$sql_order = "SELECT order_id, order_date, total_amount, status, shipping_address, payment_method
              FROM orders
              WHERE order_id = ? AND user_id = ?";

if ($stmt_order = $mysqli->prepare($sql_order)) {
    $stmt_order->bind_param("ii", $order_id, $user_id);
    if ($stmt_order->execute()) {
        $result_order = $stmt_order->get_result();
        if ($result_order->num_rows === 1) {
            $order = $result_order->fetch_assoc();
            $page_title = "Order Details #" . htmlspecialchars($order['order_id']);
        } else {
            set_message("Order not found or you do not have permission to view it.", "warning");
            redirect('order_history.php');
        }
        $result_order->free();
    } else {
        error_log("Order Details Fetch Execute Error (Order ID: $order_id, User ID: $user_id): " . $stmt_order->error);
        set_message("Error retrieving order details.", "error");
        redirect('order_history.php');
    }
    $stmt_order->close();
} else {
    error_log("Order Details Fetch Prepare Error: " . $mysqli->error);
    set_message("A database error occurred.", "error");
    redirect('order_history.php');
}

$order_items = [];
$sql_items = "SELECT oi.quantity, oi.price_at_purchase, p.product_id, p.name as product_name, p.image_url
              FROM order_items oi
              LEFT JOIN products p ON oi.product_id = p.product_id
              WHERE oi.order_id = ?";

if ($stmt_items = $mysqli->prepare($sql_items)) {
    $stmt_items->bind_param("i", $order_id);
    if ($stmt_items->execute()) {
        $result_items = $stmt_items->get_result();
        while ($row = $result_items->fetch_assoc()) {
            $order_items[] = $row;
        }
        $result_items->free();
    } else {
        error_log("Order Items Fetch Execute Error (Order ID: $order_id): " . $stmt_items->error);
        set_message("Error retrieving items for this order.", "error");
    }
    $stmt_items->close();
} else {
    error_log("Order Items Fetch Prepare Error: " . $mysqli->error);
    set_message("A database error occurred fetching order items.", "error");
}

require_once 'includes/header.php';
?>

<div class="account-page order-details-page">
    <h1><?php echo $page_title; ?></h1>
    <a href="order_history.php" class="btn btn-sm btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Order History</a>

    <?php display_message(); ?>

    <?php if ($order): ?>
        <div style="display: flex; flex-wrap: wrap; gap: 25px; margin-bottom: 30px;">
            <div style="flex: 1; min-width: 280px; background-color: #f8f9fa; padding: 20px; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                <h4>Order Summary</h4>
                <p><strong>Order Date:</strong> <?php echo date("F j, Y - g:i A", strtotime($order['order_date'])); ?></p>
                <p><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($order['status'])); ?></p>
                <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
                <p><strong>Order Total:</strong> <strong style="color: var(--primary-color);"><?php echo formatCurrency($order['total_amount']); ?></strong></p>
            </div>

            <div style="flex: 1.5; min-width: 300px; background-color: #f8f9fa; padding: 20px; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                <h4>Shipping Address</h4>
                <p style="white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
            </div>
        </div>

        <div>
            <h4>Items in this Order</h4>
            <?php if (!empty($order_items)): ?>
                <div class="table-responsive">
                    <table class="admin-table order-items-table">
                        <thead>
                            <tr>
                                <th colspan="2">Product</th>
                                <th>Quantity</th>
                                <th>Price Paid (at time of order)</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item):
                                $subtotal = $item['price_at_purchase'] * $item['quantity'];
                                $product_link = $item['product_id'] ? "product_details.php?id=" . $item['product_id'] : "#";
                                $product_name = $item['product_name'] ?: "[Product Information Unavailable]";
                                $image_src = BASE_URL . ($item['image_url'] ?: 'img/placeholder.png');
                            ?>
                                <tr>
                                    <td style="width: 60px;">
                                        <?php if ($item['product_id']):  ?>
                                        <a href="<?php echo $product_link; ?>">
                                            <img src="<?php echo htmlspecialchars($image_src); ?>" alt="<?php echo htmlspecialchars($product_name); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: var(--border-radius-sm);">
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo $product_link; ?>"><?php echo htmlspecialchars($product_name); ?></a>
                                        <?php if(!$item['product_id'] && $item['product_name']) echo " <small class='text-muted'>(Product details may have changed or item removed)</small>"; ?>
                                        <?php if(!$item['product_name']) echo " <small class='text-muted'>(Product no longer listed)</small>"; ?>
                                    </td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td><?php echo formatCurrency($item['price_at_purchase']); ?></td>
                                    <td><?php echo formatCurrency($subtotal); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td colspan="4" style="text-align: right; font-weight: bold; border-top: 2px solid var(--heading-color);">
                                    Order Total:
                                </td>
                                <td style="font-weight: bold; border-top: 2px solid var(--heading-color);">
                                    <?php echo formatCurrency($order['total_amount']); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">Could not retrieve items for this order, or the order was empty.</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($order_items)): ?>
        <div style="margin-top: 30px; text-align: right;">
            <form action="reorder_process.php" method="post" style="display: inline;">
                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            </form>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <p class="text-danger">Could not load order details.</p>
    <?php endif; ?>

</div>

<?php
require_once 'includes/footer.php';
?>