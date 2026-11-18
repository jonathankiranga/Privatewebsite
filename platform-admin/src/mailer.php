<?php
declare(strict_types=1);

/*
 * PHPMailer 6.9.3 is bundled in this repository. No Composer autoloader,
 * vendor directory, shell access or runtime download is required.
 */
require_once __DIR__ . '/../libraries/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../libraries/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libraries/PHPMailer/src/SMTP.php';

/**
 * Send platform notifications through the configured authenticated SMTP
 * account. There is deliberately no PHP mail() fallback.
 */
function platform_send_mail(string $to, string $subject, string $body, string $fromName = 'SmarterNow'): bool
{
    $from = trim((string)getenv('SMARTNOW_MAIL_FROM'));
    $host = trim((string)getenv('SMARTNOW_SMTP_HOST'));
    $username = trim((string)getenv('SMARTNOW_SMTP_USERNAME'));
    $password = (string)getenv('SMARTNOW_SMTP_PASSWORD');
    $port = (int)getenv('SMARTNOW_SMTP_PORT');
    $encryption = strtolower(trim((string)getenv('SMARTNOW_SMTP_ENCRYPTION')));

    if (!class_exists(\\PHPMailer\\PHPMailer\\PHPMailer::class)) {
        error_log('SmarterNow email unavailable: bundled PHPMailer classes could not be loaded.');
        return false;
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL) ||
        $host === '' || $username === '' || $password === '' || $port < 1 || $port > 65535 ||
        !in_array($encryption, ['ssl', 'tls'], true)) {
        error_log('SmarterNow SMTP email skipped: recipient, sender or SMTP configuration is incomplete.');
        return false;
    }

    $mail = new \\PHPMailer\\PHPMailer\\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = $encryption === 'ssl'
            ? \\PHPMailer\\PHPMailer\\PHPMailer::ENCRYPTION_SMTPS
            : \\PHPMailer\\PHPMailer\\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $port;
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(false);
        $mail->Timeout = 20;
        $mail->SMTPDebug = 0;
        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        error_log('SmarterNow SMTP server accepted an email for recipient hash ' . hash('sha256', strtolower($to)) . '.');
        return true;
    } catch (\\Throwable $e) {
        error_log('SmarterNow SMTP delivery failed: ' . ($mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage()));
        return false;
    }
}
