<?php
$page_title = "Admin Login";
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (isAdmin()) { redirect(BASE_URL . 'admin/dashboard.php'); }

$login_error = $_SESSION['admin_login_error'] ?? '';
unset($_SESSION['admin_login_error']);
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css?v=<?php echo time(); ?>">
    <style> /* Minimal styles */
        body { background-color: #f0f0f0; display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: var(--font-family); }
        .login-box { max-width: 400px; width: 90%; padding: 30px; background-color: var(--white-color); border-radius: var(--border-radius); box-shadow: var(--box-shadow-light); border: 1px solid var(--border-color); }
        .login-box h2 { text-align: center; margin-bottom: 25px; color: var(--primary-color); font-weight: 700; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2><?php echo SITE_NAME; ?> - Admin Access</h2>
        <?php if ($login_error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($login_error); ?></div>
        <?php endif; ?>
        <?php display_message('success'); ?>
        <form action="process_admin_login.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="form-group">
                <label for="credential">Username or Email</label>
                <input type="text" name="credential" id="credential" class="form-control" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            <div class="form-group">
                <button type="submit" class="btn" style="width: 100%;">Login</button>
            </div>
           
            </div>
        </form>
    </div>
</body>
</html>