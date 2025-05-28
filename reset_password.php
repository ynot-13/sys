<?php

$page_title = "Reset Password";
require_once 'includes/config.php';
require_once 'includes/functions.php';

require_once 'includes/db_connect.php';


$token = $_GET['token'] ?? '';
$email_for_form = '';
$is_token_valid = false;
$validation_error_message = '';


if (!isset($mysqli) || !$mysqli instanceof mysqli || $mysqli->connect_error) {
    error_log("Reset Password Page Error: Database connection failed before token validation.");
    set_message("A critical error occurred. Please try again later.", "error");
    redirect("login.php");
}


if (empty($token)) {
    set_message("Password reset link is invalid or incomplete.", "error");
    redirect("login.php");
} else {
    $sql_validate = "SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()";
    $stmt_validate = $mysqli->prepare($sql_validate);
    if ($stmt_validate) {
        $stmt_validate->bind_param("s", $token);
        if ($stmt_validate->execute()) {
            $result_validate = $stmt_validate->get_result();
            if ($result_validate->num_rows === 1) {

                $is_token_valid = true;
                $row = $result_validate->fetch_assoc();
                $email_for_form = $row['email'];
                $page_title = "Create New Password";
            } else {
               
                set_message("Password reset link is invalid or has expired. Please request a new one.", "error");
                $mysqli->query("DELETE FROM password_resets WHERE expires_at <= NOW()");
            }
             if(isset($result_validate)) $result_validate->free();
        } else {
             $validation_error_message = "Error validating reset token.";
             error_log("Reset PW - Token validation execute error: " . $stmt_validate->error);
             set_message($validation_error_message, "error");
        }
        $stmt_validate->close();
    } else {
         $validation_error_message = "Database error during token validation.";
         error_log("Reset PW - Token validation prepare error: " . $mysqli->error);
         set_message($validation_error_message, "error");
    }
}

require_once 'includes/header.php';


$process_errors = $_SESSION['reset_errors'] ?? [];
unset($_SESSION['reset_errors']);

?>

<div class="auth-form-container">
    <h2><?php echo htmlspecialchars($page_title); ?></h2>

    <?php display_message(); ?>

    <?php if (!empty($process_errors)): ?>
        <div class="alert alert-danger">
             <i class="fas fa-times-circle"></i>
             <span><?php echo htmlspecialchars(implode('<br>', $process_errors)); ?></span>
        </div>
    <?php endif; ?>


    <?php if ($is_token_valid): ?>
        <p style="text-align: center; margin-bottom: 20px;">Please enter your new password below.</p>
        <form action="process_reset_password.php" method="post" id="reset-password-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email_for_form); ?>">

            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" name="new_password" id="new_password" class="form-control <?php echo isset($process_errors['password_length']) ? 'is-invalid' : ''; ?>" required>
                <small class="form-text text-muted">Must be at least 8 characters long.</small>
                <?php if (isset($process_errors['password_length'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($process_errors['password_length']); ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="confirm_new_password">Confirm New Password</label>
                <input type="password" name="confirm_new_password" id="confirm_new_password" class="form-control <?php echo isset($process_errors['password_match']) ? 'is-invalid' : ''; ?>" required>
                 <?php if (isset($process_errors['password_match'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($process_errors['password_match']); ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <button type="submit" class="btn" style="width: 100%;">Reset Password</button>
            </div>
             <div class="form-footer">
                <p><a href="login.php">Back to Login</a></p>
            </div>
        </form>

    <?php else: ?>
        <div class="form-footer" style="margin-top: 30px;">
            <p><a href="forgot_password.php">Request a new reset link</a></p>
            <p><a href="login.php">Back to Login</a></p>
        </div>
    <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
?>