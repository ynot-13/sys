<?php
$page_title = "";
require_once 'admin_auth.php'; 
require_once __DIR__ . '/../includes/db_connect.php';
require_once 'admin_header.php'; 


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
     if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
          set_message('Invalid request token.', 'error');
     } else {
        $user_id_action = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

        if ($user_id_action && $user_id_action != $_SESSION['user_id']) {
            if ($_POST['action'] === 'delete') {
              
                 $stmt_delete = $mysqli->prepare("DELETE FROM users WHERE user_id = ? AND role != 'admin'"); 
                 $stmt_delete->bind_param("i", $user_id_action);
                 if ($stmt_delete->execute()) {
                      set_message("User deleted successfully.", "success");
                 } else {
                      set_message("Error deleting user: " . $stmt_delete->error, "error");
                 }
                 $stmt_delete->close();

            } elseif ($_POST['action'] === 'toggle_role') {
              
                 $stmt_get = $mysqli->prepare("SELECT role FROM users WHERE user_id = ?");
                 $stmt_get->bind_param("i", $user_id_action);
                 $stmt_get->execute();
                 $result_get = $stmt_get->get_result();
                 if($user_role_data = $result_get->fetch_assoc()) {
                     $new_role = ($user_role_data['role'] === 'user') ? 'admin' : 'user';
                     $stmt_get->close();

                     $stmt_update = $mysqli->prepare("UPDATE users SET role = ? WHERE user_id = ?");
                     $stmt_update->bind_param("si", $new_role, $user_id_action);
                     if ($stmt_update->execute()) {
                          set_message("User role updated to " . strtoupper($new_role) . ".", "success");
                     } else {
                          set_message("Error updating user role: " . $stmt_update->error, "error");
                     }
                     $stmt_update->close();
                 } else {
                     set_message("User not found.", "error");
                      if($stmt_get) $stmt_get->close();
                 }
            }
         } elseif ($user_id_action == $_SESSION['user_id']){
             set_message("You cannot modify your own account using this form.", "warning");
         } else {
             set_message("Invalid user ID specified.", "error");
         }
        
     }
}



$users = [];
$sql = "SELECT user_id, username, email, full_name, role, created_at, last_login FROM users ORDER BY created_at DESC";
$result = $mysqli->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $result->free();
} else {
    set_message("Error fetching users: " . $mysqli->error, "error");
}

?>

<div class="admin-card">
    <h2>User List</h2>

     <?php display_message();  ?>

    <div style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name'] ?: '-'); ?></td>
                            <td>
                              
                                <span class="badge <?php echo $user['role'] === 'admin' ? 'badge-danger' : 'badge-success'; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo date("Y-m-d", strtotime($user['created_at'])); ?></td>
                            <td><?php echo $user['last_login'] ? date("Y-m-d H:i", strtotime($user['last_login'])) : 'Never'; ?></td>
                            <td>
                                <?php if ($user['user_id'] != $_SESSION['user_id']):  ?>
                                    <form action="manage_users.php" method="post" style="display: inline-block;" onsubmit="return confirm('TOGGLE role for <?php echo htmlspecialchars(addslashes($user['username'])); ?>? This is a sensitive action!');">
                                         <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <input type="hidden" name="action" value="toggle_role">
                                        <button type="submit" class="btn btn-sm btn-warning" title="Toggle Role (Admin/User)">
                                             <i class="fas fa-user-shield"></i> Role
                                        </button>
                                    </form>
                                    <form action="manage_users.php" method="post" style="display: inline-block;" onsubmit="return confirm('DELETE user <?php echo htmlspecialchars(addslashes($user['username'])); ?>? This cannot be undone!');">
                                         <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete User">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                     <a href="manage_orders.php?user_id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-info" title="View User Orders"><i class="fas fa-list"></i> Orders</a>
                                <?php else: ?>
                                     <span class="text-muted"><em>(Your Account)</em></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">No users found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
   
</div>

<?php
require_once 'admin_footer.php';
?>