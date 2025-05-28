<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if (isLoggedIn()) { redirect(BASE_URL . 'account.php'); }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $errors = [];

    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors['csrf'] = 'Invalid security token.';
        $_SESSION['reset_errors'] = $errors;
        redirect("login.php");
    }

    $token = $_POST['token'] ?? '';
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    if (empty($token)) $errors['token'] = "Reset token is missing.";
    if (!$email) $errors['email'] = "Associated email is missing or invalid.";
    if (empty($new_password)) $errors['new_password'] = "New password cannot be empty.";
    elseif (strlen($new_password) < 8) $errors['password_length'] = "Password must be at least 8 characters.";
    if ($new_password !== $confirm_new_password) $errors['password_match'] = "The new passwords do not match.";

    if (!isset($mysqli) || !$mysqli instanceof mysqli || $mysqli->connect_error) {
        $errors['database'] = 'Database connection error. Please try again later.';
        error_log("Process Reset Password - DB connection error.");
    }

    $is_token_valid_server = false;
    if (empty($errors)) {
        $sql_validate = "SELECT email FROM password_resets WHERE token = ? AND email = ? AND expires_at > NOW()";
        $stmt_validate = $mysqli->prepare($sql_validate);
        if($stmt_validate) {
            $stmt_validate->bind_param("ss", $token, $email);
            $stmt_validate->execute();
            $stmt_validate->store_result();
            if ($stmt_validate->num_rows === 1) {
                $is_token_valid_server = true;
            } else {
                $errors['token_valid'] = "Invalid or expired password reset token. Please request a new link.";
            }
            $stmt_validate->close();
        } else {
            $errors['database'] = "Error validating reset token.";
            error_log("Process Reset PW - Token validation prepare error: " . $mysqli->error);
        }
    }

    if (empty($errors) && $is_token_valid_server) {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

        $mysqli->begin_transaction();
        try {
            $sql_update = "UPDATE users SET password_hash = ? WHERE email = ?";
            $stmt_update = $mysqli->prepare($sql_update);
            if (!$stmt_update) throw new Exception("User update prepare failed: ".$mysqli->error);
            $stmt_update->bind_param("ss", $password_hash, $email);
            if (!$stmt_update->execute()) throw new Exception("User update execute failed: ".$stmt_update->error);
            $stmt_update->close();

            $sql_delete = "DELETE FROM password_resets WHERE email = ?";
            $stmt_delete = $mysqli->prepare($sql_delete);
            if (!$stmt_delete) throw new Exception("Token delete prepare failed: ".$mysqli->error);
            $stmt_delete->bind_param("s", $email);
            if (!$stmt_delete->execute()) throw new Exception("Token delete execute failed: ".$stmt_delete->error);
            $stmt_delete->close();

            $mysqli->commit();

            set_message("Your password has been reset successfully. You can now log in.", "success");
            if(isset($mysqli) && $mysqli instanceof mysqli) $mysqli->close();
            redirect("login.php");

        } catch (Exception $e) {
            $mysqli->rollback();
            error_log("Password Reset Processing Error for $email: " . $e->getMessage());
            $errors['database'] = "Failed to update password due to a server error. Please try the reset process again.";
            if(isset($stmt_update) && $stmt_update instanceof mysqli_stmt) $stmt_update->close();
            if(isset($stmt_delete) && $stmt_delete instanceof mysqli_stmt) $stmt_delete->close();
        }
    }

    if (!empty($errors)) {
        $_SESSION['reset_errors'] = $errors;
        if(isset($mysqli) && $mysqli instanceof mysqli && $mysqli->thread_id) $mysqli->close();
        redirect("reset_password.php?token=" . urlencode($token));
    }

} else {
    redirect('login.php');
}

if (isset($mysqli) && $mysqli instanceof mysqli && $mysqli->thread_id) { $mysqli->close(); }
exit();
?>
