<?php

$page_title = "";
require_once 'admin_auth.php'; 
require_once __DIR__ . '/../includes/db_connect.php';
require_once 'admin_header.php';

$admin_id = $current_admin_id;
$message_threads = [];


if (!isset($mysqli) || !$mysqli instanceof mysqli) {
    set_message("Database connection error.", "error");
} else {
   
    $sql = "SELECT
                t.thread_id,
                t.latest_subject,
                t.latest_sent_at,
                t.other_user_id,
                t.other_username,
                MIN(t.is_read) AS thread_is_unread 
            FROM (
                SELECT
                    COALESCE(m.parent_message_id, m.message_id) AS thread_id,
                    m.subject AS latest_subject,
                    m.sent_at AS latest_sent_at,
                    CASE WHEN m.receiver_id = ? AND m.read_at IS NULL THEN 0 ELSE 1 END AS is_read,
                    CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AS other_user_id,
                    CASE WHEN m.sender_id = ? THEN r_user.username ELSE s_user.username END AS other_username,
                    ROW_NUMBER() OVER (PARTITION BY COALESCE(m.parent_message_id, m.message_id) ORDER BY m.sent_at DESC) as rn
                FROM messages m
                JOIN users s_user ON m.sender_id = s_user.user_id
                JOIN users r_user ON m.receiver_id = r_user.user_id
                WHERE (m.receiver_id = ? AND m.is_deleted_receiver = 0) 
                   OR (m.sender_id = ? AND m.is_deleted_sender = 0)
            ) AS t
            WHERE t.rn =1 -- Only the latest message per thread
            GROUP BY t.thread_id, t.latest_subject, t.latest_sent_at, t.other_user_id, t.other_username
            ORDER BY thread_is_unread ASC, t.latest_sent_at DESC"; 

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        
        $aid1=$admin_id; $aid2=$admin_id; $aid3=$admin_id; $aid4=$admin_id; $aid5=$admin_id;
        $stmt->bind_param("iiiii", $aid1, $aid2, $aid3, $aid4, $aid5);

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                 
                 if (empty($row['latest_subject'])) {
                     $subj_stmt = $mysqli->prepare("SELECT subject FROM messages WHERE message_id = ? AND subject IS NOT NULL LIMIT 1");
                     if($subj_stmt){
                         $tid_var = $row['thread_id'];
                         $subj_stmt->bind_param("i", $tid_var);
                         if($subj_stmt->execute()){
                             $subj_res = $subj_stmt->get_result();
                             if($subj_row = $subj_res->fetch_assoc()){ $row['latest_subject'] = $subj_row['subject']; }
                             $subj_res->free();
                         }
                         $subj_stmt->close();
                     }
                 }
                $message_threads[] = $row;
            }
            $result->free();
        } else { set_message("Error fetching admin messages: " . $stmt->error, "error"); }
        $stmt->close();
    } else { set_message("DB query error preparing admin messages.", "error"); }
} 
?>

<div class="admin-card">
    <h2>Manage Messages</h2>
    <?php display_message(); ?>

    <?php if (!empty($message_threads)): ?>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    
                </thead>
                <tbody>
                    <?php foreach ($message_threads as $thread): ?>
                        <tr style="<?php echo ($thread['thread_is_unread'] == 0) ? 'font-weight: bold; background-color: #fffbea;' : ''; ?>">
                            <td>
                                <a href="admin_view_message.php?thread_id=<?php echo $thread['thread_id']; ?>">
                                    <?php echo htmlspecialchars($thread['latest_subject'] ?: '(No Subject)'); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($thread['other_username']); ?></td>
                            <td><?php echo date("M d, Y - H:i", strtotime($thread['latest_sent_at'])); ?></td>
                            <td>
                                <?php echo ($thread['thread_is_unread'] == 0) ? '<span class="badge badge-danger">Unread</span>' : '<span class="badge badge-success">Read</span>'; ?>
                            </td>
                            <td>
                                <a href="admin_view_message.php?thread_id=<?php echo $thread['thread_id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> View / Reply
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No relevant messages found.</div>
    <?php endif; ?>

</div>

<?php
require_once 'admin_footer.php';
?>