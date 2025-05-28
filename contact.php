<?php
$page_title = "Contact Us";
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$autoloaderPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloaderPath)) {
    error_log("CRITICAL ERROR: Composer autoloader not found at $autoloaderPath for contact.php.");
    $contact_errors[] = "Cannot process form due to a system configuration issue.";
} else {
    require_once $autoloaderPath;
}

$contact_errors = $_SESSION['contact_errors'] ?? [];
$contact_success = $_SESSION['contact_success'] ?? '';
$form_data = $_SESSION['contact_form_data'] ?? [];
unset($_SESSION['contact_errors'], $_SESSION['contact_success'], $_SESSION['contact_form_data']);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_contact'])) {
    $contact_errors = [];
    $contact_success = '';
    $form_data = $_POST;

    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
         $contact_errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $name = sanitize_input($_POST['name'] ?? '');
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $subject = sanitize_input($_POST['subject'] ?? '');
        $message = sanitize_input($_POST['message'] ?? '');

        if (empty($name)) { $contact_errors['name'] = "Name is required."; }
        if (empty($email)) { $contact_errors['email'] = "A valid email address is required."; }
        if (empty($subject)) { $contact_errors['subject'] = "Subject is required."; }
        if (empty($message)) { $contact_errors['message'] = "Message cannot be empty."; }

        if (empty($contact_errors)) {
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                 $contact_errors[] = "Email sending library is missing. Cannot send message.";
                 error_log("Contact form error: PHPMailer class not found.");
            }
            elseif (!defined('SUPPORT_EMAIL') || !filter_var(SUPPORT_EMAIL, FILTER_VALIDATE_EMAIL)) {
                $contact_errors[] = "System configuration error: Support email is not set correctly.";
                error_log("Contact form error: SUPPORT_EMAIL constant is not defined or invalid in config.php");
            }
            else {
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
                    $mail->addAddress(SUPPORT_EMAIL, SITE_NAME . ' Support');
                    $mail->addReplyTo($email, $name);

                    $mail->isHTML(false);
                    $mail->Subject = "Wealthy's Contact Form: " . $subject;

                    $email_body = "New contact form submission from " . SITE_NAME . ":\n\n";
                    $email_body .= "\n";
                    $email_body .= " Name:    " . $name . "\n";
                    $email_body .= " Email:   " . $email . "\n";
                    $email_body .= " Subject: " . $subject . "\n";
                    $email_body .= "\n\n";
                    $email_body .= "Message:\n" . $message . "\n\n";
                    $email_body .= "\n";
                    $email_body .= "Sent: " . date('Y-m-d H:i:s') . "\n";
                    $email_body .= "IP Address: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n";

                    $mail->Body = $email_body;

                    $mail->send();
                    $contact_success = "Thank you, " . htmlspecialchars($name) . "! Your message was sent successfully.";
                    
                    unset($_SESSION['contact_form_data']);
                    $form_data = [];

                } catch (Exception $e) {
                    $contact_errors[] = "Sorry, your message could not be sent at this time due to a technical issue. Please try again later.";
                    error_log("PHPMailer Contact Form Error [To: ".SUPPORT_EMAIL." From: $email]: {$mail->ErrorInfo}");
                }
            }
        }
    }

    if (!empty($contact_errors)) {
        $_SESSION['contact_errors'] = $contact_errors;
        $_SESSION['contact_form_data'] = $form_data;
        redirect('contact.php');
    }
}

require_once 'includes/header.php';
?>

<div class="contact-page">
    <h1></h1>
    <p>Have questions or need assistance? We're here to help! Reach out to us using the form below or through our contact details.</p>

    <div class="contact-layout">

        <div class="contact-form-section">
            <h2>Send us a Message</h2>

             <?php if (!empty($contact_errors) && is_array($contact_errors)): ?>
                <div class="alert alert-danger">
                     <i class="fas fa-times-circle"></i>
                     <div>
                         <strong>Please fix the following issues:</strong><br>
                         <?php
                            $display_errors = [];
                            foreach ($contact_errors as $error) {
                                $display_errors[] = htmlspecialchars($error);
                            }
                            echo implode('<br>', $display_errors);
                         ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($contact_success): ?>
                <div class="alert alert-success">
                     <i class="fas fa-check-circle"></i>
                     <span><?php echo htmlspecialchars($contact_success); ?></span>
                </div>
            <?php endif; ?>

            <?php if (empty($contact_success)): ?>
                <form action="contact.php" method="post" id="contact-form">
                     <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                     <div class="form-group">
                        <label for="name">Your Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control <?php echo isset($contact_errors['name']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>" required>
                         <?php if (isset($contact_errors['name'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($contact_errors['name']); ?></div><?php endif; ?>
                    </div>
                     <div class="form-group">
                        <label for="email">Your Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="email" class="form-control <?php echo isset($contact_errors['email']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                         <?php if (isset($contact_errors['email'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($contact_errors['email']); ?></div><?php endif; ?>
                    </div>
                     <div class="form-group">
                        <label for="subject">Subject <span class="text-danger">*</span></label>
                        <input type="text" name="subject" id="subject" class="form-control <?php echo isset($contact_errors['subject']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data['subject'] ?? ''); ?>" required>
                          <?php if (isset($contact_errors['subject'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($contact_errors['subject']); ?></div><?php endif; ?>
                    </div>
                     <div class="form-group">
                        <label for="message">Message <span class="text-danger">*</span></label>
                        <textarea name="message" id="message" rows="6" class="form-control <?php echo isset($contact_errors['message']) ? 'is-invalid' : ''; ?>" required><?php echo htmlspecialchars($form_data['message'] ?? ''); ?></textarea>
                         <?php if (isset($contact_errors['message'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($contact_errors['message']); ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="submit_contact" class="btn"><i class="fas fa-paper-plane"></i> Send Message</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="contact-details-section">
            <h2>Contact Information</h2>
            <p><i class="fas fa-map-marker-alt fa-fw"></i> <span><strong>Address:</strong><br>143 alfa Philippines</span></p>
            <p><i class="fas fa-phone fa-fw"></i> <span><strong>Phone:</strong><br>+63 992 723 5793</span></p>
            <p><i class="fas fa-envelope fa-fw"></i> <span><strong>Email:</strong><br><a href="mailto:<?php echo defined('SUPPORT_EMAIL') ? htmlspecialchars(SUPPORT_EMAIL) : ''; ?>"><?php echo defined('SUPPORT_EMAIL') ? htmlspecialchars(SUPPORT_EMAIL) : 'support@example.com'; ?></a></span></p>
            <p><i class="fas fa-clock fa-fw"></i> <span><strong>Business Hours:</strong><br>Mon - Fri: 9 AM - 10 PM <br> Sat: 10 AM - 5 PM <br>Sun: Closed</span></p>
             <h3>Follow Us</h3>
             <div class="social-links">
                <a href="https://www.facebook.com/" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="https://www.instagram.com/" title="Instagram"><i class="fab fa-instagram"></i></a>
                <a href="https://x.com/" title="Twitter"><i class="fab fa-twitter"></i></a>
            </div>
        </div>
    </div>

     <div class="map-section">
         <h2>Our Location</h2>
         <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3855.2157659093514!2d120.88991837415597!3d14.925067669052359!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x339655327b36f721%3A0x532cbc48034ed561!2sAlfaMart!5e0!3m2!1sen!2sph!4v1745068316454!5m2!1sen!2sph" width="100%" height="400" style="border:0; border-radius: var(--border-radius);" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
     </div>
</div>

<?php require_once 'includes/footer.php'; ?>