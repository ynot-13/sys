<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';

$csrf_token = generateCsrfToken();
$current_page = basename($_SERVER['PHP_SELF']);
$admin_display_name = isset($current_admin_username) ? htmlspecialchars($current_admin_username) : "Admin";
$admin_unread_count = 0;

if (isset($current_admin_id) && isset($mysqli) && $mysqli instanceof mysqli) {
    $admin_unread_count = getUnreadMessageCount($mysqli);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Admin'; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="icon" href="<?php echo BASE_URL; ?>img/we.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css?v=<?php echo time(); ?>">
</head>
<body class="admin-body">
    <aside id="admin-sidebar">
        <div class="admin-logo" style="display: flex; align-items: center; gap: 10px;">
            <img src="<?php echo BASE_URL; ?>img/we.png" alt="<?php echo SITE_NAME; ?> Logo" class="logo-image" style="height: 40px;">
            <span><?php echo SITE_NAME; ?></span>
        </div>
        <ul>
            <li><a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt fa-fw"></i> <span>Dashboard</span></a></li>
            <li><a href="<?php echo BASE_URL; ?>admin/manage_users.php" class="<?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>"><i class="fas fa-users fa-fw"></i> <span>Manage Users</span></a></li>
            <li><a href="<?php echo BASE_URL; ?>admin/manage_products.php" class="<?php echo in_array($current_page, ['manage_products.php', 'add_product.php', 'edit_product.php']) ? 'active' : ''; ?>"><i class="fas fa-box-open fa-fw"></i> <span>Manage Products</span></a></li>
            <li><a href="<?php echo BASE_URL; ?>admin/manage_orders.php" class="<?php echo in_array($current_page, ['manage_orders.php', 'view_order.php']) ? 'active' : ''; ?>"><i class="fas fa-receipt fa-fw"></i> <span>Manage Orders</span></a></li>
            <li><a href="<?php echo BASE_URL; ?>admin/manage_feedback.php" class="<?php echo ($current_page == 'manage_feedback.php') ? 'active' : ''; ?>"><i class="fas fa-comments fa-fw"></i> <span>Manage Feedback</span></a></li>
            <li><a href="<?php echo BASE_URL; ?>admin/admin_manage_messages.php" class="<?php echo in_array($current_page, ['admin_manage_messages.php', 'admin_view_message.php']) ? 'active' : ''; ?>"><i class="fas fa-envelope fa-fw"></i> <span>Manage Messages <?php if($admin_unread_count > 0) echo "<span class='badge badge-danger' style='margin-left: 5px;'>".$admin_unread_count."</span>"; ?></span></a></li>
            <li><a href="<?php echo BASE_URL; ?>admin/reports.php" class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>"><i class="fas fa-chart-pie fa-fw"></i> <span>Reports</span></a></li>
            <hr style="border-color: rgba(255,255,255,0.1); margin: 10px 20px;">
            <li><a href="<?php echo BASE_URL; ?>" target="_blank"><i class="fas fa-globe fa-fw"></i> <span>View Live Site</span></a></li>
            <li><a href="<?php echo BASE_URL; ?>logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> <span>Logout</span></a></li>
        </ul>
    </aside>
    <main id="admin-content">
        <header class="admin-header">
            <h1><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Admin Dashboard'; ?></h1>
            <div class="admin-user-info" style="display: flex; align-items: center; gap: 20px;">
                <span>Welcome, <?php echo $admin_display_name; ?>!</span>
            
                    <?php if ($admin_unread_count > 0): ?>
                        <span class="message-count"><?php echo $admin_unread_count; ?></span>
                    <?php endif; ?>
                </a>
             
                    <img src="<?php echo BASE_URL; ?>img/l.jpg" alt="Profile" style="height: 36px; width: 36px; border-radius: 50%; object-fit: cover;">
                </a>
            </div>
        </header>
        <?php display_message(); ?>
