<?php

$page_title = "Register";
require_once 'includes/config.php'; 
require_once 'includes/functions.php'; 


if (isLoggedIn()) {
    redirect(BASE_URL . 'account.php');
}

require_once 'includes/header.php';

$errors = $_SESSION['register_errors'] ?? [];
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['register_errors'], $_SESSION['form_data']); 

?>


<script src="https://www.google.com/recaptcha/api.js" async defer></script>



<div class="auth-form-container">
    <h2>Create Your Account</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-times-circle"></i>
            <div> 
                <strong>Please fix the following issues:</strong><br>
                <?php
           
                 if (isset($errors['recaptcha'])) {
                     echo htmlspecialchars($errors['recaptcha']) . '<br>';
                     unset($errors['recaptcha']); 
                 }
            
                 foreach ($errors as $field => $error):
                     echo htmlspecialchars($error) . '<br>';
                 endforeach;
                ?>
            </div>
        </div>
    <?php endif; ?>

    <form action="process_register.php" method="post" id="registration-form">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

      
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>" required>
             <?php if (isset($errors['username'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['username']); ?></div><?php endif; ?>
        </div>
     
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
             <?php if (isset($errors['email'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['email']); ?></div><?php endif; ?>
        </div>
       
         <div class="form-group">
            <label for="full_name">Full Name (Optional)</label>
            <input type="text" name="full_name" id="full_name" class="form-control" value="<?php echo htmlspecialchars($form_data['full_name'] ?? ''); ?>">
        </div>
   
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" required aria-describedby="passwordHelp">
            <small id="passwordHelp" class="form-text text-muted">Must be at least 8 characters long.</small>
             <?php if (isset($errors['password'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['password']); ?></div><?php endif; ?>
        </div>
      
        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" required>
             <?php if (isset($errors['confirm_password'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['confirm_password']); ?></div><?php endif; ?>
        </div>

        
        <div class="form-group">
            <label>Security Check <span class="text-danger">*</span></label>
            <?php if (defined('RECAPTCHA_SITE_KEY')): ?>
                <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
            <?php else: ?>
                <p class="text-danger">reCAPTCHA Site Key is not configured!</p>
            <?php endif; ?>

             <?php if (isset($errors['recaptcha_display'])): ?>
                <div style="color: var(--error-color); font-size: 0.875em; margin-top: 0.25rem;"><?php echo htmlspecialchars($errors['recaptcha_display']); ?></div>
             <?php endif; ?>
             <?php if (isset($errors['recaptcha'])):  ?>
                 <div style="color: var(--error-color); font-size: 0.875em; margin-top: 0.25rem;"><?php echo htmlspecialchars($errors['recaptcha']); ?></div>
             <?php endif; ?>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn" style="width: 100%;">Register</button>
        </div>
       
        <div class="form-footer">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </form>
</div>

<?php
require_once 'includes/footer.php';
?>