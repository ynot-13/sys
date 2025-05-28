<?php
$page_title = "My Messages";
require_once 'includes/header.php';


if (!isLoggedIn()) {
    $_SESSION['redirect_url'] = 'messages.php';
    set_message("Please log in to view your messages.", "warning");
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$message_threads = [];



$sql = "SELECT t.thread_id, t.latest_subject, t.latest_sent_at, t.is_read, t.other_user_id,
               u_other.username AS other_username
        FROM (
            SELECT
                COALESCE(m.parent_message_id, m.message_id) AS thread_id,
                MAX(CASE WHEN m.parent_message_id IS NULL THEN m.subject ELSE NULL END) OVER (PARTITION BY COALESCE(m.parent_message_id, m.message_id)) as latest_subject, -- Get subject from parent
                MAX(m.sent_at) AS latest_sent_at,
        
                MIN(CASE WHEN m.receiver_id = ? AND m.read_at IS NULL THEN 0 ELSE 1 END) OVER (PARTITION BY COALESCE(m.parent_message_id, m.message_id)) AS is_read, -- 0 if ANY relevant msg is unread
                CASE
                    WHEN m.sender_id = ? THEN m.receiver_id
                    ELSE m.sender_id
                END AS other_user_id,
                ROW_NUMBER() OVER (PARTITION BY COALESCE(m.parent_message_id, m.message_id) ORDER BY m.sent_at DESC) as rn
            FROM messages m
            WHERE (m.sender_id = ? AND m.is_deleted_sender = 0)
               OR (m.receiver_id = ? AND m.is_deleted_receiver = 0)
        ) AS t
        JOIN users u_other ON t.other_user_id = u_other.user_id
        WHERE t.rn = 1 
        ORDER BY t.latest_sent_at DESC";

$stmt = $mysqli->prepare($sql);
if ($stmt) {
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id); 
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $message_threads[] = $row;
        }
    } else {
        set_message("Error fetching messages: " . $stmt->error, "error");
        error_log("Messages inbox fetch error: " . $stmt->error);
    }
    $stmt->close();
} else {
    set_message("Database query error preparing messages.", "error");
    error_log("Messages inbox prepare error: " . $mysqli->error);
}


?>

<div class="account-page"> 
    <h1>My Messages</h1>
    <a href="compose_message.php" class="btn btn-success mb-3"><i class="fas fa-edit"></i> Compose New Message</a>

    <?php if (!empty($message_threads)): ?>
        <div class="table-responsive">
            <table class="admin-table"> 
                <thead>
                    
                </thead>
                <tbody>
                    <?php foreach ($message_threads as $thread): ?>
                        <tr style="<?php echo ($thread['is_read'] == 0) ? 'font-weight: bold; background-color: #fffbea;' : ''; ?>">
                            <td>
                                <a href="view_message.php?thread_id=<?php echo $thread['thread_id']; ?>">
                                    <?php echo htmlspecialchars($thread['latest_subject'] ?: '(No Subject)'); ?>
                                     <?php echo ($thread['is_read'] == 0) ? '<span class="badge bg-danger" style="margin-left: 5px; font-size: 0.7em; background-color: var(--error-color); color: white; padding: 2px 4px; border-radius: 3px;">New</span>' : ''; ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($thread['other_username']); ?></td>
                            <td><?php echo date("M d, Y - h:i A", strtotime($thread['latest_sent_at'])); ?></td>
                            <td>
                                <a href="view_message.php?thread_id=<?php echo $thread['thread_id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> View
                                </a>
                               
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">You have no messages.</div>
    <?php endif; ?>

</div>

<?php
require_once 'includes/footer.php';
?>