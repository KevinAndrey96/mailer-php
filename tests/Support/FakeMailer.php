<?php

declare(strict_types=1);

namespace SmtpMailer\Tests\Support;

use SmtpMailer\Application\DeliveryException;
use SmtpMailer\Application\Mailer;
use SmtpMailer\Domain\Message;

final class FakeMailer implements Mailer
{
    /** @var list<Message> */
    public array $sent = [];

    public function __construct(private readonly ?string $failure = null)
    {
    }

    public function send(Message $message): string
    {
        if ($this->failure !== null) {
            throw new DeliveryException($this->failure);
        }
        $this->sent[] = $message;

        return '<fake-' . count($this->sent) . '@smtp-mailer-api.test>';
    }
}
