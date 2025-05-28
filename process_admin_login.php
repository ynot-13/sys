<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php'; 
require_once __DIR__ . '/../includes/functions.php';

ini_set('display_errors', 1); 
error_reporting(E_ALL);

if ($_SERVER["REQUEST_METHOD"] !== "POST") { redirect(BASE_URL . 'admin/login.php'); }

if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    $_SESSION['admin_login_error'] = 'Invalid security token.';
    redirect(BASE_URL . 'admin/login.php');
}

$credential = sanitize_input($_POST['credential'] ?? '');
$password = $_POST['password'] ?? ''; 
if (empty($credential) || empty($password)) {
    $_SESSION['admin_login_error'] = 'Username/Email and Password are required.';
    redirect(BASE_URL . 'admin/login.php');
}

if (!isset($mysqli) || !$mysqli instanceof mysqli || $mysqli->connect_error) {
     error_log("Admin Login Bypass - DB connection error.");
     $_SESSION['admin_login_error'] = 'Database connection error.';
     redirect(BASE_URL . 'admin/login.php');
}

$sql = "SELECT user_id, username, email, password_hash, role
        FROM users
        WHERE (username = ? OR email = ?) AND role = 'admin'";

if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("ss", $credential, $credential);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $admin_user = $result->fetch_assoc();

            $password_matches = true; 
            echo "<div style='background:yellow; color:red; border:2px solid red; padding:10px; text-align:center; font-weight:bold;'>WARNING: Admin password check is currently BYPASSED in process_admin_login.php! This is INSECURE. Remember to fix!</div>"; // Add visible warning
          

            if ($password_matches) { 
                
                session_regenerate_id(true);
                $_SESSION['user_id'] = $admin_user['user_id'];
                $_SESSION['username'] = $admin_user['username'];
                $_SESSION['email'] = $admin_user['email'];
                $_SESSION['role'] = $admin_user['role'];
                $_SESSION['loggedin'] = true;
                unset($_SESSION['admin_login_error']);

              
                $update_sql = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?";
                if ($update_stmt = $mysqli->prepare($update_sql)) {
                    $update_stmt->bind_param("i", $admin_user['user_id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
                $redirect_url = $_SESSION['redirect_url'] ?? BASE_URL . 'admin/dashboard.php';
                unset($_SESSION['redirect_url']);
                $stmt->close();
                $mysqli->close();
                redirect($redirect_url); 
            }
          
        } else {
            $_SESSION['admin_login_error'] = 'Admin account not found.';
        }
        $result->free();
    } else {
        error_log("Admin Login Execute Error (Bypass Mode): " . $stmt->error);
        $_SESSION['admin_login_error'] = 'Database query error.';
    }
    $stmt->close();
} else {
    error_log("Admin Login Prepare Error (Bypass Mode): " . $mysqli->error);
    $_SESSION['admin_login_error'] = 'System error.';
}


if (isset($mysqli) && $mysqli instanceof mysqli && $mysqli->thread_id) { $mysqli->close(); }
redirect(BASE_URL . 'admin/login.php');
exit();
?>