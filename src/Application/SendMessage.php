<?php

declare(strict_types=1);

namespace SmtpMailer\Application;

use SmtpMailer\Domain\Message;

/** Use case: check every recipient against the policy, then hand the message to the mailer. */
final readonly class SendMessage
{
    public function __construct(private Mailer $mailer, private RecipientPolicy $policy)
    {
    }

    /**
     * @return string the Message-ID of the sent message
     *
     * @throws RecipientNotAllowedException
     * @throws DeliveryException
     */
    public function __invoke(Message $message): string
    {
        foreach ($message->to as $recipient) {
            if (!$this->policy->allows($recipient)) {
                throw new RecipientNotAllowedException("recipient domain {$recipient->domain} is not allowed");
            }
        }

        return $this->mailer->send($message);
    }
}
