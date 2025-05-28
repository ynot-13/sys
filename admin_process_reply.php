<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/admin_auth.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect(BASE_URL . 'admin/admin_manage_messages.php');
}
if (!isset($mysqli) || !$mysqli instanceof mysqli || $mysqli->connect_error) {
    set_message("Database connection error. Cannot send reply.", "error");
    $redirect_back = isset($_POST['parent_message_id']) ? 'admin_view_message.php?thread_id=' . $_POST['parent_message_id'] : 'admin_manage_messages.php';
    redirect(BASE_URL . 'admin/' . $redirect_back);
}

if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    set_message('Invalid security token. Please try again.', 'error');
    $redirect_back = isset($_POST['parent_message_id']) ? 'admin_view_message.php?thread_id=' . $_POST['parent_message_id'] : 'admin_manage_messages.php';
    redirect(BASE_URL . 'admin/' . $redirect_back);
}

$errors = [];
$form_data = $_POST;

$sender_id = $current_admin_id;
$recipient_id = filter_input(INPUT_POST, 'recipient_id', FILTER_VALIDATE_INT);
$parent_message_id = filter_input(INPUT_POST, 'parent_message_id', FILTER_VALIDATE_INT);
$subject = sanitize_input($_POST['subject'] ?? '');
$body = sanitize_input($_POST['body'] ?? '');

$redirect_back_url = BASE_URL . 'admin/' . ($parent_message_id ? 'admin_view_message.php?thread_id=' . $parent_message_id : 'admin_manage_messages.php');

if (!$recipient_id || $recipient_id <= 0) {
    $errors['recipient'] = "Invalid recipient (user) ID passed.";
} else {
    $stmt_check = $mysqli->prepare("SELECT user_id, role FROM users WHERE user_id = ?");
    if ($stmt_check) {
        $rec_id_var = $recipient_id;
        $stmt_check->bind_param("i", $rec_id_var);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($user_data = $result_check->fetch_assoc()) {
            if ($user_data['role'] === 'user') {
               
            } else {
                 $errors['recipient'] = "Recipient is an admin, not a regular user.";
            }
        } else {
            $errors['recipient'] = "Recipient user not found in database.";
        }
        $stmt_check->close();
    } else {
        $errors['database'] = "Error checking recipient existence.";
    }
}

if (!$parent_message_id || $parent_message_id <=0) {
     $errors['parent'] = "Invalid message thread reference for reply.";
     $redirect_back_url = BASE_URL . 'admin/admin_manage_messages.php';
}

if (empty($body)) {
    $errors['body'] = "Reply body cannot be empty.";
}

if (empty($errors)) {
    $sql_insert = "INSERT INTO messages (parent_message_id, sender_id, receiver_id, subject, body, sent_at) VALUES (?, ?, ?, ?, ?, NOW())";
    if ($stmt_insert = $mysqli->prepare($sql_insert)) {
        $stmt_insert->bind_param("iiiss", $parent_message_id, $sender_id, $recipient_id, $subject, $body);
        if ($stmt_insert->execute()) {
            set_message("Reply sent successfully!", "success");
            $stmt_insert->close(); $mysqli->close();
            redirect(BASE_URL . 'admin/admin_view_message.php?thread_id=' . $parent_message_id);
        } else {
            $errors['database'] = "Failed to send reply (Execute).";
            error_log("Admin Reply Insert Execute Error: " . $stmt_insert->error);
        }
        if(isset($stmt_insert) && $stmt_insert instanceof mysqli_stmt) $stmt_insert->close();
    } else {
        $errors['database'] = "Failed to prepare reply.";
        error_log("Admin Reply Insert Prepare Error: " . $mysqli->error);
    }
}

if (!empty($errors)) {
    $_SESSION['admin_reply_errors'] = $errors;
    $_SESSION['admin_reply_form_data'] = ['body' => $body];
    if(isset($mysqli) && $mysqli instanceof mysqli && $mysqli->thread_id) { $mysqli->close(); }
    redirect($redirect_back_url);
}

if (isset($mysqli) && $mysqli instanceof mysqli) { $mysqli->close(); }
exit();
?>