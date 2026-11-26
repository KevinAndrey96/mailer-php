<?php

declare(strict_types=1);

namespace SmtpMailer\Infrastructure;

use PHPMailer\PHPMailer\Exception as PhpMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use SmtpMailer\Application\DeliveryException;
use SmtpMailer\Application\Mailer;
use SmtpMailer\Config\Settings;
use SmtpMailer\Domain\Message;

/**
 * Sends through SMTP with PHPMailer. Certificates are always verified: the 2019 version disabled
 * peer verification, which let anyone on the network read the SMTP password.
 */
final readonly class PhpMailerTransport implements Mailer
{
    private const int TIMEOUT_SECONDS = 10;

    public function __construct(private Settings $settings)
    {
    }

    public function send(Message $message): string
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->settings->smtpHost;
            $mail->Port = $this->settings->smtpPort;
            $mail->Timeout = self::TIMEOUT_SECONDS;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->SMTPSecure = match ($this->settings->smtpEncryption) {
                'ssl' => PHPMailer::ENCRYPTION_SMTPS,
                'tls' => PHPMailer::ENCRYPTION_STARTTLS,
                'none' => '',
            };
            $mail->SMTPAutoTLS = $this->settings->smtpEncryption !== 'none';
            $mail->SMTPAuth = $this->settings->smtpUsername !== '';
            $mail->Username = $this->settings->smtpUsername;
            $mail->Password = $this->settings->smtpPassword;

            $mail->setFrom($this->settings->fromAddress, $this->settings->fromName);
            foreach ($message->to as $recipient) {
                $mail->addAddress($recipient->value);
            }
            if ($message->replyTo !== null) {
                $mail->addReplyTo($message->replyTo->value);
            }

            $mail->Subject = $message->subject;
            if ($message->html !== null) {
                $mail->isHTML();
                $mail->Body = $message->html;
                $mail->AltBody = $message->text;
            } else {
                $mail->Body = $message->text;
            }

            $mail->send();
        } catch (PhpMailerException $e) {
            throw new DeliveryException('smtp delivery: ' . $e->getMessage(), 0, $e);
        }

        return $mail->getLastMessageID();
    }
}
