<?php

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$errors = [];
$form_data = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors['csrf'] = 'Invalid security token.';
        $_SESSION['register_errors'] = $errors;
        redirect('register.php');
    }

    $username = sanitize_input($_POST['username']);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $full_name = sanitize_input($_POST['full_name']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    $form_data = $_POST;
    unset($form_data['password'], $form_data['confirm_password'], $form_data['csrf_token'], $form_data['g-recaptcha-response']);

    if (empty($recaptcha_response)) {
        $errors['recaptcha'] = "Please complete the reCAPTCHA verification.";
    } else {
        $secretKey = RECAPTCHA_SECRET_KEY;
        $verifyURL = 'https://www.google.com/recaptcha/api/siteverify';
        $postData = http_build_query([
            'secret'   => $secretKey,
            'response' => $recaptcha_response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $verifyURL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $errors['recaptcha'] = "reCAPTCHA verification request failed. Please try again.";
            error_log("reCAPTCHA cURL Error: " . $curl_error);
        } else {
            $responseData = json_decode($response, true);
            if (!$responseData || !isset($responseData['success'])) {
                $errors['recaptcha'] = "Invalid response from reCAPTCHA server.";
                error_log("reCAPTCHA Invalid JSON Response: " . $response);
            } elseif ($responseData['success'] !== true) {
                $errors['recaptcha'] = "reCAPTCHA verification failed. Please try again.";
                $error_codes = $responseData['error-codes'] ?? [];
                error_log("reCAPTCHA Verification Failed. Error codes: " . implode(', ', $error_codes));
            }
        }
    }

    if (empty($username)) { $errors['username'] = "Username is required."; }
    elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) { $errors['username'] = "Username must be 3-20 characters (letters, numbers, underscore)."; }

    if (!$email) { $errors['email'] = "A valid email address is required."; }

    if (empty($password)) { $errors['password'] = "Password is required."; }
    elseif (strlen($password) < 8) { $errors['password'] = "Password must be at least 8 characters long."; }

    if ($password !== $confirm_password) { $errors['confirm_password'] = "Passwords do not match."; }

    if (empty($errors)) {
        if (!isset($mysqli) || !$mysqli instanceof mysqli || $mysqli->connect_error) {
            $errors['database'] = "Database connection error.";
            error_log("Register Error: DB connection failed before duplicate check.");
        } else {
            $sql_check = "SELECT user_id FROM users WHERE username = ? OR email = ?";
            $stmt_check = $mysqli->prepare($sql_check);
            if ($stmt_check) {
                $stmt_check->bind_param("ss", $username, $email);
                $stmt_check->execute();
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $stmt_check->bind_result($found_id); $stmt_check->fetch();
                    $stmt_user = $mysqli->prepare("SELECT user_id FROM users WHERE username = ?");
                    if($stmt_user){ $stmt_user->bind_param("s", $username); $stmt_user->execute(); $stmt_user->store_result(); if($stmt_user->num_rows > 0) $errors['username'] = "Username already taken."; $stmt_user->close(); }
                    $stmt_email = $mysqli->prepare("SELECT user_id FROM users WHERE email = ?");
                    if($stmt_email){ $stmt_email->bind_param("s", $email); $stmt_email->execute(); $stmt_email->store_result(); if($stmt_email->num_rows > 0) $errors['email'] = "Email already registered."; $stmt_email->close(); }
                }
                $stmt_check->close();
            } else {
                $errors['database'] = "Error checking existing user.";
                error_log("Register check prepare failed: " . $mysqli->error);
            }
        }
    }

    if (empty($errors)) {
        if (!isset($mysqli) || !$mysqli instanceof mysqli || $mysqli->connect_error) {
            $errors['database'] = "Database connection error before insert.";
            error_log("Register Error: DB connection failed before insert.");
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $sql_insert = "INSERT INTO users (username, email, password_hash, full_name, role, created_at) VALUES (?, ?, ?, ?, 'user', NOW())";
            $stmt_insert = $mysqli->prepare($sql_insert);
            if ($stmt_insert) {
                $stmt_insert->bind_param("ssss", $username, $email, $password_hash, $full_name);
                if ($stmt_insert->execute()) {
                    set_message("Registration successful! You can now log in.", 'success');
                    unset($_SESSION['form_data']);
                    $stmt_insert->close();
                    $mysqli->close();
                    redirect('login.php');
                } else {
                    $errors['database'] = "Registration failed. Please try again.";
                    error_log("Register insert failed: " . $stmt_insert->error);
                }
                if(isset($stmt_insert) && $stmt_insert instanceof mysqli_stmt) $stmt_insert->close();
            } else {
                $errors['database'] = "Error preparing registration.";
                error_log("Register insert prepare failed: " . $mysqli->error);
            }
        }
    }

    if (!empty($errors)) {
        $_SESSION['register_errors'] = $errors;
        $_SESSION['form_data'] = $form_data;
        if (isset($mysqli) && $mysqli instanceof mysqli) $mysqli->close();
        redirect('register.php');
    }

    if (isset($mysqli) && $mysqli instanceof mysqli) $mysqli->close();
    exit();

} else {
    redirect('register.php');
}
?>
