<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        set_message('Invalid request. Please try again.', 'error');
        redirect('login.php');
    }

    $email_username = sanitize_input($_POST['email_username']);
    $password = $_POST['password'];

    $_SESSION['form_data']['email_username'] = $email_username;

    if (empty($email_username) || empty($password)) {
        $_SESSION['login_error'] = "Email/Username and Password are required.";
        redirect('login.php');
    }

    $sql = "SELECT user_id, username, email, password_hash, role FROM users WHERE email = ? OR username = ?";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("ss", $email_username, $email_username);

        if ($stmt->execute()) {
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();

                if (password_verify($password, $user['password_hash'])) {
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['loggedin'] = true;

                    unset($_SESSION['login_error']);
                    unset($_SESSION['form_data']);

                    $update_sql = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?";
                    if ($update_stmt = $mysqli->prepare($update_sql)) {
                        $update_stmt->bind_param("i", $user['user_id']);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }

                    if ($user['role'] == 'admin') {
                        redirect(BASE_URL . 'admin/dashboard.php');
                    } else {
                        if (isset($_SESSION['redirect_url'])) {
                            $redirect_url = $_SESSION['redirect_url'];
                            unset($_SESSION['redirect_url']);
                            redirect($redirect_url);
                        } else {
                            redirect(BASE_URL . 'index.php');
                        }
                    }
                } else {
                    $_SESSION['login_error'] = "Invalid password.";
                    redirect('login.php');
                }
            } else {
                $_SESSION['login_error'] = "No account found with that email or username.";
                redirect('login.php');
            }
        } else {
            error_log("Login statement execution failed: " . $stmt->error);
            $_SESSION['login_error'] = "An error occurred. Please try again later.";
            redirect('login.php');
        }
        $stmt->close();
    } else {
        error_log("Login statement preparation failed: " . $mysqli->error);
        $_SESSION['login_error'] = "An error occurred. Please try again later.";
        redirect('login.php');
    }

    $mysqli->close();

} else {
    redirect('login.php');
}
?>
