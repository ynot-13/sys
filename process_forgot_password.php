<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$autoloaderPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloaderPath)) {
    error_log("CRITICAL ERROR: Composer autoloader not found at $autoloaderPath.");
    $_SESSION['forgot_errors'] = ['A system error occurred. Please try again later. (Ref: AUTOLOAD)'];
    redirect('forgot_password.php');
}
require_once $autoloaderPath;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $errors = [];

    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['forgot_errors'] = ['Invalid security token. Please try again.'];
        redirect('forgot_password.php');
    }

    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    if (!$email) {
        $_SESSION['forgot_success'] = 'If an account with that email exists, password reset instructions have been sent.';
        redirect('forgot_password.php');
    }

    if (!isset($mysqli) || !$mysqli instanceof mysqli || $mysqli->connect_error) {
        error_log("Forgot Password - DB connection error in process.");
        $_SESSION['forgot_errors'] = ['A server error occurred. Please try again later. (Ref: DBINIT)'];
        redirect('forgot_password.php');
    }

    $user_id = null;
    $sql_check = "SELECT user_id FROM users WHERE email = ?";
    $stmt_check = $mysqli->prepare($sql_check);

    if ($stmt_check) {
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows === 1) {
            $user_id = $result_check->fetch_assoc()['user_id'];
            $token_stored = false;
            $token = bin2hex(random_bytes(32));
            $expires = time() + PASSWORD_RESET_TIMEOUT;

            $mysqli->begin_transaction();
            try {
                $sql_delete = "DELETE FROM password_resets WHERE email = ?";
                if($stmt_del = $mysqli->prepare($sql_delete)){
                    $stmt_del->bind_param("s", $email);
                    $stmt_del->execute();
                    $stmt_del->close();
                } else { throw new Exception("Token deletion prepare failed."); }

                $sql_insert = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))";
                if ($stmt_ins = $mysqli->prepare($sql_insert)) {
                    $stmt_ins->bind_param("ssi", $email, $token, $expires);
                    if ($stmt_ins->execute()) { $token_stored = true; }
                    else { throw new Exception("Token insertion execute failed: ".$stmt_ins->error); }
                    $stmt_ins->close();
                } else { throw new Exception("Token insertion prepare failed."); }

                $mysqli->commit();

            } catch (Exception $e) {
                $mysqli->rollback();
                error_log("Password Reset Token DB Error for $email: " . $e->getMessage());
                $_SESSION['forgot_success'] = 'If an account with that email exists, password reset instructions have been sent.';
                if(isset($stmt_check) && $stmt_check instanceof mysqli_stmt) $stmt_check->close();
                $mysqli->close();
                redirect('forgot_password.php');
            }

            if ($token_stored) {
                $reset_link = BASE_URL . "reset_password.php?token=" . $token;
                $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host       = MAIL_HOST;
                    $mail->SMTPAuth   = MAIL_SMTPAuth;
                    $mail->Username   = MAIL_USERNAME;
                    $mail->Password   = MAIL_PASSWORD;
                    $mail->SMTPSecure = defined('MAIL_SMTPSECURE') ? MAIL_SMTPSECURE : PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = MAIL_PORT;

                    $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
                    $mail->addAddress($email);

                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Request - ' . SITE_NAME;
                    $mail->Body    = "<p>Hello,</p><p>Click the button below (valid for " . (PASSWORD_RESET_TIMEOUT / 60) . " minutes) to reset your password for " . SITE_NAME . ":</p><p><a href='" . $reset_link . "' style='display: inline-block; padding: 12px 20px; background-color: #FF9494; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>Reset Your Password</a></p><p>Link: " . $reset_link . "</p><p>If you didn't request this, ignore this email.</p>";
                    $mail->AltBody = "Reset your password for " . SITE_NAME . " by visiting this link (valid " . (PASSWORD_RESET_TIMEOUT / 60) . " mins):\n" . $reset_link . "\n\nIf you didn't request this, ignore this email.";

                    $mail->send();
                    $_SESSION['forgot_success'] = 'Password reset instructions have been sent to your email address.';

                } catch (Exception $e) {
                    error_log("PHPMailer Error sending reset to $email (User ID: $user_id): {$mail->ErrorInfo}");
                    $_SESSION['forgot_success'] = 'If an account with that email exists, password reset instructions have been sent.';
                }
            } else {
                $_SESSION['forgot_success'] = 'If an account exists, instructions sent.';
            }

        } else {
            $_SESSION['forgot_success'] = 'If an account with that email exists, password reset instructions have been sent.';
            error_log("Forgot PW attempt - email not found: " . $email);
        }
        if(isset($result_check)) $result_check->free();
    } else {
        error_log("Forgot PW - Email Check Prepare/Execute Error: " . ($stmt_check ? $stmt_check->error : $mysqli->error));
        $_SESSION['forgot_success'] = 'If an account exists, instructions sent.';
    }
    if(isset($stmt_check) && $stmt_check instanceof mysqli_stmt) $stmt_check->close();
    if(isset($mysqli) && $mysqli instanceof mysqli && $mysqli->thread_id) { $mysqli->close(); }
    redirect('forgot_password.php');

} else {
    redirect('forgot_password.php');
}
?>
