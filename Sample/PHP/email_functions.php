<?php
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

if (class_exists('Dotenv\\Dotenv')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->safeLoad();
}

/**
 * Send an email to the admin using Brevo SMTP settings.
 *
 * @param string $subject
 * @param string $body
 * @return void
 */
function sendAdminEmail(string $subject, string $body): void
{
    $host = $_ENV['BREVO_SMTP_HOST'] ?? '';
    $port = (int)($_ENV['BREVO_SMTP_PORT'] ?? 587);
    $username = $_ENV['BREVO_SMTP_USERNAME'] ?? '';
    $password = $_ENV['BREVO_SMTP_PASSWORD'] ?? '';
    $from = $_ENV['BREVO_FROM_EMAIL'] ?? '';
    $fromName = $_ENV['BREVO_FROM_NAME'] ?? 'Stock Bot';

    if (!$host || !$from) {
        error_log('SMTP configuration incomplete.');
        return;
    }

    if (!class_exists(PHPMailer::class)) {
        error_log('PHPMailer library missing. Email not sent.');
        return;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $port;
        $mail->SMTPAuth = !empty($username);
        if ($mail->SMTPAuth) {
            $mail->Username = $username;
            $mail->Password = $password;
        }
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->setFrom($from, $fromName);
        $mail->addAddress($from);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
    }
}

/**
 * Send an email to a specified recipient using Brevo SMTP settings.
 *
 * @param string $to
 * @param string $subject
 * @param string $body
 * @return void
 */
function sendUserEmail(string $to, string $subject, string $body): void
{
    $host = $_ENV['BREVO_SMTP_HOST'] ?? '';
    $port = (int)($_ENV['BREVO_SMTP_PORT'] ?? 587);
    $username = $_ENV['BREVO_SMTP_USERNAME'] ?? '';
    $password = $_ENV['BREVO_SMTP_PASSWORD'] ?? '';
    $from = $_ENV['BREVO_FROM_EMAIL'] ?? '';
    $fromName = $_ENV['BREVO_FROM_NAME'] ?? 'Stock Bot';

    if (!$host || !$from || !$to) {
        error_log('SMTP configuration incomplete.');
        return;
    }

    if (!class_exists(PHPMailer::class)) {
        error_log('PHPMailer library missing. Email not sent.');
        return;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $port;
        $mail->SMTPAuth = !empty($username);
        if ($mail->SMTPAuth) {
            $mail->Username = $username;
            $mail->Password = $password;
        }
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
    }
}

/**
 * Send a low-stock notification email using SMTP credentials.
 *
 * @param string $productName
 * @param int    $stockQty
 * @return void
 */
function sendLowStockEmail(string $productName, int $stockQty): void
{
    $subject = 'Low Stock Alert: ' . $productName;
    $body = "Stock for {$productName} is low. Current level: {$stockQty}.";
    sendAdminEmail($subject, $body);
}

/**
 * Notify the admin whenever a new order is placed.
 *
 * @param int   $orderId
 * @param int   $userId
 * @param float $total
 * @return void
 */
function sendOrderNotificationEmail(int $orderId, int $userId, float $total): void
{
    $subject = 'New Order: #' . $orderId;
    $body = "Order #{$orderId} has been placed by user ID {$userId}. Total amount: {$total}.";
    sendAdminEmail($subject, $body);
}

/**
 * Send an order confirmation email to the user.
 *
 * @param string $toEmail
 * @param int    $orderId
 * @param float  $total
 * @return void
 */
function sendOrderConfirmationEmail(string $toEmail, int $orderId, float $total): void
{
    $subject = 'Order Confirmation: #' . $orderId;
    $body = "Thank you for your order #{$orderId}. Total amount: {$total}.";
    sendUserEmail($toEmail, $subject, $body);
}
?>
