<?php
$page_title = "View Message ";
require_once 'admin_auth.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once 'admin_header.php';

$thread_id = filter_input(INPUT_GET, 'thread_id', FILTER_VALIDATE_INT);
if (!$thread_id || $thread_id <= 0) {
    set_message("Invalid message thread specified.", "error");
    redirect('admin_manage_messages.php');
}

if (!isset($mysqli) || !$mysqli instanceof mysqli || $mysqli->connect_error) {
    set_message("Database connection error.", "error");
    echo "<div class='admin-card'><div class='alert alert-danger'>Database connection error. Cannot load messages.</div></div>";
    require_once 'admin_footer.php';
    exit;
}

$admin_id = $current_admin_id;
$messages_in_thread = [];
$thread_subject = '(No Subject)';
$other_participant_id = null;
$other_participant_username = 'Unknown User';
$root_message_id = $thread_id;

$sql_root = "SELECT message_id, sender_id, receiver_id, subject, parent_message_id
             FROM messages
             WHERE message_id = ? AND (sender_id = ? OR receiver_id = ?)";
$stmt_root = $mysqli->prepare($sql_root);
$root_message_data = null;

if($stmt_root) {
    $tid_var = $thread_id; $aid1 = $admin_id; $aid2 = $admin_id;
    $stmt_root->bind_param("iii", $tid_var, $aid1, $aid2);
    if($stmt_root->execute()){
        $result_root = $stmt_root->get_result();
        if($result_root->num_rows === 1) {
            $root_message_data = $result_root->fetch_assoc();
            $root_message_id = $root_message_data['parent_message_id'] ?? $root_message_data['message_id'];
        } else {
             set_message("Thread not found or access denied.", "warning");
             redirect('admin_manage_messages.php');
        }
        $result_root->free();
    } else {
        set_message("Error checking thread involvement.", "error");
        error_log("Admin View Msg - Root Check Execute Error: ".$stmt_root->error);
        redirect('admin_manage_messages.php');
    }
    $stmt_root->close();
} else {
    set_message("Error preparing thread check.", "error");
    error_log("Admin View Msg - Root Check Prepare Error: ".$mysqli->error);
    redirect('admin_manage_messages.php');
}

$sql_thread = "SELECT m.*, sender.username as sender_username, receiver.username as receiver_username
               FROM messages m
               JOIN users sender ON m.sender_id = sender.user_id
               JOIN users receiver ON m.receiver_id = receiver.user_id
               WHERE (m.message_id = ? OR m.parent_message_id = ?)
               ORDER BY m.sent_at ASC";

$stmt_thread = $mysqli->prepare($sql_thread);
if ($stmt_thread) {
    $root_id_var1 = $root_message_id; $root_id_var2 = $root_message_id;
    $stmt_thread->bind_param("ii", $root_id_var1, $root_id_var2);

    if ($stmt_thread->execute()) {
        $result_thread = $stmt_thread->get_result();
        $ids_to_mark_read = [];
        while ($row = $result_thread->fetch_assoc()) {
            $messages_in_thread[] = $row;
            if($row['message_id'] == $root_message_id && !empty($row['subject'])) {
                $thread_subject = $row['subject'];
            }
            if($root_message_data){
                if($root_message_data['sender_id'] == $admin_id) {
                     $other_participant_id = $root_message_data['receiver_id'];
                } else {
                     $other_participant_id = $root_message_data['sender_id'];
                }
            }
            if ($row['receiver_id'] == $admin_id && $row['read_at'] === null) {
                $ids_to_mark_read[] = $row['message_id'];
            }
        }
        $result_thread->free();

        if (empty($messages_in_thread)) {
             set_message("No messages found in this thread.", "warning");
             // Don't redirect here, allow empty thread to show
        }

        if($other_participant_id) {
             $stmt_other_user = $mysqli->prepare("SELECT username FROM users WHERE user_id = ?");
             if($stmt_other_user){
                 $other_id_var = $other_participant_id;
                 $stmt_other_user->bind_param("i", $other_id_var);
                 if($stmt_other_user->execute()){
                     $res_other_user = $stmt_other_user->get_result();
                     if($other_user_data = $res_other_user->fetch_assoc()){
                         $other_participant_username = $other_user_data['username'];
                     }
                     $res_other_user->free();
                 }
                 $stmt_other_user->close();
             }
        }
        $page_title = htmlspecialchars($thread_subject) . " (with " . htmlspecialchars($other_participant_username) . ")";

        if (!empty($ids_to_mark_read)) {
            $placeholders = implode(',', array_fill(0, count($ids_to_mark_read), '?'));
            $sql_mark_read = "UPDATE messages SET read_at = NOW() WHERE message_id IN ($placeholders) AND receiver_id = ?";
            if($stmt_read = $mysqli->prepare($sql_mark_read)) {
                 $types = str_repeat('i', count($ids_to_mark_read)) . 'i';
                 $bind_params = array_merge($ids_to_mark_read, [$admin_id]);
                 $stmt_read->bind_param($types, ...$bind_params);
                 if(!$stmt_read->execute()){ error_log("Failed mark admin messages read (Thread $root_message_id): " . $stmt_read->error); }
                 $stmt_read->close();
             } else { error_log("Failed prepare mark admin messages read: " . $mysqli->error); }
        }

    } else {
        set_message("Error fetching messages: " . $stmt_thread->error, "error");
    }
    $stmt_thread->close();
} else {
    set_message("DB error preparing messages.", "error");
}

if (!$other_participant_id && !empty($messages_in_thread)) {
    set_message("Could not determine recipient.", "warning");
}
?>

<div class="admin-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap;">
        <h2 style="font-size: 1.4rem; margin-bottom: 5px;"><?php echo $page_title; ?></h2>
        <a href="admin_manage_messages.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Back to Messages</a>
    </div>

    <?php display_message(); ?>

    <p style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color);">
        Conversation with: <strong><?php echo htmlspecialchars($other_participant_username); ?></strong>
        <?php if ($other_participant_id): ?>
            (User ID: <a href="manage_users.php?user_id=<?php echo $other_participant_id; ?>"><?php echo $other_participant_id; ?></a>)
        <?php endif; ?>
    </p>

    <div class="message-thread" style="border: 1px solid #ddd; border-radius: 5px; padding: 15px; background-color: #fdfdfd; max-height: 50vh; overflow-y: auto; margin-bottom: 30px;">
        <?php if (empty($messages_in_thread)): ?>
            <p class="text-muted text-center">No messages in this thread.</p>
        <?php else: ?>
            <?php foreach ($messages_in_thread as $message):
                $is_admin_sender = ($message['sender_id'] == $admin_id);
                $align_class = $is_admin_sender ? 'message-sent' : 'message-received';
                $sender_name = $is_admin_sender ? 'You (Admin)' : htmlspecialchars($message['sender_username']);
            ?>
                <div class="message-item mb-3 <?php echo $align_class; ?>">
                     <div class="message-bubble">
                        <div class="message-header">
                            <strong><?php echo $sender_name; ?></strong>
                            <small class="message-timestamp text-muted" style="<?php echo $is_admin_sender ? 'float: left; margin-right: 10px;' : 'float: right; margin-left: 10px;'; ?>">
                                <?php echo date("M d, Y - H:i", strtotime($message['sent_at'])); ?>
                            </small>
                        </div>
                         <div class="message-body">
                            <?php echo nl2br(htmlspecialchars($message['body'])); ?>
                         </div>
                    </div>
                </div>
                 <div style="clear:both;"></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="reply-form">
        <?php if ($other_participant_id): ?>
            <h3 class="mb-3">Reply to <?php echo htmlspecialchars($other_participant_username); ?></h3>
            <form action="admin_process_reply.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="recipient_id" value="<?php echo $other_participant_id; ?>">
                <input type="hidden" name="parent_message_id" value="<?php echo $root_message_id; ?>">
                <input type="hidden" name="subject" value="<?php echo htmlspecialchars($thread_subject); ?>">
                <div class="form-group mb-3">
                    <label for="body" class="form-label">Your Reply:</label>
                    <textarea name="body" id="body" rows="5" class="form-control" required></textarea>
                </div>
                <div class="form-group mt-3">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-reply"></i> Send Reply</button>
                </div>
            </form>
        <?php else: ?>
             <p class="text-warning">Cannot reply because recipient information is unavailable.</p>
        <?php endif; ?>
    </div>
</div>
<style>

.message-thread { padding: 15px; }
.message-item { display: flex; margin-bottom: 10px; }
.message-sent { justify-content: flex-end; }
.message-received { justify-content: flex-start; }
.message-bubble { max-width: 75%; padding: 10px 15px; border-radius: 15px; position: relative; }
.message-sent .message-bubble { background-color: #dcf8c6; border-bottom-right-radius: 5px; }
.message-received .message-bubble { background-color: #f0f0f0; border-bottom-left-radius: 5px; }
.message-header { font-size: 0.85em; margin-bottom: 5px; color: #555; }
.message-header strong { margin-right: 5px; font-weight: 600;}
.message-timestamp { font-size: 0.9em; }

.message-body { line-height: 1.5; word-wrap: break-word; }
.message-item i { font-size: 0.8em; margin-left: 5px; color: #999; }
.message-item i.fa-check-double { color: #4fc3f7; }
</style>
