<?php

function isLoggedIn(): bool {
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

function isAdmin(): bool {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function sanitize_input(?string $data): string {
    if ($data === null) {
        return '';
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function redirect(string $url): void {
    if (!headers_sent()) {
         header("Location: " . $url);
    } else {
        echo "<script>window.location.href='" . addslashes($url) . "';</script>";
        error_log("Redirect failed: Headers already sent. Tried redirecting to: " . $url);
    }
    exit();
}

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            error_log("Failed to generate secure CSRF token: " . $e->getMessage());
            $_SESSION['csrf_token'] = md5(uniqid(rand(), true) . microtime());
        }
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $submitted_token): bool {
    if (!isset($_SESSION['csrf_token']) || empty($submitted_token)) {
        return false;
    }
    $result = hash_equals($_SESSION['csrf_token'], $submitted_token);
    return $result;
}

function set_message(string $message, string $type = 'info'): void {
    $allowed_types = ['success', 'error', 'warning', 'info'];
    $type = in_array($type, $allowed_types) ? $type : 'info';
    $_SESSION['flash_message'] = ['message' => $message, 'type' => $type];
}

function display_message(): void {
    if (isset($_SESSION['flash_message'])) {
        $message_data = $_SESSION['flash_message'];
        $message = htmlspecialchars($message_data['message'], ENT_QUOTES, 'UTF-8');
        $type = htmlspecialchars($message_data['type'], ENT_QUOTES, 'UTF-8');

        $alertClass = 'alert-info';
        $iconClass = 'fas fa-info-circle';
        switch ($type) {
            case 'success': $alertClass = 'alert-success'; $iconClass = 'fas fa-check-circle'; break;
            case 'error':   $alertClass = 'alert-danger';  $iconClass = 'fas fa-exclamation-triangle'; break;
            case 'warning': $alertClass = 'alert-warning'; $iconClass = 'fas fa-exclamation-circle'; break;
        }

        echo "<div class='alert {$alertClass}' role='alert'>";
        echo "<i class='{$iconClass}' style='margin-right: 10px;'></i> ";
        echo $message;
        echo "</div>";

        unset($_SESSION['flash_message']);
    }
}

function formatCurrency($amount, int $decimals = 2): string {
    if (!is_numeric($amount)) {
        return '';
    }
    $symbol = defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : 'P';
    return $symbol . number_format((float)$amount, $decimals);
}

function addToCart($product_id, $quantity, $mysqli) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if (!filter_var($product_id, FILTER_VALIDATE_INT) || $product_id <= 0) {
        return ['success' => false, 'message' => 'Invalid product ID.', 'product_name' => null];
    }
    if (!filter_var($quantity, FILTER_VALIDATE_INT) || $quantity <= 0) {
        return ['success' => false, 'message' => 'Invalid quantity.', 'product_name' => null];
    }

    $stmt = $mysqli->prepare("SELECT name, price, image_url, stock, is_active FROM products WHERE product_id = ?");
    if (!$stmt) {
        error_log("addToCart prepare error: " . $mysqli->error);
        return ['success' => false, 'message' => 'Error preparing to fetch product details.', 'product_name' => null];
    }
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();

    if (!$product) {
        return ['success' => false, 'message' => "Product (ID: {$product_id}) not found.", 'product_name' => null];
    }

    $product_name_for_msg = htmlspecialchars($product['name']);

    if (!$product['is_active']) {
        return ['success' => false, 'message' => "Product '{$product_name_for_msg}' is no longer available.", 'product_name' => $product_name_for_msg];
    }

    $quantity_to_add = $quantity;

    if (isset($_SESSION['cart'][$product_id])) {
        $new_quantity = $_SESSION['cart'][$product_id]['quantity'] + $quantity_to_add;
        $_SESSION['cart'][$product_id]['quantity'] = $new_quantity;
        $_SESSION['cart'][$product_id]['price'] = $product['price'];
    } else {
        $_SESSION['cart'][$product_id] = [
            'product_id' => $product_id,
            'name'       => $product['name'],
            'price'      => $product['price'],
            'quantity'   => $quantity_to_add,
            'image_url'  => $product['image_url']
        ];
    }
    return ['success' => true, 'message' => "'{$product_name_for_msg}' (Qty: {$quantity_to_add}) added/updated in cart.", 'product_name' => $product_name_for_msg];
}

function getCartItemCount(mysqli $db): int {
    if ($db->connect_error || !$db->thread_id) {
        error_log("getCartItemCount Error: Invalid or closed mysqli object provided.");
        return 0;
    }

    $count = 0;
    $sql = "";
    $params = [];
    $types = "";

    if (isLoggedIn()) {
        $sql = "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?";
        $params[] = $_SESSION['user_id']; $types = "i";
    } else {
        $session_id = session_id();
        if ($session_id) {
            $sql = "SELECT SUM(quantity) as total FROM cart WHERE session_id = ? AND user_id IS NULL";
            $params[] = $session_id; $types = "s";
        } else { return 0; }
    }

    if ($stmt = $db->prepare($sql)) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $count = (int) ($row['total'] ?? 0);
            }
             if ($result) $result->free();
        } else { error_log("Cart Count Execute Error: " . $stmt->error); }
        $stmt->close();
    } else { error_log("Cart Count Prepare Error: " . $db->error); }

    return $count;
}

function getUnreadMessageCount(mysqli $db): int {
    if (!isLoggedIn()) { return 0; }
    if ($db->connect_error || !$db->thread_id) {
        error_log("getUnreadMessageCount Error: Invalid or closed mysqli object provided.");
        return 0;
    }

    $count = 0;
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT COUNT(*) as c FROM messages WHERE receiver_id = ? AND read_at IS NULL AND is_deleted_receiver = 0";

    if ($stmt = $db->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $count = (int) ($row['c'] ?? 0);
            }
             if ($result) $result->free();
        } else { error_log("Unread Msg Count Execute Error: " . $stmt->error); }
        $stmt->close();
    } else { error_log("Unread Msg Count Prepare Error: " . $db->error); }

    return $count;
}

function generateCaptchaQuestion(): string {
    $num1 = rand(1, 10); $num2 = rand(1, 10);
    $op = rand(0, 1);
    $question = ($op == 0) ? "What is $num1 + $num2?" : "What is $num1 x $num2?";
    $answer = ($op == 0) ? ($num1 + $num2) : ($num1 * $num2);
    $_SESSION['captcha_answer_hash'] = md5((string)$answer . "WEALTHYS_SALT_!@#");
    return $question;
}

function verifyCaptcha(string $user_answer): bool {
    if (!isset($_SESSION['captcha_answer_hash'])) { return false; }
    $correct_hash = $_SESSION['captcha_answer_hash'];
    unset($_SESSION['captcha_answer_hash']);
    $user_answer_hash = md5((string)$user_answer . "WEALTHYS_SALT_!@#");
    return hash_equals($correct_hash, $user_answer_hash);
}