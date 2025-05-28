<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$contact_errors = [];
$contact_success = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_contact'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
         $contact_errors[] = 'Invalid request token.';
    } else {
        $name = sanitize_input($_POST['name'] ?? '');
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $subject = sanitize_input($_POST['subject'] ?? '');
        $message = sanitize_input($_POST['message'] ?? '');

        if (empty($name)) { $contact_errors[] = "Name is required."; }
        if (empty($email)) { $contact_errors[] = "Email is required."; }
        elseif($email === false){ $contact_errors[] = "Invalid email format."; }
        if (empty($subject)) { $contact_errors[] = "Subject is required."; }
        if (empty($message)) { $contact_errors[] = "Message cannot be empty."; }

        if (empty($contact_errors)) {
            $admin_email = ADMIN_EMAIL_RECIPIENT;
            $email_subject = "Website Contact Form: " . $subject;

            $html_body = "You have received a new message from the website contact form:<br><br>" .
                         "<strong>Name:</strong> " . htmlspecialchars($name) . "<br>" .
                         "<strong>Email:</strong> " . htmlspecialchars($email) . "<br>" .
                         "<strong>Subject:</strong> " . htmlspecialchars($subject) . "<br><br>" .
                         "<strong>Message:</strong><br>" . nl2br(htmlspecialchars($message));
            $alt_body = "New contact form message:\n\n" .
                        "Name: " . $name . "\n" .
                        "Email: " . $email . "\n" .
                        "Subject: " . $subject . "\n\n" .
                        "Message:\n" . $message;

            if (send_email($admin_email, SITE_NAME . " Admin", $email_subject, $html_body, $alt_body, $email, $name)) {
                $contact_success = "Thank you for contacting us! Your message has been sent.";
                set_message($contact_success, 'success');
                redirect('contact.php');
                exit;
            } else {
                $contact_errors[] = "Sorry, there was an error sending your message. Please try again later.";
                error_log("Contact form email failed to send. To: $admin_email, From: $email");
            }
        }
    }
    if(!empty($contact_errors)){
         $_SESSION['contact_errors'] = $contact_errors;
         $_SESSION['contact_form_data'] = $_POST;
         redirect('contact.php');
         exit;
     }
} else {
    redirect('contact.php');
}
?>
