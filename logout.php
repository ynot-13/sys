<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php'; 

$_SESSION = array();


if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}


session_destroy();


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
set_message("You have been logged out successfully.", "success");


redirect(BASE_URL . 'admin/login.php');

exit(); 
?>