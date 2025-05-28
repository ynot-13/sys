<?php
$page_title = "Shopping Cart";
require_once 'includes/header.php';

$cart_items_data = [];
$cart_total_calculated = 0;
$is_guest_cart = !isLoggedIn();
$user_id_cart = !$is_guest_cart ? $_SESSION['user_id'] : null;
$session_id_cart = session_id();
$all_cart_items_valid = true;

$sql_cart_fetch = "SELECT
            c.cart_id,
            c.quantity,
            p.product_id,
            p.name,
            p.price AS regular_price,
            p.image_url,
            p.stock,
            p.is_active,
            p.is_on_flash_sale,
            p.flash_sale_price,
            p.flash_sale_start_date,
            p.flash_sale_end_date
        FROM cart c
        JOIN products p ON c.product_id = p.product_id ";

if ($is_guest_cart) {
    $sql_cart_fetch .= " WHERE c.session_id = ? AND c.user_id IS NULL";
    $stmt_cart = $mysqli->prepare($sql_cart_fetch);
    if ($stmt_cart) {
        $stmt_cart->bind_param("s", $session_id_cart);
    }
} else {
    $sql_cart_fetch .= " WHERE c.user_id = ?";
    $stmt_cart = $mysqli->prepare($sql_cart_fetch);
    if ($stmt_cart) {
        $stmt_cart->bind_param("i", $user_id_cart);
    }
}

if (isset($stmt_cart) && $stmt_cart) {
    if ($stmt_cart->execute()) {
        $result_cart_fetch = $stmt_cart->get_result();
        while ($row_cart = $result_cart_fetch->fetch_assoc()) {
            $item_current_price = $row_cart['regular_price'];
            $is_item_on_sale_now = false;

            $condition_active = !empty($row_cart['is_active']);
            $condition_is_on_sale_flag = !empty($row_cart['is_on_flash_sale']);
            $start_timestamp = $row_cart['flash_sale_start_date'] ? strtotime($row_cart['flash_sale_start_date']) : null;
            $end_timestamp = $row_cart['flash_sale_end_date'] ? strtotime($row_cart['flash_sale_end_date']) : null;
            $current_php_time = time();
            $condition_start_date_ok = ($start_timestamp === null || ($start_timestamp !== false && $start_timestamp <= $current_php_time));
            $condition_end_date_ok = ($end_timestamp === null || ($end_timestamp !== false && $end_timestamp >= $current_php_time));
            $condition_price_valid = ($row_cart['flash_sale_price'] !== null && is_numeric($row_cart['flash_sale_price']) && is_numeric($row_cart['regular_price']) && (float)$row_cart['flash_sale_price'] < (float)$row_cart['regular_price']);
            $all_conditions_met_for_sale = $condition_active && $condition_is_on_sale_flag && $condition_start_date_ok && $condition_end_date_ok && $condition_price_valid;

            if ($all_conditions_met_for_sale) {
                $item_current_price = $row_cart['flash_sale_price'];
                $is_item_on_sale_now = true;
            } elseif (!$condition_active) {
                $row_cart['is_invalid_item'] = true;
                $item_current_price = 0; 
                $all_cart_items_valid = false;
            }
            
            if ($condition_active && $row_cart['quantity'] > $row_cart['stock']) {
                 $row_cart['exceeds_stock'] = true;
                 $all_cart_items_valid = false;
            }

            $row_cart['effective_price'] = $item_current_price;
            $row_cart['is_item_on_sale_now'] = $is_item_on_sale_now;
            $cart_items_data[] = $row_cart;

            if (!isset($row_cart['is_invalid_item'])) {
                $cart_total_calculated += $item_current_price * $row_cart['quantity'];
            }
        }
        if (isset($result_cart_fetch) && $result_cart_fetch instanceof mysqli_result) $result_cart_fetch->free();
        $stmt_cart->close();
    } else {
        $all_cart_items_valid = false;
        error_log("Cart fetch execute error: " . $stmt_cart->error);
    }
} else {
    $all_cart_items_valid = false;
    error_log("Cart fetch prepare error: " . $mysqli->error);
}

?>

<div class="cart-page">
    <h1></h1>
    <?php display_message(); ?>

    <?php if (!empty($cart_items_data)): ?>
        <form action="update_cart.php" method="post">
         <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <table class="cart-table">
                <thead>
                    <tr>
                       
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart_items_data as $item_display):
                        $item_subtotal = $item_display['effective_price'] * $item_display['quantity'];
                        $is_display_item_invalid = isset($item_display['is_invalid_item']) && $item_display['is_invalid_item'];
                        $item_exceeds_stock = isset($item_display['exceeds_stock']) && $item_display['exceeds_stock'];
                    ?>
                        <tr class="<?php if($is_display_item_invalid) echo 'table-danger-row'; elseif($item_exceeds_stock) echo 'table-warning-row'; elseif($item_display['is_item_on_sale_now']) echo 'table-sale-row'; ?>">
                            <td data-label="Product Image">
                                <a href="product_details.php?id=<?php echo $item_display['product_id']; ?>">
                                    <img src="<?php echo BASE_URL . htmlspecialchars($item_display['image_url'] ?? 'img/placeholder.png'); ?>" alt="<?php echo htmlspecialchars($item_display['name']); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                                </a>
                            </td>
                             <td data-label="Product Name">
                                <a href="product_details.php?id=<?php echo $item_display['product_id']; ?>">
                                    <?php echo htmlspecialchars($item_display['name']); ?>
                                </a>
                                <?php if ($item_display['is_item_on_sale_now'] && !$is_display_item_invalid): ?>
                                    <br><small style="color: green; font-weight: bold;"> Flash Sale!</small>
                                <?php endif; ?>
                                <?php if ($is_display_item_invalid): ?>
                                    <br><small style="color: red; font-weight: bold;">Item unavailable.</small>
                                <?php endif; ?>
                                <?php if ($item_exceeds_stock && !$is_display_item_invalid): ?>
                                    <br><small style="color: orange; font-weight: bold;">Quantity exceeds stock (<?php echo $item_display['stock']; ?> available).</small>
                                <?php endif; ?>
                             </td>
                            <td data-label="Price">
                                <?php if ($item_display['is_item_on_sale_now'] && !$is_display_item_invalid): ?>
                                    <del><?php echo formatCurrency($item_display['regular_price']); ?></del><br>
                                    <strong style="color: #dc3545;"><?php echo formatCurrency($item_display['effective_price']); ?></strong>
                                <?php else: ?>
                                    <?php echo formatCurrency($item_display['effective_price']); ?>
                                <?php endif; ?>
                            </td>
                            <td data-label="Quantity">
                                <?php if (!$is_display_item_invalid): ?>
                                <input type="number" name="quantity[<?php echo $item_display['cart_id']; ?>]" value="<?php echo $item_display['quantity']; ?>" min="0" max="<?php echo $item_display['stock']; ?>" class="form-control quantity-input" style="width: 70px; display: inline-block; text-align:center;">
                                <?php else: echo htmlspecialchars($item_display['quantity']); endif; ?>
                            </td>
                            <td data-label="Subtotal"><?php echo $is_display_item_invalid ? 'N/A' : formatCurrency($item_subtotal); ?></td>
                            <td data-label="Action" class="cart-actions">
                                <a href="remove_from_cart.php?cart_id=<?php echo $item_display['cart_id']; ?>&token=<?php echo htmlspecialchars($csrf_token); ?>" class="btn btn-danger btn-sm" title="Remove Item" onclick="return confirm('Are you sure you want to remove this item?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

             <div style="text-align: right; margin-bottom: 20px;">
                <button type="submit" name="update_cart_action" class="btn btn-secondary">Update Quantities</button>
            </div>
        </form>

        <div class="cart-summary">
            <h3>Cart Summary</h3>
            <p>
                <span>Subtotal:</span>
                <strong><?php echo formatCurrency($cart_total_calculated); ?></strong>
            </p>
            <p>
                <span>Shipping:</span>
                <strong>Calculated at checkout</strong>
            </p>
            <hr>
            <p>
                <span>Total:</span>
                <strong style="font-size: 1.4em;"><?php echo formatCurrency($cart_total_calculated); ?></strong>
            </p>
            <?php if (!$all_cart_items_valid): ?>
                <div style="color: red; margin-bottom: 10px; font-weight: bold;">
                    Please correct issues in your cart (e.g., unavailable items or quantity exceeding stock) before proceeding.
                </div>
            <?php endif; ?>
            <a href="checkout.php" class="btn <?php echo !$all_cart_items_valid ? 'disabled-checkout' : ''; ?>" 
               style="width: 100%; <?php echo !$all_cart_items_valid ? 'pointer-events: none; background-color: #ccc; border-color: #ccc;' : ''; ?>"
               <?php if(!$all_cart_items_valid) echo 'onclick="alert(\'Please resolve cart issues before proceeding to checkout.\'); return false;"'; ?>>
               Proceed to Checkout
            </a>
             <a href="products.php" class="btn btn-secondary" style="width: 100%; margin-top: 10px; background-color: #aaa;">Continue Shopping</a>
        </div>
         <div style="clear: both;"></div>

    <?php else: ?>
        <div class="empty-cart" style="text-align: center; padding: 40px 0;">
            <i class="fas fa-shopping-cart" style="font-size: 3em; color: #ccc; margin-bottom: 15px;"></i>
            <p style="font-size: 1.2em;">Your cart is currently empty.</p>
            <a href="products.php" class="btn" style="padding: 10px 20px; font-size: 1em;">Start Shopping</a>
        </div>
    <?php endif; ?>

</div>
<style>
    .table-danger-row td { background-color: #f8d7da !important; }
    .table-warning-row td { background-color: #fff3cd !important; }
    .table-sale-row td { }
    .disabled-checkout {
        opacity: 0.65;
        cursor: not-allowed;
    }
</style>
<?php
require_once 'includes/footer.php';
?>