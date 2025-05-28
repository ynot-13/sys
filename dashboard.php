
<?php
$page_title = "";
require_once 'admin_auth.php'; 
require_once __DIR__ . '/../includes/db_connect.php'; 
require_once 'admin_header.php';


$stats = ['total_users' => 0, 'total_products' => 0, 'pending_orders' => 0, 'total_sales' => 0, 'pending_feedback' => 0];
$recent_orders = [];
$unread_messages = []; 

if (isset($mysqli) && $mysqli instanceof mysqli && !$mysqli->connect_error) {
    if($result = $mysqli->query("SELECT COUNT(*) as c FROM users WHERE role = 'user'")) { $stats['total_users'] = $result->fetch_assoc()['c']??0; $result->free(); }
    if($result = $mysqli->query("SELECT COUNT(*) as c FROM products")) { $stats['total_products'] = $result->fetch_assoc()['c']??0; $result->free(); }
    if($result = $mysqli->query("SELECT COUNT(*) as c FROM orders WHERE status IN ('Pending Payment', 'Processing')")) { $stats['pending_orders'] = $result->fetch_assoc()['c']??0; $result->free(); }
    if($result = $mysqli->query("SELECT SUM(total_amount) as t FROM orders WHERE status = 'Delivered'")) { $stats['total_sales'] = $result->fetch_assoc()['t']??0; $result->free(); }
    if($result = $mysqli->query("SELECT COUNT(*) as c FROM feedback WHERE is_approved = 0")) { $stats['pending_feedback'] = $result->fetch_assoc()['c']??0; $result->free(); }

   
     $sql_recent = "SELECT o.order_id, o.order_date, o.total_amount, o.status,
                           COALESCE(u.username, o.guest_name, 'Guest') as customer_name
                    FROM orders o
                    LEFT JOIN users u ON o.user_id = u.user_id
                    ORDER BY o.order_date DESC LIMIT 5";
     if($result_recent = $mysqli->query($sql_recent)){
         while($row = $result_recent->fetch_assoc()){ $recent_orders[] = $row; }
         $result_recent->free();
     } else { error_log("Dashboard recent orders query failed: " . $mysqli->error); }

   
    $sql_unread_msg = "SELECT m.message_id, COALESCE(m.parent_message_id, m.message_id) as thread_id,
                              m.subject, LEFT(m.body, 70) as body_snippet, m.sent_at,
                              u_sender.username as sender_username
                       FROM messages m
                       JOIN users u_sender ON m.sender_id = u_sender.user_id
                       WHERE m.receiver_id = ?
                         AND m.read_at IS NULL
                         AND m.is_deleted_receiver = 0
                       ORDER BY m.sent_at DESC
                       LIMIT 5"; 

    if($stmt_msg = $mysqli->prepare($sql_unread_msg)) {
        $admin_id_var = $current_admin_id; 
        $stmt_msg->bind_param("i", $admin_id_var);
        if($stmt_msg->execute()){
            $result_msg = $stmt_msg->get_result();
            while($row = $result_msg->fetch_assoc()){
                $unread_messages[] = $row;
            }
            $result_msg->free();
        } else { error_log("Dashboard unread messages query failed: " . $stmt_msg->error); }
        $stmt_msg->close();
    } else { error_log("Dashboard unread messages prepare failed: " . $mysqli->error); }


} else { set_message("Database connection error. Stats not loaded.", "error"); display_message("error"); }

$formatted_total_sales = function_exists('formatCurrency')
                            ? formatCurrency($stats['total_sales'])
                            : (defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : 'P') . number_format($stats['total_sales'], 2);

?>

<div class="dashboard-stats">
    <div class="stat-card"> <i class="fas fa-users fa-3x"></i> <h3><?php echo $stats['total_users']; ?></h3> <p>Total Users</p> <a href="manage_users.php" class="btn btn-sm btn-secondary">Manage</a> </div>
    <div class="stat-card"> <i class="fas fa-box-open fa-3x"></i> <h3><?php echo $stats['total_products']; ?></h3> <p>Total Products</p> <a href="manage_products.php" class="btn btn-sm btn-secondary">Manage</a> </div>
    <div class="stat-card"> <i class="fas fa-receipt fa-3x"></i> <h3><?php echo $stats['pending_orders']; ?></h3> <p>Pending Orders</p> <a href="manage_orders.php?status=Pending%20Payment" class="btn btn-sm btn-secondary">View</a> </div>
    <div class="stat-card"> <i class="fas fa-dollar-sign fa-3x"></i> <h3><?php echo $formatted_total_sales; ?></h3> <p>Total Sales</p> <a href="reports.php?type=sales_summary" class="btn btn-sm btn-secondary">Report</a> </div>
    <div class="stat-card"> <i class="fas fa-comments fa-3x"></i> <h3><?php echo $stats['pending_feedback']; ?></h3> <p>Pending Feedback</p> <a href="manage_feedback.php?status=pending" class="btn btn-sm btn-secondary">Review</a> </div>
</div>


<div style="display: flex; flex-wrap: wrap; gap: 30px;">


    <div class="admin-card" style="flex: 1.5; min-width: 350px;">
        <h2>Recent Activity (Orders)</h2>
        <?php if (!empty($recent_orders)): ?>
            <ul> <?php foreach ($recent_orders as $order): ?> <li> <span> Order <a href="view_order.php?id=<?php echo $order['order_id']; ?>">#<?php echo $order['order_id']; ?></a> by <?php echo htmlspecialchars($order['customer_name']); ?> (<?php echo formatCurrency($order['total_amount']); ?>) - Status: <?php echo htmlspecialchars($order['status']); ?> </span> <small class="activity-date"> <?php echo date("M d, Y H:i", strtotime($order['order_date'])); ?> </small> </li> <?php endforeach; ?> </ul>
            <div style="text-align: right; margin-top: 15px;"> <a href="manage_orders.php" class="btn btn-sm btn-outline-secondary">View All Orders</a> </div>
        <?php else: ?> <p>No recent orders found.</p> <?php endif; ?>
    </div>

  
    <div class="admin-card" style="flex: 1; min-width: 300px;">
        <h2>Recent Unread Messages</h2>
        <?php if (!empty($unread_messages)): ?>
            <ul>
                <?php foreach ($unread_messages as $msg): ?>
                <li style="align-items: flex-start;"> 
                    <span style="flex-grow: 1;">
                        <a href="admin_view_message.php?thread_id=<?php echo $msg['thread_id']; ?>" style="font-weight: bold;">
                           <?php echo htmlspecialchars($msg['subject'] ?: '(No Subject)'); ?>
                        </a><br>
                        <small>From: <?php echo htmlspecialchars($msg['sender_username']); ?> - <?php echo date("M d, H:i", strtotime($msg['sent_at'])); ?></small><br>
                        <p style="margin: 5px 0 0 0; font-size: 0.9em; color: #555;">
                            <?php echo htmlspecialchars($msg['body_snippet']); ?>...
                        </p>
                    </span>
                    <a href="admin_view_message.php?thread_id=<?php echo $msg['thread_id']; ?>" class="btn btn-sm btn-info" style="margin-left: 10px; flex-shrink: 0;">View</a>
                </li>
                <?php endforeach; ?>
            </ul>
            <div style="text-align: right; margin-top: 15px;">
                 <a href="admin_manage_messages.php" class="btn btn-sm btn-outline-secondary">View All Messages</a>
             </div>
        <?php else: ?>
             <p>No unread messages.</p>
        <?php endif; ?>
    </div>

</div>


<?php require_once 'admin_footer.php'; ?>