<?php

declare(strict_types=1);

namespace SmtpMailer\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SmtpMailer\Domain\EmailAddress;
use SmtpMailer\Domain\Message;
use SmtpMailer\Domain\ValidationException;

final class MessageTest extends TestCase
{
    public function testBuildsAValidMessage(): void
    {
        $message = Message::create(
            [EmailAddress::parse('cliente@example.com')],
            '  Confirmación de registro ',
            'Hola, tu registro está completo.',
            '<p>Hola, tu registro está completo.</p>',
            EmailAddress::parse('soporte@example.com'),
        );

        self::assertSame('Confirmación de registro', $message->subject);
        self::assertSame('cliente@example.com', $message->to[0]->value);
        self::assertSame('soporte@example.com', $message->replyTo?->value);
    }

    /**
     * @return iterable<string, array{list<EmailAddress>, string, string, ?string, array<string, string>}>
     */
    public static function invalidMessages(): iterable
    {
        $one = [EmailAddress::parse('a@example.com')];
        $eleven = array_fill(0, 11, EmailAddress::parse('a@example.com'));

        yield 'no recipients' => [[], 'Asunto', 'Texto', null, ['to' => 'needs at least one recipient']];
        yield 'too many recipients' => [$eleven, 'Asunto', 'Texto', null, ['to' => 'accepts at most 10 recipients']];
        yield 'empty subject' => [$one, '   ', 'Texto', null, ['subject' => 'is required']];
        yield 'subject header injection' => [$one, "Hola\r\nBcc: x@example.org", 'Texto', null, ['subject' => 'cannot contain line breaks']];
        yield 'long subject' => [$one, str_repeat('á', 201), 'Texto', null, ['subject' => 'is longer than 200 characters']];
        yield 'missing text' => [$one, 'Asunto', ' ', '<p>Hola</p>', ['text' => 'is required, also as the plain text alternative of html']];
        yield 'huge text' => [$one, 'Asunto', str_repeat('x', 100_001), null, ['text' => 'is larger than 100000 bytes']];
        yield 'huge html' => [$one, 'Asunto', 'Texto', str_repeat('x', 100_001), ['html' => 'is larger than 100000 bytes']];
        yield 'invalid utf-8' => [$one, 'Asunto', "Texto \xC3\x28", null, ['text' => 'is not valid UTF-8']];
        yield 'several problems' => [[], '', '', null, [
            'to' => 'needs at least one recipient',
            'subject' => 'is required',
            'text' => 'is required, also as the plain text alternative of html',
        ]];
    }

    /**
     * @param list<EmailAddress> $to
     * @param array<string, string> $expected
     */
    #[DataProvider('invalidMessages')]
    public function testReportsEveryInvalidField(array $to, string $subject, string $text, ?string $html, array $expected): void
    {
        try {
            Message::create($to, $subject, $text, $html);
            self::fail('accepted an invalid message');
        } catch (ValidationException $e) {
            self::assertSame($expected, $e->errors());
        }
    }
}
