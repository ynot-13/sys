<?php


$page_title = "My Account";
require_once 'includes/header.php';


if (!isLoggedIn()) { }


$user_info = null;
$newsfeed_items = [];
if (isset($mysqli) && $mysqli instanceof mysqli && !$mysqli->connect_error) {
    $user_id = $_SESSION['user_id'];
    
    $stmt_user = $mysqli->prepare("SELECT username, email, full_name, created_at, profile_image_path FROM users WHERE user_id = ?"); // Added profile_image_path
    if ($stmt_user) {
        $stmt_user->bind_param("i", $user_id);
        if ($stmt_user->execute()) {
            $result_user = $stmt_user->get_result();
            if ($result_user->num_rows === 1) {
                $user_info = $result_user->fetch_assoc();
            } else { redirect('logout.php'); }
             $result_user->free();
        } 
        $stmt_user->close();
    } 

    
    $newsfeed_limit = 7;
    $sql_orders = "SELECT order_id, order_date as activity_date, total_amount, status, 'order' as type FROM orders WHERE user_id = ? ORDER BY order_date DESC LIMIT ?";
    $stmt_orders = $mysqli->prepare($sql_orders);
    if ($stmt_orders) { $stmt_orders->bind_param("ii", $user_id, $newsfeed_limit); if ($stmt_orders->execute()) { $result = $stmt_orders->get_result(); while ($row = $result->fetch_assoc()) { $newsfeed_items[] = $row; } $result->free(); } $stmt_orders->close(); }
    $sql_feedback = "SELECT f.feedback_id, f.submitted_at as activity_date, f.comment, p.name as product_name, p.product_id, 'feedback' as type FROM feedback f LEFT JOIN products p ON f.product_id = p.product_id WHERE f.user_id = ? ORDER BY f.submitted_at DESC LIMIT ?";
    $stmt_feedback = $mysqli->prepare($sql_feedback);
    if ($stmt_feedback) { $stmt_feedback->bind_param("ii", $user_id, $newsfeed_limit); if ($stmt_feedback->execute()) { $result = $stmt_feedback->get_result(); while ($row = $result->fetch_assoc()) { $newsfeed_items[] = $row; } $result->free(); } $stmt_feedback->close(); }
    if (!empty($newsfeed_items)) { usort($newsfeed_items, function($a, $b) { return strtotime($b['activity_date']) - strtotime($a['activity_date']); }); $newsfeed_items = array_slice($newsfeed_items, 0, $newsfeed_limit); }
   

} else { set_message("Database connection error.", "error"); }



$profile_pic = BASE_URL . 'img/default-avatar.png'; 
if ($user_info && !empty($user_info['profile_image_path'])) {

    if (file_exists(__DIR__ . '/' . $user_info['profile_image_path'])) {
        $profile_pic = BASE_URL . htmlspecialchars($user_info['profile_image_path']);
    } else {
         error_log("Profile picture file not found for user {$user_id}: " . $user_info['profile_image_path']);
    
    }
}

?>

<div class="account-page">
    <h1>My Account</h1>

    <?php display_message(); ?>

    <?php if ($user_info): ?>
       
        <div class="user-details user-profile-header"> 
            <div class="profile-picture-container">
                 <img src="<?php echo $profile_pic; ?>" alt="Profile Picture" class="profile-picture">
            </div>
            <div class="profile-info-container">
                <h3>Welcome back, <?php echo htmlspecialchars($user_info['full_name'] ?: $user_info['username']); ?>!</h3>
                <p><strong>Username:</strong> <?php echo htmlspecialchars($user_info['username']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user_info['email']); ?></p>
                <p><strong>Member Since:</strong> <?php echo date("F j, Y", strtotime($user_info['created_at'])); ?></p>
                <div class="user-details-actions">
                    <a href="edit_profile.php" class="btn btn-sm btn-secondary"><i class="fas fa-user-edit"></i> Edit Profile & Picture</a>
                    <a href="change_password.php" class="btn btn-sm btn-secondary"><i class="fas fa-key"></i> Change Password</a>
                </div>
            </div>
        </div>

      
        <div class="account-sections">
            
            <div class="account-actions-col admin-card">
                 <h3>Quick Actions</h3>
                 <div class="action-card-grid">
                     <div class="action-card"> <i class="fas fa-receipt fa-2x"></i> <h4>My Orders</h4> <p>View your past purchases.</p> <a href="order_history.php" class="btn btn-sm">View Orders</a> </div>
                     <div class="action-card"> <i class="fas fa-comments fa-2x"></i> <h4>My Feedback</h4> <p>Manage your reviews.</p> <a href="feedback.php" class="btn btn-sm">View Feedback</a> </div>
                     <div class="action-card"> <i class="fas fa-envelope fa-2x"></i> <h4>My Messages</h4> <p>Check your inbox.</p> <a href="messages.php" class="btn btn-sm">View Messages</a> </div>
                 </div>
            </div>
      
             <div class="account-newsfeed-col admin-card">
                 <h3>Recent Activity</h3>
                 <?php if (!empty($newsfeed_items)): ?>
                     <div class="newsfeed-list"> <ul> <?php foreach ($newsfeed_items as $item): ?> <li> <span class="activity-icon"> <?php switch ($item['type']) { case 'order': echo '<i class="fas fa-receipt fa-fw"></i>'; break; case 'feedback': echo '<i class="fas fa-comment-dots fa-fw"></i>'; break; default: echo '<i class="fas fa-info-circle fa-fw"></i>'; break; } ?> </span> <span class="activity-text"> <?php switch ($item['type']) { case 'order': echo "Placed <a href='order_details.php?id={$item['order_id']}'>Order #{$item['order_id']}</a>. Status: ".htmlspecialchars($item['status']); break; case 'feedback': echo "Submitted feedback"; if (!empty($item['product_name'])) { $link = $item['product_id'] ? "product_details.php?id={$item['product_id']}" : "#"; echo " for <a href='{$link}'>" . htmlspecialchars($item['product_name']) . "</a>"; } break; default: echo "An update occurred."; break; } ?> </span> <small class="activity-date"> <?php echo date("M d, 'y", strtotime($item['activity_date'])); ?> </small> </li> <?php endforeach; ?> </ul> </div>
                 <?php else: ?> <p class="text-muted">No recent activity to display.</p> <?php endif; ?>
             </div>
        </div>
    <?php elseif(!isset($mysqli) || $mysqli->connect_error): ?> <p>Could not load account details due to a connection issue.</p>
    <?php else: ?> <p>An unexpected error occurred loading your account information.</p> <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>