<?php
$page_title = "";
require_once 'admin_auth.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once 'admin_header.php';


$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$order_id) {
    set_message("Invalid Order ID.", "error");
    redirect("manage_orders.php");
}


$order = null;
$order_items = [];

$sql_order = "SELECT o.*, COALESCE(u.username, o.guest_name, 'Guest') as customer_name, u.email as user_email
              FROM orders o
              LEFT JOIN users u ON o.user_id = u.user_id
              WHERE o.order_id = ?";
$stmt_order = $mysqli->prepare($sql_order);
if(!$stmt_order){
     set_message("Error preparing order query: " . $mysqli->error, "error");
     redirect("manage_orders.php");
}
$stmt_order->bind_param("i", $order_id);
if ($stmt_order->execute()) {
    $result_order = $stmt_order->get_result();
    if ($result_order->num_rows === 1) {
        $order = $result_order->fetch_assoc();
        $page_title = "Order #" . $order['order_id']; 

      
        $sql_items = "SELECT oi.*, p.name as product_name, p.image_url, p.product_id
                      FROM order_items oi
                      JOIN products p ON oi.product_id = p.product_id
                      WHERE oi.order_id = ?";
        $stmt_items = $mysqli->prepare($sql_items);
        if($stmt_items){
            $stmt_items->bind_param("i", $order_id);
            if ($stmt_items->execute()) {
                $result_items = $stmt_items->get_result();
                while ($row = $result_items->fetch_assoc()) {
                    $order_items[] = $row;
                }
            } else {
                 set_message("Error fetching order items: " . $stmt_items->error, "error");
            }
            $stmt_items->close();
        } else {
             set_message("Error preparing order items query: " . $mysqli->error, "error");
        }

    } else {
        set_message("Order not found.", "error");
        redirect("manage_orders.php");
    }
} else {
     set_message("Error fetching order data: " . $stmt_order->error, "error");
     redirect("manage_orders.php");
}
$stmt_order->close();

$allowed_statuses = ['Pending Payment', 'Processing', 'Shipped', 'Delivered', 'Cancelled', 'Failed'];
?>

<div class="admin-card">
     <a href="manage_orders.php" class="btn btn-sm btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Order List</a>
    <h2>Order Details: #<?php echo $order['order_id']; ?></h2>

    <?php display_message(); ?>

    <div style="display: flex; flex-wrap: wrap; gap: 25px; margin-bottom: 30px;">
     
        <div style="flex: 1; min-width: 300px; background-color: #f8f9fa; padding: 20px; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
            <h4>Order Information</h4>
            <p><strong>ID:</strong> #<?php echo $order['order_id']; ?></p>
            <p><strong>Date:</strong> <?php echo date("M d, Y - h:i A", strtotime($order['order_date'])); ?></p>
            <p><strong>Total:</strong> <?php echo function_exists('formatCurrency') ? formatCurrency($order['total_amount']) : CURRENCY_SYMBOL . number_format($order['total_amount'], 2); ?></p>
            <p><strong>Payment:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($order['status']); ?></p>
           
        </div>

      
        <div style="flex: 1; min-width: 300px; background-color: #f8f9fa; padding: 20px; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
            <h4>Customer & Shipping</h4>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
            <p><strong>Email:</strong>
                <?php
                $customer_email = $order['user_id'] ? $order['user_email'] : $order['guest_email'];
                echo htmlspecialchars($customer_email ?: 'N/A');
                ?>
            </p>
            <?php if ($order['user_id']): ?>
                <p><strong>Account:</strong> Registered User (<a href="manage_users.php?user_id=<?php echo $order['user_id']; ?>">ID: <?php echo $order['user_id']; ?></a>)</p>
            <?php else: ?>
                 <p><strong>Account:</strong> Guest</p>
            <?php endif; ?>
            <p><strong>Shipping Address:</strong><br> <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
        </div>
    </div>

    <div>
        <h4>Order Items</h4>
        <?php if (!empty($order_items)): ?>
            <div style="overflow-x: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Price @ Purchase</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item):
                            $subtotal = $item['price_at_purchase'] * $item['quantity'];
                        ?>
                            <tr>
                                <td>
                                     <img src="<?php echo BASE_URL . htmlspecialchars($item['image_url'] ?: 'img/placeholder.png'); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" style="width: 40px; height: 40px; object-fit: cover;">
                                </td>
                                <td>
                                    <a href="../product_details.php?id=<?php echo $item['product_id']; ?>" target="_blank">
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                    </a>
                                    <small>(ID: <?php echo $item['product_id']; ?>)</small>
                                </td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo function_exists('formatCurrency') ? formatCurrency($item['price_at_purchase']) : CURRENCY_SYMBOL . number_format($item['price_at_purchase'], 2); ?></td>
                                <td><?php echo function_exists('formatCurrency') ? formatCurrency($subtotal) : CURRENCY_SYMBOL . number_format($subtotal, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- Add a total row -->
                        <tr>
                             <td colspan="4" style="text-align: right; font-weight: bold; border-top: 2px solid #333;">Order Total:</td>
                             <td style="font-weight: bold; border-top: 2px solid #333;"><?php echo function_exists('formatCurrency') ? formatCurrency($order['total_amount']) : CURRENCY_SYMBOL . number_format($order['total_amount'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>Could not retrieve order items for this order.</p>
        <?php endif; ?>
    </div>

     <div style="margin-top: 30px; text-align: right;">
         <button class="btn btn-info" onclick="printInvoice()"><i class="fas fa-print"></i> Print Invoice</button>
     </div>

</div>

<script>
function printInvoice() {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Invoice - Order #<?php echo $order['order_id']; ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1, h2, h4 { text-align: center; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                table, th, td { border: 1px solid #ddd; }
                th, td { padding: 8px; text-align: left; }
                th { background-color: #f4f4f4; }
                .total-row { font-weight: bold; border-top: 2px solid #333; }
            </style>
        </head>
        <body>
            <h1>Invoice</h1>
            <h2>Order #<?php echo $order['order_id']; ?></h2>
            <h4>Order Information</h4>
            <p><strong>Date:</strong> <?php echo date("M d, Y - h:i A", strtotime($order['order_date'])); ?></p>
            <p><strong>Total:</strong> <?php echo function_exists('formatCurrency') ? formatCurrency($order['total_amount']) : CURRENCY_SYMBOL . number_format($order['total_amount'], 2); ?></p>
            <p><strong>Payment:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($order['status']); ?></p>
            <h4>Customer & Shipping</h4>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($customer_email ?: 'N/A'); ?></p>
            <p><strong>Shipping Address:</strong><br> <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
            <h4>Order Items</h4>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Price @ Purchase</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo function_exists('formatCurrency') ? formatCurrency($item['price_at_purchase']) : CURRENCY_SYMBOL . number_format($item['price_at_purchase'], 2); ?></td>
                            <td><?php echo function_exists('formatCurrency') ? formatCurrency($item['price_at_purchase'] * $item['quantity']) : CURRENCY_SYMBOL . number_format($item['price_at_purchase'] * $item['quantity'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;">Order Total:</td>
                        <td><?php echo function_exists('formatCurrency') ? formatCurrency($order['total_amount']) : CURRENCY_SYMBOL . number_format($order['total_amount'], 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>

<?php
require_once 'admin_footer.php';
?>