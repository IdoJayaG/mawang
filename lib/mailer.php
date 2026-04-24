<?php

/**
 * Send email using SMTP (PHPMailer) when configured, fallback to native mail().
 *
 * @param string $toEmail
 * @param string $toName
 * @param string $subject
 * @param string $htmlBody
 * @param string $plainBody
 * @param string|null $error
 * @return bool
 */
function app_send_email($toEmail, $toName, $subject, $htmlBody, $plainBody = '', &$error = null)
{
    $error = null;

    $toEmail = trim((string)$toEmail);
    $toName = trim((string)$toName);
    $subject = trim((string)$subject);

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Alamat email tujuan tidak valid.';
        return false;
    }

    if ($subject === '') {
        $error = 'Subjek email wajib diisi.';
        return false;
    }

    $transport = defined('MAIL_TRANSPORT') ? strtolower((string)MAIL_TRANSPORT) : 'mail';

    if ($transport === 'smtp') {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            $error = 'PHPMailer belum tersedia. Jalankan: composer require phpmailer/phpmailer';
            return false;
        }

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = (string)MAIL_HOST;
            $mail->Port = (int)MAIL_PORT;
            $mail->SMTPAuth = ((string)MAIL_USERNAME !== '');
            $mail->Username = (string)MAIL_USERNAME;
            $mail->Password = (string)MAIL_PASSWORD;

            $enc = strtolower((string)MAIL_ENCRYPTION);
            if ($enc === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'tls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
            }

            $mail->CharSet = 'UTF-8';
            $mail->setFrom((string)MAIL_FROM_ADDRESS, (string)MAIL_FROM_NAME);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $plainBody !== '' ? $plainBody : strip_tags($htmlBody);
            $mail->send();
            return true;
        } catch (Throwable $e) {
            $error = $e->getMessage();
            return false;
        }
    }

    // Fallback to native mail()
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $fromName = (string)MAIL_FROM_NAME;
    $fromAddress = (string)MAIL_FROM_ADDRESS;

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $fromAddress . '>';
    $headers[] = 'Reply-To: ' . $fromAddress;

    $ok = @mail($toEmail, $encodedSubject, $htmlBody, implode("\r\n", $headers));
    if (!$ok) {
        $error = 'Fungsi mail() gagal mengirim email. Pastikan SMTP/sendmail server aktif.';
    }

    return $ok;
}
