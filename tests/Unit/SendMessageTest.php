<?php

declare(strict_types=1);

namespace SmtpMailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmtpMailer\Application\RecipientNotAllowedException;
use SmtpMailer\Application\RecipientPolicy;
use SmtpMailer\Application\SendMessage;
use SmtpMailer\Domain\EmailAddress;
use SmtpMailer\Domain\Message;
use SmtpMailer\Tests\Support\FakeMailer;

final class SendMessageTest extends TestCase
{
    private static function messageTo(string ...$addresses): Message
    {
        return Message::create(
            array_values(array_map(static fn (string $address): EmailAddress => EmailAddress::parse($address), $addresses)),
            'Asunto',
            'Texto',
        );
    }

    public function testSendsWhenEveryRecipientIsAllowed(): void
    {
        $mailer = new FakeMailer();
        $send = new SendMessage($mailer, RecipientPolicy::fromList(' Example.com , example.org ,'));

        self::assertSame('<fake-1@smtp-mailer-api.test>', $send(self::messageTo('a@example.com', 'b@EXAMPLE.org')));
        self::assertCount(1, $mailer->sent);
    }

    public function testRefusesTheWholeMessageIfOneRecipientIsOutsideTheAllowlist(): void
    {
        $mailer = new FakeMailer();
        $send = new SendMessage($mailer, RecipientPolicy::fromList('example.com'));

        try {
            $send(self::messageTo('a@example.com', 'victim@gmail.com'));
            self::fail('sent to a forbidden domain');
        } catch (RecipientNotAllowedException $e) {
            self::assertSame('recipient domain gmail.com is not allowed', $e->getMessage());
        }
        self::assertSame([], $mailer->sent);
    }

    public function testSubdomainsAreNotImplicitlyAllowed(): void
    {
        $policy = RecipientPolicy::fromList('example.com');

        self::assertFalse($policy->allows(EmailAddress::parse('a@mail.example.com')));
        self::assertFalse($policy->allows(EmailAddress::parse('a@example.com.evil.org')));
    }

    public function testAWildcardAllowsAnyDomain(): void
    {
        self::assertTrue(RecipientPolicy::fromList('*')->allows(EmailAddress::parse('a@anywhere.net')));
    }

    public function testAnEmptyPolicyAllowsNothing(): void
    {
        self::assertFalse(RecipientPolicy::fromList(' , ')->allows(EmailAddress::parse('a@example.com')));
    }
}
