<?php
require_once '../includes/config.php'; 
require_once '../includes/functions.php'; 



if (!isAdmin()) {
    set_message("Access Restricted. Please log in as administrator.", "error");
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI']; 
    redirect(BASE_URL . 'admin/login.php'); 
}


$current_admin_id = $_SESSION['user_id'];
$current_admin_username = $_SESSION['username'];
?>