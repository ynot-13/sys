<?php
// forgot_password.php - Form for user to request password reset

$page_title = "Forgot Password";
require_once 'includes/config.php'; // Needs BASE_URL, session
require_once 'includes/functions.php'; // Needs isLoggedIn, generateCsrfToken, display_message

// Redirect if user is already logged in
if (isLoggedIn()) {
    redirect(BASE_URL . 'account.php');
}

// Include the main website header
require_once 'includes/header.php'; // Contains CSRF generation via header call

// Get messages from session (set by process_forgot_password.php)
$errors = $_SESSION['forgot_errors'] ?? [];
$success_message = $_SESSION['forgot_success'] ?? '';
unset($_SESSION['forgot_errors'], $_SESSION['forgot_success']); // Clear after displaying

?>

<div class="auth-form-container">
    <h2>Forgot Your Password?</h2>
    <p style="text-align: center; margin-bottom: 20px; font-size: 0.95em;">
        No problem! Enter your email address below and we'll send you a link to reset your password.
    </p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-times-circle"></i>
            <span><?php echo htmlspecialchars(implode('<br>', $errors)); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
             <i class="fas fa-check-circle"></i>
             <span><?php echo $success_message; // Allow potential HTML for demo link ?></span>
        </div>
    <?php endif; ?>

    <?php if (empty($success_message)): // Hide form after success message is shown ?>
    <form action="process_forgot_password.php" method="post" id="forgot-password-form">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <div class="form-group">
            <label for="email">Enter Your Account Email</label>
            <input type="email" name="email" id="email" class="form-control" required autofocus placeholder="e.g., yourname@example.com">
        </div>
        <div class="form-group">
            <button type="submit" class="btn" style="width: 100%;">Send Reset Link</button>
        </div>
        <div class="form-footer">
            <p><a href="login.php">Remembered your password? Login</a></p>
        </div>
    </form>
    <?php endif; ?>

</div>

<?php
require_once 'includes/footer.php';
?>