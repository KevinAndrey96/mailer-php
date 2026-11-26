<?php

declare(strict_types=1);

namespace SmtpMailer\Application;

use SmtpMailer\Domain\Message;

interface Mailer
{
    /**
     * Delivers the message to the outgoing server.
     *
     * @return string the Message-ID header of the sent message
     *
     * @throws DeliveryException when the server rejects or cannot be reached
     */
    public function send(Message $message): string;
}
