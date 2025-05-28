<?php
$page_title = "Checkout";
require_once 'includes/header.php';

$shipping_fee = 30.00;

$cart_items_for_checkout = [];
$cart_subtotal = 0;
$is_guest_checkout = !isLoggedIn();
$user_id_checkout = !$is_guest_checkout ? $_SESSION['user_id'] : null;
$session_id_checkout = session_id();
$all_items_checkout_valid = true;


$sql_cart_checkout = "SELECT
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

if ($is_guest_checkout) {
    $sql_cart_checkout .= " WHERE c.session_id = ? AND c.user_id IS NULL";
    $stmt_checkout_cart = $mysqli->prepare($sql_cart_checkout);
    if ($stmt_checkout_cart) {
        $stmt_checkout_cart->bind_param("s", $session_id_checkout);
    }
} else {
    $sql_cart_checkout .= " WHERE c.user_id = ?";
    $stmt_checkout_cart = $mysqli->prepare($sql_cart_checkout);
    if ($stmt_checkout_cart) {
        $stmt_checkout_cart->bind_param("i", $user_id_checkout);
    }
}

if (isset($stmt_checkout_cart) && $stmt_checkout_cart) {
    if ($stmt_checkout_cart->execute()) {
        $result_checkout_cart = $stmt_checkout_cart->get_result();
        while ($row_checkout = $result_checkout_cart->fetch_assoc()) {
            $item_price_checkout = $row_checkout['regular_price'];
            $is_item_on_sale_checkout = false;

            if (
                $row_checkout['is_active'] &&
                $row_checkout['is_on_flash_sale'] &&
                ($row_checkout['flash_sale_start_date'] == null || strtotime($row_checkout['flash_sale_start_date']) <= time()) &&
                ($row_checkout['flash_sale_end_date'] == null || strtotime($row_checkout['flash_sale_end_date']) >= time()) &&
                $row_checkout['flash_sale_price'] !== null && (float)$row_checkout['flash_sale_price'] < (float)$row_checkout['regular_price']
            ) {
                $item_price_checkout = $row_checkout['flash_sale_price'];
                $is_item_on_sale_checkout = true;
            } elseif (!$row_checkout['is_active']) {
                $row_checkout['is_invalid_checkout_item'] = true;
                $item_price_checkout = 0;
                $all_items_checkout_valid = false;
            }

            if ($row_checkout['is_active'] && $row_checkout['quantity'] > $row_checkout['stock']) {
                $row_checkout['exceeds_stock_checkout'] = true;
                $all_items_checkout_valid = false;
                 set_message("Item '" . htmlspecialchars($row_checkout['name']) . "' quantity exceeds stock. Please update your cart.", "error");
            }
            
            if (isset($row_checkout['is_invalid_checkout_item'])) {
                 set_message("Item '" . htmlspecialchars($row_checkout['name']) . "' is no longer available. Please remove it from your cart.", "error");
            }


            $row_checkout['effective_price'] = $item_price_checkout;
            $row_checkout['is_item_on_sale_now_checkout'] = $is_item_on_sale_checkout;
            $cart_items_for_checkout[] = $row_checkout;

            if (!isset($row_checkout['is_invalid_checkout_item'])) {
                $cart_subtotal += $item_price_checkout * $row_checkout['quantity'];
            }
        }
        if (isset($result_checkout_cart) && $result_checkout_cart instanceof mysqli_result) $result_checkout_cart->free();
        $stmt_checkout_cart->close();
    } else {
        $all_items_checkout_valid = false;
        error_log("Error executing checkout cart query: " . $stmt_checkout_cart->error);
        set_message("Error retrieving cart items for checkout. Please try again.", "error");
        redirect('cart.php');
    }
} else {
    $all_items_checkout_valid = false;
    error_log("Error preparing checkout cart query: " . $mysqli->error);
    set_message("An error occurred while preparing your checkout. Please try again.", "error");
    redirect('cart.php');
}


if (empty($cart_items_for_checkout) || !$all_items_checkout_valid) {
    if (!isset($_SESSION['flash_message'])) { 
        set_message("Your cart is empty or contains invalid items. Please review your cart.", "warning");
    }
    redirect('cart.php');
}

$grand_total_checkout = $cart_subtotal + $shipping_fee;

$user_full_name_checkout = '';
$user_email_checkout = '';
if (!$is_guest_checkout) {
    $stmt_user_checkout = $mysqli->prepare("SELECT full_name, email FROM users WHERE user_id = ?");
    if ($stmt_user_checkout) {
        $stmt_user_checkout->bind_param("i", $user_id_checkout);
        if ($stmt_user_checkout->execute()) {
            $result_user_checkout = $stmt_user_checkout->get_result();
            if ($user_data_checkout = $result_user_checkout->fetch_assoc()) {
                $user_full_name_checkout = $user_data_checkout['full_name'] ?? '';
                $user_email_checkout = $user_data_checkout['email'] ?? '';
            }
            if ($result_user_checkout instanceof mysqli_result) $result_user_checkout->free();
            $stmt_user_checkout->close();
        } else { error_log("Error executing user query for checkout: " . $stmt_user_checkout->error); }
    } else { error_log("Error preparing user query for checkout: " . $mysqli->error); }
}

$errors_checkout = $_SESSION['checkout_errors'] ?? [];
$form_data_checkout = $_SESSION['checkout_form_data'] ?? [];
unset($_SESSION['checkout_errors'], $_SESSION['checkout_form_data']);

$selected_payment_method_checkout = $form_data_checkout['payment_method'] ?? 'GCash';

?>

<div class="checkout-page container">
    <h1></h1>

    <div class="checkout-layout">
        <div class="checkout-form">
            <?php display_message(); ?>
            <form action="place_order.php" method="post" id="checkout-form">
                 <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                 <input type="hidden" name="shipping_fee" value="<?php echo htmlspecialchars($shipping_fee); ?>">

                <h2>Shipping Information</h2>
                <?php if ($is_guest_checkout): ?>
                    <p style="margin-bottom: 20px; font-size: 0.9em; background-color: var(--info-light-bg); padding: 10px; border-radius: var(--border-radius); border: 1px solid var(--info-color);">
                        <a href="login.php?redirect=checkout.php" style="font-weight: bold;">Login</a> or
                        <a href="register.php" style="font-weight: bold;">Register</a> for a faster checkout.
                    </p>
                    <div class="form-group">
                        <label for="guest_name">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="guest_name" id="guest_name" class="form-control <?php echo isset($errors_checkout['guest_name']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data_checkout['guest_name'] ?? ''); ?>" required>
                         <?php if (isset($errors_checkout['guest_name'])): ?><div class="invalid-feedback d-block"><?php echo $errors_checkout['guest_name']; ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="guest_email">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="guest_email" id="guest_email" class="form-control <?php echo isset($errors_checkout['guest_email']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data_checkout['guest_email'] ?? ''); ?>" required>
                         <?php if (isset($errors_checkout['guest_email'])): ?><div class="invalid-feedback d-block"><?php echo $errors_checkout['guest_email']; ?></div><?php endif; ?>
                         <small class="form-text text-muted">Order confirmation will be sent here.</small>
                    </div>
                <?php else: ?>
                    <p style="margin-bottom: 20px;">Shipping details for: <strong><?php echo htmlspecialchars($user_full_name_checkout ?: ($_SESSION['username'] ?? 'User')); ?></strong> (<?php echo htmlspecialchars($user_email_checkout); ?>)</p>
                <?php endif; ?>

                <div class="form-group">
                    <label for="shipping_address">Full Shipping Address <span class="text-danger">*</span></label>
                    <textarea name="shipping_address" id="shipping_address" rows="4" class="form-control <?php echo isset($errors_checkout['shipping_address']) ? 'is-invalid' : ''; ?>" placeholder="Street Address, Barangay, City/Municipality, Province, Postal Code" required><?php echo htmlspecialchars($form_data_checkout['shipping_address'] ?? ''); ?></textarea>
                     <?php if (isset($errors_checkout['shipping_address'])): ?><div class="invalid-feedback d-block"><?php echo $errors_checkout['shipping_address']; ?></div><?php endif; ?>
                </div>
                 <div class="form-group">
                    <label for="phone_number">Phone Number <span class="text-danger">*</span></label>
                    <input type="tel" name="phone_number" id="phone_number" class="form-control <?php echo isset($errors_checkout['phone_number']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data_checkout['phone_number'] ?? ''); ?>" placeholder="e.g., 09171234567" required pattern="^(09|\+639)\d{9}$">
                     <?php if (isset($errors_checkout['phone_number'])): ?><div class="invalid-feedback d-block"><?php echo $errors_checkout['phone_number']; ?></div><?php endif; ?>
                     <small class="form-text text-muted">For delivery coordination.</small>
                </div>
                 <hr>
                 <h2>Payment Method <span class="text-danger">*</span></h2>
                 <div class="payment-options">
                    <div class="payment-option form-group">
                        <input type="radio" name="payment_method" id="payment_gcash" value="GCash" <?php echo ($selected_payment_method_checkout === 'GCash') ? 'checked' : ''; ?>>
                        <label for="payment_gcash" class="payment-label">
                            <img src="<?php echo BASE_URL; ?>img/Gcash.PNG" alt="GCash Logo" class="payment-logo">
                            <span>GCash</span>
                        </label>
                        <p class="payment-description">Pay securely using GCash.</p>
                    </div>
                    <div class="payment-option form-group">
                        <input type="radio" name="payment_method" id="payment_cod" value="COD" <?php echo ($selected_payment_method_checkout === 'COD') ? 'checked' : ''; ?>>
                        <label for="payment_cod" class="payment-label">
                             <i class="fas fa-money-bill-wave payment-icon"></i>
                            <span>Cash on Delivery (COD)</span>
                        </label>
                         <p class="payment-description">Pay with cash upon delivery.</p>
                    </div>
                     <?php if (isset($errors_checkout['payment_method'])): ?>
                         <div class="alert alert-danger p-2 mt-2" style="font-size: 0.9em;"><?php echo $errors_checkout['payment_method']; ?></div>
                     <?php endif; ?>
                 </div>
                <div class="form-group" style="margin-top: 30px;">
                    <button type="submit" name="place_order" class="btn btn-primary w-100">Place Order</button>
                </div>
            </form>
        </div>

        <div class="checkout-summary">
            <h3>Order Summary</h3>
            <ul>
                <?php foreach ($cart_items_for_checkout as $item_checkout): ?>
                    <li>
                        <span><?php echo htmlspecialchars($item_checkout['name']); ?> (x<?php echo $item_checkout['quantity']; ?>)</span>
                        <span>
                            <?php if (isset($item_checkout['is_item_on_sale_now_checkout']) && $item_checkout['is_item_on_sale_now_checkout']): ?>
                                <del style="font-size:0.8em; color:#888;"><?php echo formatCurrency($item_checkout['regular_price'] * $item_checkout['quantity']); ?></del>
                                <strong style="color:red;"><?php echo formatCurrency($item_checkout['effective_price'] * $item_checkout['quantity']); ?></strong>
                            <?php else: ?>
                                <?php echo formatCurrency($item_checkout['effective_price'] * $item_checkout['quantity']); ?>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <hr>
            <p>
                <span>Subtotal:</span>
                <span><?php echo formatCurrency($cart_subtotal); ?></span>
            </p>
            <p>
                <span>Shipping Fee:</span>
                <span><?php echo formatCurrency($shipping_fee); ?></span>
            </p>
            <hr>
            <p class="total">
                <span>Total:</span>
                <strong><?php echo formatCurrency($grand_total_checkout); ?></strong>
            </p>
             <hr>
             <p style="font-size: 0.85em; text-align: center; color: #6c757d;">By placing your order, you agree to our <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>.</p>
        </div>
    </div>
</div>

<style>
    .checkout-layout { display: flex; flex-wrap: wrap; gap: 30px; align-items: flex-start; }
    .checkout-form { flex: 2; min-width: 320px; }
    .checkout-summary { flex: 1; min-width: 280px; background-color: var(--white-color); padding: 25px; border-radius: var(--border-radius); box-shadow: var(--box-shadow-light); border: 1px solid var(--border-color); position: sticky; top: calc(var(--header-height, 60px) + 20px); }
    .checkout-summary h3 { margin-bottom: 20px; text-align: center; color: var(--heading-color); font-weight: 600; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
    .checkout-summary ul { margin-bottom: 15px; padding-left: 0; list-style: none;}
    .checkout-summary li { display: flex; justify-content: space-between; padding: 8px 0; font-size: 0.95rem; }
    .checkout-summary li span:first-child { color: var(--text-color); max-width: 75%; }
    .checkout-summary li span:last-child { font-weight: 500; color: var(--heading-color); text-align: right;}
    .checkout-summary .total { font-size: 1.3rem; font-weight: bold; margin-top: 15px; padding-top: 15px; border-top: 2px solid var(--primary-color); display: flex; justify-content: space-between; color: var(--primary-color); }
    .checkout-summary .total strong { font-weight: bold; }
    .payment-options { margin-top: 1rem; }
    .payment-option { border: 1px solid var(--border-color); padding: 15px; border-radius: var(--border-radius); margin-bottom: 15px; cursor: pointer; transition: border-color 0.3s ease, box-shadow 0.3s ease; }
    .payment-option:has(input:checked) { border-color: var(--primary-color); box-shadow: 0 0 0 1px var(--primary-color); }
    .payment-option input[type="radio"] { margin-right: 10px; vertical-align: middle; }
    .payment-label { display: flex; align-items: center; font-weight: 600; color: var(--heading-color); cursor: pointer; margin-bottom: 5px; }
    .payment-logo { height: 25px; width: auto; margin-right: 10px; vertical-align: middle; }
    .payment-icon { font-size: 1.5em; margin-right: 10px; color: var(--primary-color); vertical-align: middle; width: 30px; text-align: center; }
    .payment-description { font-size: 0.85em; color: var(--text-muted, #6c757d); margin-left: 30px; margin-top: 5px; margin-bottom: 0; line-height: 1.4; }
     @media (max-width: 768px) {
        .checkout-layout { flex-direction: column; }
        .checkout-summary { position: static; width: 100%; margin-top: 30px; }
         .payment-description { margin-left: 0; }
    }
</style>

<?php
require_once 'includes/footer.php';
?>