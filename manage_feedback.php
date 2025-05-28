<?php
$page_title = "";
require_once 'admin_auth.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once 'admin_header.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
     if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        set_message('Invalid request token.', 'error');
     } else {
        $feedback_id_action = filter_input(INPUT_POST, 'feedback_id', FILTER_VALIDATE_INT);

        if ($feedback_id_action) {
            if ($_POST['action'] === 'toggle_approval') {
              
                 $stmt_get = $mysqli->prepare("SELECT is_approved FROM feedback WHERE feedback_id = ?");
                 if($stmt_get){
                     $stmt_get->bind_param("i", $feedback_id_action);
                     $stmt_get->execute();
                     $result_get = $stmt_get->get_result();
                     if($feedback_data = $result_get->fetch_assoc()) {
                         $new_approval_status = $feedback_data['is_approved'] ? 0 : 1; 

                         $stmt_update = $mysqli->prepare("UPDATE feedback SET is_approved = ? WHERE feedback_id = ?");
                          if($stmt_update){
                             $stmt_update->bind_param("ii", $new_approval_status, $feedback_id_action);
                             if ($stmt_update->execute()) {
                                  set_message("Feedback #" . $feedback_id_action . " approval status " . ($new_approval_status ? 'set to Approved' : 'set to Pending') . ".", "success");
                             } else { set_message("Error updating feedback status: " . $stmt_update->error, "error"); }
                             $stmt_update->close();
                          } else { set_message("Error preparing feedback update.", "error"); }
                     } else { set_message("Feedback not found.", "error"); }
                     $stmt_get->close();
                 } else { set_message("Error preparing feedback check.", "error"); }

            } elseif ($_POST['action'] === 'delete_feedback') {
                 $stmt_delete = $mysqli->prepare("DELETE FROM feedback WHERE feedback_id = ?");
                 if($stmt_delete){
                     $stmt_delete->bind_param("i", $feedback_id_action);
                     if ($stmt_delete->execute()) {
                          set_message("Feedback #" . $feedback_id_action . " deleted successfully.", "success");
                     } else { set_message("Error deleting feedback: " . $stmt_delete->error, "error"); }
                     $stmt_delete->close();
                 } else { set_message("Error preparing feedback delete.", "error"); }
            }
         } else {
             set_message("Invalid feedback ID specified.", "error");
         }
     }
      
      header("Location: " . $_SERVER['REQUEST_URI']);
      exit();
}



$feedback_list = [];
$filter_status = isset($_GET['status']) ? $_GET['status'] : null; 
$where_clause = '';
$params = [];
$types = '';

if ($filter_status === 'pending') {
    $where_clause = " WHERE f.is_approved = 0";
} elseif ($filter_status === 'approved') {
     $where_clause = " WHERE f.is_approved = 1";
}

$sql = "SELECT f.feedback_id, f.rating, f.comment, f.submitted_at, f.is_approved,
               u.username as user_username,
               p.name as product_name, p.product_id
        FROM feedback f
        JOIN users u ON f.user_id = u.user_id
        LEFT JOIN products p ON f.product_id = p.product_id "
        . $where_clause .
        " ORDER BY f.submitted_at DESC";

$result = $mysqli->query($sql); 
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $feedback_list[] = $row;
    }
    $result->free();
} else {
     set_message("Error fetching feedback: " . $mysqli->error, "error");
}
?>

<div class="admin-card">
    <h2>Feedback List <?php echo $filter_status ? "(".ucfirst($filter_status).")" : ""; ?></h2>

    
    <div style="margin-bottom: 20px;">
        Filter:
        <a href="manage_feedback.php" class="btn btn-sm <?php echo !$filter_status ? 'btn-primary' : 'btn-secondary'; ?>">All</a>
        <a href="manage_feedback.php?status=pending" class="btn btn-sm <?php echo $filter_status === 'pending' ? 'btn-primary' : 'btn-secondary'; ?>">Pending</a>
        <a href="manage_feedback.php?status=approved" class="btn btn-sm <?php echo $filter_status === 'approved' ? 'btn-primary' : 'btn-secondary'; ?>">Approved</a>
    </div>

     <?php display_message(); ?>

    <div style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                
            </thead>
            <tbody>
                <?php if (!empty($feedback_list)): ?>
                    <?php foreach ($feedback_list as $feedback): ?>
                        <tr>
                            <td><?php echo $feedback['feedback_id']; ?></td>
                            <td><?php echo date("Y-m-d", strtotime($feedback['submitted_at'])); ?></td>
                            <td><?php echo htmlspecialchars($feedback['user_username']); ?></td>
                            <td>
                                <?php if ($feedback['product_id'] && $feedback['product_name']): ?>
                                    <a href="../product_details.php?id=<?php echo $feedback['product_id']; ?>" target="_blank" title="View Product">
                                        <?php echo htmlspecialchars($feedback['product_name']); ?>
                                    </a>
                                <?php else: ?>
                                    <em>General</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($feedback['rating']): ?>
                                    <?php for($i=1; $i<=$feedback['rating']; $i++) echo '<i class="fas fa-star" style="color: var(--warning-color);"></i>'; ?>
                                    <?php for($i=$feedback['rating']+1; $i<=5; $i++) echo '<i class="far fa-star" style="color: #ccc;"></i>'; ?>
                                <?php else: echo 'N/A'; endif; ?>
                            </td>
                            <td><?php echo nl2br(htmlspecialchars(substr($feedback['comment'], 0, 100))) . (strlen($feedback['comment']) > 100 ? '...' : ''); ?></td>
                            <td>
                                <?php if ($feedback['is_approved']): ?>
                                    <span class="badge badge-success">Approved</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form action="manage_feedback.php?<?php echo http_build_query(['status'=>$filter_status]); // Keep filter ?>" method="post" style="display: inline-block;">
                                     <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="feedback_id" value="<?php echo $feedback['feedback_id']; ?>">
                                    <input type="hidden" name="action" value="toggle_approval">
                                    <button type="submit" class="btn btn-sm <?php echo $feedback['is_approved'] ? 'btn-secondary' : 'btn-success'; ?>" title="<?php echo $feedback['is_approved'] ? 'Set to Pending' : 'Approve'; ?>">
                                        <i class="fas <?php echo $feedback['is_approved'] ? 'fa-times-circle' : 'fa-check-circle'; ?>"></i> <?php echo $feedback['is_approved'] ? 'Unapprove' : 'Approve'; ?>
                                    </button>
                                </form>
                                <form action="manage_feedback.php?<?php echo http_build_query(['status'=>$filter_status]); ?>" method="post" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to DELETE this feedback?');">
                                     <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="feedback_id" value="<?php echo $feedback['feedback_id']; ?>">
                                    <input type="hidden" name="action" value="delete_feedback">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete Feedback">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">No feedback found matching the criteria.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
   
</div>

<?php
require_once 'admin_footer.php';
?>