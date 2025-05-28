<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Manila');

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'wealthys_db');

define('SITE_NAME', "WEALTHY'S");
define('BASE_URL', 'http://localhost/relapsing/');

define('CURRENCY_SYMBOL', '₱');
define('CURRENCY_CODE', 'PHP');

define('ADMIN_EMAIL', 'noreply@wealthys.com');
define('SUPPORT_EMAIL', 'wealthys.system.mail@gmail.com');

define('DEFAULT_ADMIN_RECIPIENT_ID', 17);
define('PASSWORD_RESET_TIMEOUT', 1800);
define('PRODUCTS_PER_PAGE', 10);
define('CAPTCHA_NUM_QUESTIONS', 5);

define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_SMTPSECURE', 'tls');
define('MAIL_SMTPAuth', true);
define('MAIL_USERNAME', 'wealthys.system.mail@gmail.com');
define('MAIL_PASSWORD', 'kynf tham jqie llkj');
define('MAIL_FROM_ADDRESS', 'wealthys.system.mail@gmail.com');
define('MAIL_FROM_NAME', SITE_NAME);

define('RECAPTCHA_SITE_KEY', '6Lf2KyUrAAAAAKj1jlMEiZorOIW9M6duAA25WuQE');
define('RECAPTCHA_SECRET_KEY', '6Lf2KyUrAAAAANQWtp499wvrT21OTffxAy91zaST');
?>