<?php

$page_title = "";
require_once 'admin_auth.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once 'admin_header.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
     if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
          set_message('Invalid request token.', 'error');
     } else {
        $product_id_action = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $action = $_POST['action'];

        if ($product_id_action) {
        
            if ($action === 'toggle_active') {
                $current_status = filter_input(INPUT_POST, 'current_status', FILTER_VALIDATE_INT);
                if ($current_status === 0 || $current_status === 1) {
                    $new_status = $current_status == 1 ? 0 : 1;
                    $action_text = $new_status == 1 ? "activated" : "deactivated";
                    $stmt_toggle = $mysqli->prepare("UPDATE products SET is_active = ? WHERE product_id = ? AND is_deleted = 0"); // Only toggle active if not deleted
                    if ($stmt_toggle) {
                        $stmt_toggle->bind_param("ii", $new_status, $product_id_action);
                        if ($stmt_toggle->execute()) {
                             set_message("Product successfully {$action_text}.", "success");
                        } else {
                             set_message("Error updating product status: " . $stmt_toggle->error, "error");
                        }
                        $stmt_toggle->close();
                    } else {
                         set_message("Error preparing status update: " . $mysqli->error, "error");
                    }
                } else {
                    set_message("Invalid current status for toggle.", "error");
                }
            }
        
            elseif ($action === 'delete_product') {
                 $stmt_delete = $mysqli->prepare("UPDATE products SET is_deleted = 1, is_active = 0 WHERE product_id = ?"); // Also deactivate when deleting
                 if ($stmt_delete) {
                     $stmt_delete->bind_param("i", $product_id_action);
                     if ($stmt_delete->execute()) {
                          set_message("Product successfully marked as deleted.", "success");
                     } else {
                          set_message("Error deleting product: " . $stmt_delete->error, "error");
                     }
                     $stmt_delete->close();
                 } else {
                     set_message("Error preparing delete operation: " . $mysqli->error, "error");
                 }
            }
            
            elseif ($action === 'restore_product') {
               
                 $stmt_restore = $mysqli->prepare("UPDATE products SET is_deleted = 0 WHERE product_id = ?");
                 if ($stmt_restore) {
                     $stmt_restore->bind_param("i", $product_id_action);
                     if ($stmt_restore->execute()) {
                          set_message("Product successfully restored. You may need to activate it separately.", "success");
                     } else {
                          set_message("Error restoring product: " . $stmt_restore->error, "error");
                     }
                     $stmt_restore->close();
                 } else {
                      set_message("Error preparing restore operation: " . $mysqli->error, "error");
                 }
            }
            else {
                set_message("Unknown action.", "error");
            }
        } else {
            set_message("Invalid product ID.", "error");
        }
     }
  
     header("Location: manage_products.php");
     exit();
}


$products = [];

$sql = "SELECT p.product_id, p.name, p.price, p.stock, p.image_url, p.is_active, p.is_deleted, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        ORDER BY p.is_deleted ASC, p.name ASC"; 
$result = $mysqli->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $result->free();
} else {
    
    set_message("Error fetching products: " . $mysqli->error, "error");
    error_log("Error fetching products: " . $mysqli->error); 
}
?>

<div class="admin-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap;">
        <h2>Product List</h2>
        <a href="add_product.php" class="btn btn-success"><i class="fas fa-plus"></i> Add New Product</a>
    </div>

    <?php display_message(); ?>

    <div style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
               
            </thead>
            <tbody>
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product):
                        $is_deleted = $product['is_deleted'] == 1;
                        $row_style = '';
                        if ($is_deleted) {
                            $row_style = 'opacity: 0.5; text-decoration: line-through; background-color: #ffebee;'; // Style for deleted rows
                        } elseif ($product['is_active'] == 0) {
                            $row_style = 'opacity: 0.7; background-color: #f8f9fa;';
                        }
                    ?>
                        <tr style="<?php echo $row_style; ?>">
                            <td>
                                <img src="<?php echo BASE_URL . htmlspecialchars($product['image_url'] ?: 'img/placeholder.png'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border: 1px solid #eee;">
                            </td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['category_name'] ?: 'N/A'); ?></td>
                            <td><?php echo formatCurrency($product['price']); ?></td>
                            <td><?php echo $product['stock']; ?></td>
                            <td>
                                <?php if ($product['is_active'] == 1): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary" style="background-color: #6c757d;">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_deleted): ?>
                                    <span class="badge badge-danger">Yes</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary" style="background-color: #adb5bd;">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                               
                                <a href="edit_product.php?id=<?php echo $product['product_id']; ?>"
                                   class="btn btn-sm btn-primary <?php echo $is_deleted ? 'disabled' : ''; ?>"
                                   title="Edit Product"
                                   <?php echo $is_deleted ? 'onclick="return false;" style="pointer-events: none; opacity: 0.5;"' : ''; ?>>
                                    <i class="fas fa-edit"></i> Edit
                                </a>

                         
                                <form action="manage_products.php" method="post" style="display: inline-block;"
                                      onsubmit="return <?php echo $is_deleted ? 'false' : "confirm('Are you sure you want to " . ($product['is_active'] ? 'DEACTIVATE' : 'ACTIVATE') . " this product?');" ?>">
                                     <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                     <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                     <input type="hidden" name="current_status" value="<?php echo $product['is_active']; ?>">
                                     <input type="hidden" name="action" value="toggle_active">
                                     <?php if ($product['is_active'] == 1): ?>
                                        <button type="submit" class="btn btn-sm btn-warning" title="Deactivate Product" <?php echo $is_deleted ? 'disabled style="opacity: 0.5;"' : ''; ?>>
                                             <i class="fas fa-toggle-off"></i> Deactivate
                                        </button>
                                     <?php else: ?>
                                         <button type="submit" class="btn btn-sm btn-success" title="Activate Product" <?php echo $is_deleted ? 'disabled style="opacity: 0.5;"' : ''; ?>>
                                             <i class="fas fa-toggle-on"></i> Activate
                                         </button>
                                     <?php endif; ?>
                                </form>

                          
                                <form action="manage_products.php" method="post" style="display: inline-block;"
                                      onsubmit="return confirm('Are you sure you want to <?php echo $is_deleted ? 'RESTORE' : 'DELETE'; ?> this product? <?php echo $is_deleted ? '' : '(This is reversible)'; ?>');">
                                     <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                     <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                     <?php if ($is_deleted): ?>
                                         <input type="hidden" name="action" value="restore_product">
                                         <button type="submit" class="btn btn-sm btn-info" title="Restore Product">
                                             <i class="fas fa-undo"></i> Restore
                                         </button>
                                     <?php else: ?>
                                         <input type="hidden" name="action" value="delete_product">
                                         <button type="submit" class="btn btn-sm btn-danger" title="Delete Product">
                                             <i class="fas fa-trash"></i> Delete
                                         </button>
                                     <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr> <td colspan="8" style="text-align: center;">No products found. <a href="add_product.php">Add one now!</a></td> </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once 'admin_footer.php';
?>