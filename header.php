<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db_connect.php';

$cart_count = 0;
$unread_message_count = 0;
$user_profile_pic_path = null;

if (isset($mysqli) && $mysqli instanceof mysqli && !$mysqli->connect_error) {
    $cart_count = getCartItemCount($mysqli);

    if(isLoggedIn()){
        $user_id_for_header = $_SESSION['user_id'];
        $unread_message_count = getUnreadMessageCount($mysqli);

        $sql_pic = "SELECT profile_image_path FROM users WHERE user_id = ? LIMIT 1";
        $stmt_pic = $mysqli->prepare($sql_pic);
        if ($stmt_pic) {
            $stmt_pic->bind_param("i", $user_id_for_header);
            if ($stmt_pic->execute()) {
                $result_pic = $stmt_pic->get_result();
                if ($user_data = $result_pic->fetch_assoc()) {
                    $temp_path = $user_data['profile_image_path'];
                    $default_avatar_path = 'img/default-avatar.png';
                    if (!empty($temp_path) && $temp_path !== $default_avatar_path && file_exists(__DIR__ . '/../' . $temp_path)) {
                        $user_profile_pic_path = BASE_URL . htmlspecialchars($temp_path);
                    }
                }
                 if (isset($result_pic) && $result_pic instanceof mysqli_result) $result_pic->free();
            } else { error_log("Header profile pic fetch execute error: " . $stmt_pic->error); }
            $stmt_pic->close();
        } else { error_log("Header profile pic fetch prepare error: " . $mysqli->error); }
    }
} else {
    error_log("DB connection object (\$mysqli) not available in header.php");
}

$csrf_token = generateCsrfToken();
$current_page_base = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <link rel="icon" href="<?php echo BASE_URL; ?>img/we.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="container nav-container">
            <a href="<?php echo BASE_URL; ?>index.php" class="nav-logo">
    <img src="<?php echo BASE_URL; ?>img/we.png" alt="<?php echo SITE_NAME; ?> Logo" class="logo-image"> 
    <span class="logo-text"><?php echo SITE_NAME; ?></span>
</a> 

                <ul class="nav-menu">
                    <li title="Home"><a href="<?php echo BASE_URL; ?>index.php" class="<?php echo ($current_page_base == 'index.php') ? 'active' : ''; ?>"><i class=""></i> Home</a></li>
                    <li title="Products"><a href="<?php echo BASE_URL; ?>products.php" class="<?php echo ($current_page_base == 'products.php') ? 'active' : ''; ?>"><i class=""></i> Products</a></li>
                    <li title="About Us"><a href="<?php echo BASE_URL; ?>about.php" class="<?php echo ($current_page_base == 'about.php') ? 'active' : ''; ?>"><i class=""></i> About Us</a></li>
                    <li title="Contact Us"><a href="<?php echo BASE_URL; ?>contact.php" class="<?php echo ($current_page_base == 'contact.php') ? 'active' : ''; ?>"><i class=""></i> Contact</a></li>
                 </ul>

                <div class="nav-icons">
                    <a href="<?php echo BASE_URL; ?>products.php" class="nav-icon search-icon" title="Search Products"><i class="fas fa-search"></i></a>
                    <a href="<?php echo BASE_URL; ?>cart.php" class="nav-icon cart-icon" title="Shopping Cart">
                        <i class="fas fa-shopping-cart"></i>
                        <?php if ($cart_count > 0): ?><span class="cart-count"><?php echo $cart_count; ?></span><?php endif; ?>
                    </a>

                    <?php if (isLoggedIn()): ?>
                         <a href="<?php echo BASE_URL; ?>messages.php" class="nav-icon message-icon" title="My Messages">
                            <i class="fas fa-envelope"></i>
                            <?php if ($unread_message_count > 0): ?><span class="message-count"><?php echo $unread_message_count; ?></span><?php endif; ?>
                        </a>
                        <div class="dropdown">
                             <a href="#" class="nav-icon user-icon" title="My Account">
                                <?php if ($user_profile_pic_path): ?>
                                    <img src="<?php echo $user_profile_pic_path; ?>?v=<?php echo time(); ?>" alt="My Account" class="nav-profile-pic">
                                <?php else: ?>
                                    <i class="fas fa-user-circle"></i>
                                <?php endif; ?>
                             </a>
                             <div class="dropdown-content">
                                <?php if (isAdmin()): ?>
                                     <a href="<?php echo BASE_URL; ?>admin/dashboard.php"><i class="fas fa-user-shield fa-fw"></i> Admin Dashboard</a>
                                <?php endif; ?>
                                <a href="<?php echo BASE_URL; ?>account.php"><i class="fas fa-user-cog fa-fw"></i> My Account</a>
                                <a href="<?php echo BASE_URL; ?>order_history.php"><i class="fas fa-receipt fa-fw"></i> Order History</a>
                                <a href="<?php echo BASE_URL; ?>feedback.php"><i class="fas fa-comments fa-fw"></i> My Feedback</a>
                                <a href="<?php echo BASE_URL; ?>messages.php"><i class="fas fa-envelope fa-fw"></i> Messages <?php if($unread_message_count > 0) echo "({$unread_message_count})"; ?></a>
                                <hr style="margin: 5px 10px; border-color: rgba(0,0,0,0.1);">
                                <a href="<?php echo BASE_URL; ?>logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>login.php" class="nav-icon login-icon" title="Login/Register"><i class="fas fa-sign-in-alt"></i></a>
                    <?php endif; ?>

                     <div class="menu-toggle" id="mobile-menu" title="Toggle Menu">
                         <span class="bar"></span> <span class="bar"></span> <span class="bar"></span>
                     </div>
                </div>
            </div>
        </nav>
    </header>
    <main class="container main-content">
        <?php display_message(); ?>