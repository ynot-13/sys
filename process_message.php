<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) { set_message("Please log in first.", "error"); redirect('login.php'); }

if ($_SERVER["REQUEST_METHOD"] !== "POST") { redirect('messages.php'); }

if (!isset($mysqli) || !$mysqli instanceof mysqli || $mysqli->connect_error) { set_message("Database connection error.", "error"); redirect('compose_message.php'); }

if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) { set_message('Invalid security token.', 'error'); redirect('compose_message.php'); }

$errors = [];
$form_data = $_POST;

$sender_id = $_SESSION['user_id'];
$recipient_id = filter_input(INPUT_POST, 'recipient_id', FILTER_VALIDATE_INT);
$parent_message_id = filter_input(INPUT_POST, 'parent_message_id', FILTER_VALIDATE_INT);
$subject = sanitize_input($_POST['subject'] ?? '');
$body = sanitize_input($_POST['body'] ?? '');
$parent_id_check = ($parent_message_id === false || $parent_message_id <= 0) ? null : $parent_message_id;

if (!$recipient_id || $recipient_id <= 0) {
    $errors['recipient'] = "Invalid recipient specified.";
} else {
    $stmt_check = $mysqli->prepare("SELECT user_id FROM users WHERE user_id = ?");
    if ($stmt_check) {
        $rec_id_var = $recipient_id;
        $stmt_check->bind_param("i", $rec_id_var); $stmt_check->execute(); $stmt_check->store_result();
        if ($stmt_check->num_rows == 0) { $errors['recipient'] = "Recipient user does not exist."; }
        $stmt_check->close();
    } else { $errors['database'] = "Error checking recipient."; }
}

if (!$parent_id_check && empty($subject)) { $errors['subject'] = "Subject is required for new messages."; }

if (empty($body)) { $errors['body'] = "Message body cannot be empty."; }

if (empty($errors)) {
    $subject_to_save = ($parent_id_check && trim(strtolower($subject)) == 're:') ? null : $subject; 
    $sql_insert = "INSERT INTO messages (parent_message_id, sender_id, receiver_id, subject, body, sent_at) VALUES (?, ?, ?, ?, ?, NOW())";
    if ($stmt_insert = $mysqli->prepare($sql_insert)) {
        $stmt_insert->bind_param("iiiss", $parent_id_check, $sender_id, $recipient_id, $subject_to_save, $body);
        if ($stmt_insert->execute()) {
            set_message("Message sent successfully!", "success");
            unset($_SESSION['compose_errors'], $_SESSION['compose_form_data']);
            $stmt_insert->close(); $mysqli->close();
            redirect('messages.php'); 
        } else { $errors['database'] = "Failed to send message (Execute)."; error_log("Msg Insert Execute Err: " . $stmt_insert->error); }
        if(isset($stmt_insert) && $stmt_insert instanceof mysqli_stmt) $stmt_insert->close();
    } else { $errors['database'] = "Failed to prepare message."; error_log("Msg Insert Prepare Err: " . $mysqli->error); }
}

if (!empty($errors)) {
    $_SESSION['compose_errors'] = $errors;
    $_SESSION['compose_form_data'] = $form_data;
    $redirect_url = 'compose_message.php'; 
    $mysqli->close();
    redirect($redirect_url);
}

if (isset($mysqli) && $mysqli instanceof mysqli) { $mysqli->close(); }
exit();
?>
