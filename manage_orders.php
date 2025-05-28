<?php
$page_title = "";
require_once 'admin_auth.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once 'admin_header.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
     if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        set_message('Invalid request token.', 'error');
     } else {
         $order_id_update = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
         $new_status = sanitize_input($_POST['new_status'] ?? '');
         $allowed_statuses = ['Pending Payment', 'Processing', 'Shipped', 'Delivered', 'Cancelled', 'Failed'];

         if ($order_id_update && $new_status && in_array($new_status, $allowed_statuses)) {
             $stmt_update = $mysqli->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
             if ($stmt_update) {
                 $stmt_update->bind_param("si", $new_status, $order_id_update);
                 if ($stmt_update->execute()) {
                     set_message("Order #{$order_id_update} status updated to '{$new_status}'.", "success");
                    
                 } else {
                      set_message("Error updating order status: " . $stmt_update->error, "error");
                 }
                 $stmt_update->close();
             } else {
                  set_message("Error preparing status update.", "error");
             }
         } else {
              set_message("Invalid order ID or status provided.", "error");
         }
     }
      
      header("Location: " . $_SERVER['REQUEST_URI']); 
      exit();
}


$orders = [];
$where_clauses = [];
$params = [];
$types = '';
$filter_values = []; 


$filter_status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
if (!empty($filter_status)) {
     $where_clauses[] = "o.status = ?";
     $params[] = $filter_status;
     $types .= 's';
     $filter_values['status'] = $filter_status;
}


$filter_user_id = isset($_GET['user_id']) ? filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT) : null;
if ($filter_user_id) {
    $where_clauses[] = "o.user_id = ?";
    $params[] = $filter_user_id;
    $types .= 'i';
    $filter_values['user_id'] = $filter_user_id;
}

$sql = "SELECT o.order_id, o.order_date, o.total_amount, o.status,
               COALESCE(u.username, o.guest_name, 'Guest') as customer_name,
               o.user_id, o.guest_email
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.user_id ";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY o.order_date DESC";


$stmt = $mysqli->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if($stmt->execute()){
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        $stmt->close();
    } else {
         set_message("Error fetching orders: " . $stmt->error, "error");
    }
} else {
     set_message("Database query error preparing orders: " . $mysqli->error, "error");
}

$allowed_statuses = ['Pending Payment', 'Processing', 'Shipped', 'Delivered', 'Cancelled', 'Failed'];
?>

<div class="admin-card">
    <h2>Order List
        <?php 
            $filter_texts = [];
            if (!empty($filter_values['status'])) $filter_texts[] = "Status: ".htmlspecialchars($filter_values['status']);
            if (!empty($filter_values['user_id'])) $filter_texts[] = "User ID: ".htmlspecialchars($filter_values['user_id']);
            if (!empty($filter_texts)) echo " (".implode(', ', $filter_texts).")";
        ?>
    </h2>

    <form action="manage_orders.php" method="get" style="margin-bottom: 20px; background-color: #f8f9fa; padding: 15px; border-radius: 5px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
         <div>
             <label for="status_filter" style="margin-right: 5px; font-weight: 500;">Filter by Status:</label>
             <select name="status" id="status_filter" class="form-control form-control-sm" style="display: inline-block; width: auto;">
                 <option value="">All Statuses</option>
                 <?php foreach ($allowed_statuses as $stat): ?>
                    <option value="<?php echo $stat; ?>" <?php echo (isset($filter_values['status']) && $filter_values['status'] === $stat) ? 'selected' : ''; ?>><?php echo $stat; ?></option>
                 <?php endforeach; ?>
             </select>
         </div>
        
         <?php if(isset($filter_values['user_id'])): ?>
            <input type="hidden" name="user_id" value="<?php echo $filter_values['user_id']; ?>">
         <?php endif; ?>
         <button type="submit" class="btn btn-sm btn-primary">Filter</button>
         <?php if(!empty($filter_values)): ?>
            <a href="manage_orders.php" class="btn btn-sm btn-secondary">Clear Filters</a>
         <?php endif; ?>
    </form>

     <?php display_message();  ?>

    <div style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                
            </thead>
            <tbody>
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?php echo $order['order_id']; ?></td>
                            <td><?php echo date("Y-m-d H:i", strtotime($order['order_date'])); ?></td>
                            <td>
                                <?php echo htmlspecialchars($order['customer_name']); ?>
                                <?php if ($order['user_id']): ?>
                                    <small>(<a href="manage_users.php?user_id=<?php echo $order['user_id']; ?>" title="View User">ID: <?php echo $order['user_id']; ?></a>)</small>
                                <?php elseif ($order['guest_email']): ?>
                                     <small>(<?php echo htmlspecialchars($order['guest_email']); ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo function_exists('formatCurrency') ? formatCurrency($order['total_amount']) : CURRENCY_SYMBOL . number_format($order['total_amount'], 2); ?></td>
                            <td>
                              
                                <form action="manage_orders.php?<?php echo http_build_query($filter_values);  ?>" method="post" style="display: flex; align-items: center; gap: 5px;">
                                     <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                    <input type="hidden" name="action" value="update_status">
                                    <select name="new_status" class="form-control form-control-sm" style="width: auto;">
                                        <?php foreach ($allowed_statuses as $stat): ?>
                                        <option value="<?php echo $stat; ?>" <?php echo ($order['status'] === $stat) ? 'selected' : ''; ?>><?php echo $stat; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-success" title="Update Status"><i class="fas fa-check"></i></button>
                                </form>
                            </td>
                             <td>
                                <a href="view_order.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-info" title="View Order Details">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">No orders found matching the criteria.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
  
</div>

<?php
require_once 'admin_footer.php';
?>