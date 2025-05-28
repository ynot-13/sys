<?php

$page_title = "Compose Message";
require_once 'includes/header.php'; 


if (!isLoggedIn()) {
    $_SESSION['redirect_url'] = 'compose_message.php' . ($_SERVER['QUERY_STRING'] ? '?'.$_SERVER['QUERY_STRING'] : '');
    set_message("Please log in to send messages.", "warning");
    redirect('login.php');
}


if (!defined('DEFAULT_ADMIN_RECIPIENT_ID') || !filter_var(DEFAULT_ADMIN_RECIPIENT_ID, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
    error_log("CRITICAL CONFIG ERROR: DEFAULT_ADMIN_RECIPIENT_ID is not defined or is invalid in config.php!");
    set_message("System configuration error (Admin Recipient ID). Cannot send messages.", "error");
    echo "<div class='account-page message-page'><h1>Configuration Error</h1><div class='alert alert-danger'>System messaging configuration error. Please contact support.</div></div>";
    require_once 'includes/footer.php';
    exit;
}


if (!isset($mysqli) || !$mysqli instanceof mysqli || $mysqli->connect_error) {
    set_message("Database connection error. Cannot compose message.", "error");
    echo "<div class='account-page message-page'><h1>Error</h1><div class='alert alert-danger'>Database connection error. Please try again later.</div></div>";
    require_once 'includes/footer.php';
    exit;
}


$user_id = $_SESSION['user_id'];
$reply_to_id = filter_input(INPUT_GET, 'reply_to', FILTER_VALIDATE_INT);
$parent_message_id = null;
$recipient_id = null;
$recipient_username = '';
$subject = '';
$recipient_exists = false;


if ($reply_to_id) {
    $page_title = "Reply to Message";
    $sql_reply = "SELECT m.sender_id, u.username as sender_username,
                         COALESCE(p.subject, m.subject) as thread_subject,
                         COALESCE(m.parent_message_id, m.message_id) as root_thread_id
                  FROM messages m
                  JOIN users u ON m.sender_id = u.user_id
                  LEFT JOIN messages p ON m.parent_message_id = p.message_id
                  WHERE m.message_id = ? AND m.receiver_id = ?";

    if ($stmt_reply = $mysqli->prepare($sql_reply)) {
        $reply_id_var = $reply_to_id; $user_id_var = $user_id;
        $stmt_reply->bind_param("ii", $reply_id_var, $user_id_var);

        if ($stmt_reply->execute()) {
            $result_reply = $stmt_reply->get_result();
            if ($original_message = $result_reply->fetch_assoc()) {
                $recipient_id = $original_message['sender_id']; 
                $recipient_username = $original_message['sender_username'];
                $parent_message_id = $original_message['root_thread_id']; 
                $subject = $original_message['thread_subject'];
                if ($subject && stripos($subject, "Re:") !== 0) { $subject = "Re: " . $subject; }
                 $page_title = htmlspecialchars($subject ?: "Reply");
                 $recipient_exists = true; 
            } else {
                set_message("Cannot reply: Original message not found or access denied.", "warning");
                $recipient_id = DEFAULT_ADMIN_RECIPIENT_ID;
            }
            $result_reply->free();
        } else { set_message("Error fetching message details for reply.", "error"); $recipient_id = DEFAULT_ADMIN_RECIPIENT_ID; }
        $stmt_reply->close();
    } else { set_message("Error preparing reply query.", "error"); $recipient_id = DEFAULT_ADMIN_RECIPIENT_ID; }
}


if (!$recipient_id) { $recipient_id = DEFAULT_ADMIN_RECIPIENT_ID; }


if ($recipient_id) {
    $stmt_rec = $mysqli->prepare("SELECT username FROM users WHERE user_id = ?");
    if($stmt_rec) {
        $rec_id_var = $recipient_id;
        $stmt_rec->bind_param("i", $rec_id_var);
        if($stmt_rec->execute()){
            $res_rec = $stmt_rec->get_result();
            if($rec_data = $res_rec->fetch_assoc()){
                $recipient_username = $rec_data['username'];
                $recipient_exists = true;
            } else {
                $recipient_exists = false; $recipient_username = "Invalid User (ID: $recipient_id)";
                set_message("Error: The intended message recipient (ID: ".htmlspecialchars($recipient_id).") does not exist.", "error");
                error_log("Compose Message Error: Recipient User ID $recipient_id not found.");
            }
            if(isset($res_rec)) $res_rec->free();
        }
        $stmt_rec->close();
    } else { set_message("Error verifying recipient.", "error"); $recipient_exists = false; }
}


$errors = $_SESSION['compose_errors'] ?? [];
$form_data = $_SESSION['compose_form_data'] ?? [];
unset($_SESSION['compose_errors'], $_SESSION['compose_form_data']);
?>
<div class="account-page message-page">
    <h1><?php echo $page_title; ?></h1>
    <a href="messages.php" class="btn btn-sm btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Inbox</a>
    <?php display_message(); ?>
    <?php if (!empty($errors)): ?> <div class="alert alert-danger"><strong>Errors:</strong><ul> <?php foreach ($errors as $error): ?> <li><?php echo htmlspecialchars($error); ?></li> <?php endforeach; ?> </ul></div> <?php endif; ?>

    <form action="process_message.php" method="post" id="compose-form">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="recipient_id" value="<?php echo $recipient_id; ?>">
        <?php if ($parent_message_id): ?><input type="hidden" name="parent_message_id" value="<?php echo $parent_message_id; ?>"> <?php endif; ?>
        <div class="form-group">
            <label>To:</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($recipient_username); ?>" readonly disabled style="<?php echo !$recipient_exists ? 'border-color:red; color:red;' : ''; ?>">
        </div>
        <div class="form-group">
            <label for="subject">Subject</label>
            <input type="text" name="subject" id="subject" class="form-control <?php echo isset($errors['subject']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data['subject'] ?? $subject); ?>" <?php echo !$parent_message_id ? 'required' : ''; ?> <?php echo !$recipient_exists ? 'disabled' : ''; ?>>
            <?php if (isset($errors['subject'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['subject']); ?></div><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="body">Message Body <span class="text-danger">*</span></label>
            <textarea name="body" id="body" rows="8" class="form-control <?php echo isset($errors['body']) ? 'is-invalid' : ''; ?>" required <?php echo !$recipient_exists ? 'disabled' : ''; ?>><?php echo htmlspecialchars($form_data['body'] ?? ''); ?></textarea>
            <?php if (isset($errors['body'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['body']); ?></div><?php endif; ?>
        </div>
        <div class="form-group mt-4">
            <button type="submit" class="btn btn-primary" <?php echo !$recipient_exists ? 'disabled' : ''; ?>><i class="fas fa-paper-plane"></i> Send Message</button>
            <?php if (!$recipient_exists): ?><span class="text-danger" style="margin-left: 10px;">Cannot send: Invalid recipient.</span><?php endif; ?>
        </div>
    </form>
</div>
<?php require_once 'includes/footer.php'; ?>