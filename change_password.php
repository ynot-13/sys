<?php
$page_title = "Change Password";
require_once 'includes/header.php'; 


if (!isLoggedIn()) {
    $_SESSION['redirect_url'] = 'change_password.php';
    set_message("Please log in to change your password.", "warning");
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success_message = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid request token. Please try again.';
    } else {
     
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_new_password = $_POST['confirm_new_password'] ?? '';

   
        if (empty($current_password)) $errors[] = "Current password is required.";
        if (empty($new_password)) $errors[] = "New password is required.";
        elseif (strlen($new_password) < 8) $errors[] = "New password must be at least 8 characters long.";
        if ($new_password !== $confirm_new_password) $errors[] = "New passwords do not match.";

       
        if (empty($errors)) {
         
            $stmt_get = $mysqli->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt_get->bind_param("i", $user_id);
            $stmt_get->execute();
            $result_get = $stmt_get->get_result();
            if ($user_data = $result_get->fetch_assoc()) {
                 $current_hash = $user_data['password_hash'];
                
                 if (password_verify($current_password, $current_hash)) {
                 
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                   
                    $sql_update = "UPDATE users SET password_hash = ? WHERE user_id = ?";
                    $stmt_update = $mysqli->prepare($sql_update);
                    $stmt_update->bind_param("si", $new_password_hash, $user_id);

                     if ($stmt_update->execute()) {
                         $success_message = "Password changed successfully!";
                         
                     } else {
                         $errors[] = "Failed to update password. Please try again.";
                         error_log("Password change error for user $user_id: " . $stmt_update->error);
                     }
                     $stmt_update->close();

                 } else {
                     $errors[] = "Incorrect current password.";
                 }
            } else {
          
                 $errors[] = "Error retrieving user data.";
                 error_log("Password change - user data fetch failed for user $user_id");
            }
            $stmt_get->close();
        }
    }
}

?>

<div class="account-page"> 
    <h1>Change Your Password</h1>
     <a href="account.php" class="btn btn-sm btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Account</a>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <form action="change_password.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <div class="form-group">
            <label for="current_password">Current Password</label>
            <input type="password" name="current_password" id="current_password" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" name="new_password" id="new_password" class="form-control" required>
            <small class="form-text text-muted">Must be at least 8 characters long.</small>
        </div>

        <div class="form-group">
            <label for="confirm_new_password">Confirm New Password</label>
            <input type="password" name="confirm_new_password" id="confirm_new_password" class="form-control" required>
        </div>

        <div class="form-group mt-4">
            <button type="submit" class="btn btn-primary">Change Password</button>
        </div>
    </form>

</div>

<?php
require_once 'includes/footer.php';
?>