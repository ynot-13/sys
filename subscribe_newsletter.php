<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_message('Invalid request method.', 'error');
    redirect(BASE_URL . "index.php");
}

if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
     set_message('Invalid security token. Please try again.', 'error');
    
     $redirect_url = $_SERVER['HTTP_REFERER'] ?? BASE_URL . "index.php";
     redirect($redirect_url);
}


$email = trim($_POST['email'] ?? '');
$redirect_url = $_SERVER['HTTP_REFERER'] ?? BASE_URL . "index.php";

if (empty($email)) {
    set_message('Email address cannot be empty.', 'warning');
    redirect($redirect_url);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_message('Please enter a valid email address.', 'warning');
    redirect($redirect_url);
}

if (!$mysqli || $mysqli->connect_error) {
     error_log("Database connection error in subscribe_newsletter: " . ($mysqli->connect_error ?? 'Unknown error'));
     set_message('A database error occurred. Please try again later. [Code: DB_CONN]', 'error');
     redirect($redirect_url);
}

$sql_check = "SELECT subscriber_id FROM newsletter_subscribers WHERE email = ?";
$stmt_check = $mysqli->prepare($sql_check);

if (!$stmt_check) {
    error_log("Newsletter Check Prepare Error: " . $mysqli->error);
    set_message('An error occurred. Please try again later. [Code: DB_PREP_CHECK]', 'error');
    redirect($redirect_url);
}

$stmt_check->bind_param("s", $email);
$stmt_check->execute();
$stmt_check->store_result();

if ($stmt_check->num_rows > 0) {
    set_message('You are already subscribed to our newsletter!', 'info');
    $stmt_check->close();
    redirect($redirect_url);
}
$stmt_check->close();


$sql_insert = "INSERT INTO newsletter_subscribers (email, subscribed_at) VALUES (?, NOW())"; // Added subscribed_at
$stmt_insert = $mysqli->prepare($sql_insert);

if (!$stmt_insert) {
    error_log("Newsletter Insert Prepare Error: " . $mysqli->error);
    set_message('An error occurred. Please try again later. [Code: DB_PREP_INS]', 'error');
    redirect($redirect_url);
}

$stmt_insert->bind_param("s", $email);

if ($stmt_insert->execute()) {
    set_message('Thank you for subscribing!', 'success');
} else {
    error_log("Newsletter Insert Execute Error: " . $stmt_insert->error);
     if ($mysqli->errno == 1062) { 
         set_message('You are already subscribed to our newsletter!', 'info');
     } else {
         set_message('Could not subscribe at this time. Please try again later. [Code: DB_EXEC]', 'error');
     }
}

$stmt_insert->close();
if ($mysqli) {
    $mysqli->close();
}

redirect($redirect_url);
?>