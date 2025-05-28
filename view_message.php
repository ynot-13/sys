<?php
$page_title = "View Message";
require_once 'includes/header.php';

if (!isLoggedIn()) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    set_message("Please log in to view your messages.", "warning");
    redirect('login.php');
}
if (!isset($mysqli) || !$mysqli instanceof mysqli || $mysqli->connect_error) {
    error_log("Database connection error in view_message.php");
    set_message("Cannot connect to the database.", "error");
    redirect('messages.php');
}

$user_id = $_SESSION['user_id'];
$thread_id = filter_input(INPUT_GET, 'thread_id', FILTER_VALIDATE_INT);
if (!$thread_id || $thread_id <= 0) {
    set_message("Invalid message thread specified.", "error");
    redirect('messages.php');
}

$messages_in_thread = [];
$thread_subject = '';
$other_participant_id = null;
$other_participant_username = '';
$first_message_id_in_db = $thread_id;

$sql = "SELECT
            m.message_id, m.parent_message_id, m.sender_id, m.receiver_id,
            m.subject, m.body, m.sent_at, m.read_at,
            m.is_deleted_sender, m.is_deleted_receiver,
            s.username AS sender_username,
            r.username AS receiver_username
        FROM messages m
        JOIN users s ON m.sender_id = s.user_id
        JOIN users r ON m.receiver_id = r.user_id
        WHERE (m.message_id = ? OR m.parent_message_id = ?)
          AND EXISTS (
                SELECT 1 FROM messages root
                WHERE root.message_id = ? AND (root.sender_id = ? OR root.receiver_id = ?)
              )
          AND (
                 (m.sender_id = ? AND m.is_deleted_sender = 0) OR
                 (m.receiver_id = ? AND m.is_deleted_receiver = 0)
               )
        ORDER BY m.sent_at ASC";

$stmt = $mysqli->prepare($sql);
if ($stmt) {
    $stmt->bind_param("iiiiiii", $thread_id, $thread_id, $thread_id, $user_id, $user_id, $user_id, $user_id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $ids_to_mark_read = [];

        while ($row = $result->fetch_assoc()) {
            $messages_in_thread[] = $row;
            if ($row['message_id'] == $thread_id) {
                $first_message_id_in_db = $row['message_id'];
                $thread_subject = $row['subject'] ?: '(No Subject)';
                if ($row['sender_id'] == $user_id) {
                    $other_participant_id = $row['receiver_id'];
                    $other_participant_username = $row['receiver_username'];
                } else {
                    $other_participant_id = $row['sender_id'];
                    $other_participant_username = $row['sender_username'];
                }
            }
            if ($row['receiver_id'] == $user_id && $row['read_at'] === null) {
                $ids_to_mark_read[] = $row['message_id'];
            }
        }
        $result->free();

        if (empty($messages_in_thread)) {
            set_message("Message thread not found or access denied.", "warning");
            $stmt->close(); $mysqli->close(); redirect('messages.php');
        }
        if (!$other_participant_id) {
             set_message("Could not identify participants.", "error");
             $stmt->close(); $mysqli->close(); redirect('messages.php');
        }

        $page_title = "Message: " . htmlspecialchars($thread_subject);

        if (!empty($ids_to_mark_read)) {
            $placeholders = implode(',', array_fill(0, count($ids_to_mark_read), '?'));
            $sql_read = "UPDATE messages SET read_at = NOW() WHERE message_id IN ($placeholders) AND receiver_id = ?";
            if ($stmt_read = $mysqli->prepare($sql_read)) {
                $types = str_repeat('i', count($ids_to_mark_read)) . 'i';
                $bind_params = array_merge($ids_to_mark_read, [$user_id]);
                $stmt_read->bind_param($types, ...$bind_params);
                if(!$stmt_read->execute()){ error_log("Failed mark user messages read: " . $stmt_read->error); }
                $stmt_read->close();
            } else { error_log("Failed prepare mark user messages read: " . $mysqli->error); }
        }

    } else {
        error_log("Failed execute fetch thread (User: $user_id, Thread: $thread_id): " . $stmt->error);
        set_message("Error fetching message thread.", "error");
        $stmt->close(); $mysqli->close(); redirect('messages.php');
    }
    $stmt->close();
} else {
    error_log("Failed prepare fetch thread (User: $user_id, Thread: $thread_id): " . $mysqli->error);
    set_message("Database query error.", "error");
    if (isset($mysqli) && $mysqli instanceof mysqli) $mysqli->close();
    redirect('messages.php');
}
?>
<div class="account-page message-page container">
    <h1><?php echo $page_title; ?></h1>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="messages.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Inbox</a>
        <p class="mb-0">Conversation with: <strong><?php echo htmlspecialchars($other_participant_username); ?></strong></p>
    </div>

    <?php display_message(); ?>

    <div class="message-thread card">
        <div class="card-body">
            <?php if (empty($messages_in_thread)): ?>
                <p class="text-center text-muted">No messages found.</p>
            <?php else: ?>
                <?php foreach ($messages_in_thread as $message):
                    $is_sender = ($message['sender_id'] == $user_id);
                    $align_class = $is_sender ? 'message-sent' : 'message-received';
                    $sender_display_name = $is_sender ? 'You' : htmlspecialchars($message['sender_username']);
                ?>
                    <div class="message-item mb-3 <?php echo $align_class; ?>">
                        <div class="message-bubble">
                            <div class="message-header">
                                <strong><?php echo $sender_display_name; ?></strong>
                                <small class="message-timestamp text-muted">
                                    <?php echo date("M d, Y H:i", strtotime($message['sent_at'])); ?>
                                    <?php
                                    if ($is_sender) {
                                        if ($message['read_at']) {
                                            echo ' <i class="fas fa-check-double text-primary" title="Read at ' . date("M d, H:i", strtotime($message['read_at'])) . '"></i>';
                                        } else {
                                            echo ' <i class="fas fa-check" title="Sent"></i>';
                                        }
                                    }
                                    ?>
                                </small>
                            </div>
                            <div class="message-body">
                                <?php echo nl2br(htmlspecialchars($message['body'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="reply-form mt-4">
        <h3 class="mb-3">Reply to <?php echo htmlspecialchars($other_participant_username); ?></h3>
         <form action="process_message.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="recipient_id" value="<?php echo $other_participant_id; ?>">
            <input type="hidden" name="parent_message_id" value="<?php echo $first_message_id_in_db; ?>">
            <input type="hidden" name="subject" value="<?php echo htmlspecialchars($thread_subject); ?>">
            <div class="form-group mb-3">
                <label for="body" class="form-label">Your Reply:</label>
                <textarea name="body" id="body" rows="5" class="form-control" required placeholder="Type your reply here..."></textarea>
            </div>
            <div class="form-group mt-3">
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Reply</button>
            </div>
        </form>
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
.message-sent .message-header { text-align: right; }
.message-body { line-height: 1.5; word-wrap: break-word; }
.message-item i { font-size: 0.8em; margin-left: 5px; color: #999; }
.message-item i.fa-check-double { color: #4fc3f7; }
</style>

