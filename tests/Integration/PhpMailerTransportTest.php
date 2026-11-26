<?php

declare(strict_types=1);

namespace SmtpMailer\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SmtpMailer\Application\DeliveryException;
use SmtpMailer\Config\Settings;
use SmtpMailer\Domain\EmailAddress;
use SmtpMailer\Domain\Message;
use SmtpMailer\Infrastructure\PhpMailerTransport;

/**
 * Delivers real messages to Mailpit. Set MAILPIT_HOST (and optionally MAILPIT_SMTP_PORT and
 * MAILPIT_HTTP_PORT) to run it; CI does, with --fail-on-skipped.
 */
final class PhpMailerTransportTest extends TestCase
{
    private string $host;

    protected function setUp(): void
    {
        $host = getenv('MAILPIT_HOST');
        if (!is_string($host) || $host === '') {
            self::markTestSkipped('MAILPIT_HOST is not set');
        }
        $this->host = $host;
        $this->mailpit('DELETE', '/api/v1/messages');
    }

    private static function port(string $variable, int $default): int
    {
        $value = getenv($variable);

        return is_string($value) && $value !== '' ? (int) $value : $default;
    }

    private function settings(int $smtpPort, string $encryption = 'none'): Settings
    {
        return Settings::fromEnvironment([
            'SMTP_HOST' => $this->host,
            'SMTP_PORT' => (string) $smtpPort,
            'SMTP_ENCRYPTION' => $encryption,
            'MAIL_FROM_ADDRESS' => 'no-reply@example.com',
            'MAIL_FROM_NAME' => 'Kevin Andrey',
            'API_TOKEN' => str_repeat('k', 32),
            'ALLOWED_RECIPIENT_DOMAINS' => 'example.com',
        ]);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function mailpit(string $method, string $path): array
    {
        $context = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true]]);
        $url = "http://{$this->host}:" . self::port('MAILPIT_HTTP_PORT', 8025) . $path;
        $body = file_get_contents($url, false, $context);
        self::assertIsString($body, "mailpit is not reachable at {$url}");
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function testDeliversHtmlWithPlainTextAlternative(): void
    {
        $transport = new PhpMailerTransport($this->settings(self::port('MAILPIT_SMTP_PORT', 1025)));

        $id = $transport->send(Message::create(
            [EmailAddress::parse('cliente@example.com'), EmailAddress::parse('ventas@example.com')],
            'Confirmación de registro',
            'Hola, tu registro está completo.',
            '<p>Hola, tu registro <strong>está completo</strong>.</p>',
            EmailAddress::parse('soporte@example.com'),
        ));

        self::assertMatchesRegularExpression('/^<.+@.+>$/', $id);
        $list = $this->mailpit('GET', '/api/v1/messages');
        self::assertSame(1, $list['total'] ?? null);
        $summary = is_array($list['messages'] ?? null) && is_array($list['messages'][0] ?? null) ? $list['messages'][0] : [];
        self::assertSame('Confirmación de registro', $summary['Subject'] ?? null);
        self::assertIsString($summary['ID'] ?? null);

        $message = $this->mailpit('GET', '/api/v1/message/' . $summary['ID']);
        self::assertEquals(['Address' => 'no-reply@example.com', 'Name' => 'Kevin Andrey'], $message['From'] ?? null);
        self::assertCount(2, is_array($message['To'] ?? null) ? $message['To'] : []);
        self::assertEquals([['Address' => 'soporte@example.com', 'Name' => '']], $message['ReplyTo'] ?? null);
        self::assertSame('Hola, tu registro está completo.', trim(is_string($message['Text'] ?? null) ? $message['Text'] : ''));
        self::assertStringContainsString('<strong>está completo</strong>', is_string($message['HTML'] ?? null) ? $message['HTML'] : '');
    }

    public function testDeliversPlainTextOnly(): void
    {
        $transport = new PhpMailerTransport($this->settings(self::port('MAILPIT_SMTP_PORT', 1025)));

        $transport->send(Message::create([EmailAddress::parse('cliente@example.com')], 'Solo texto', 'Sin HTML.'));

        $list = $this->mailpit('GET', '/api/v1/messages');
        self::assertSame(1, $list['total'] ?? null);
    }

    public function testUnreachableServersRaiseDeliveryErrors(): void
    {
        $transport = new PhpMailerTransport($this->settings(9, 'tls'));

        $this->expectException(DeliveryException::class);
        $this->expectExceptionMessageMatches('/^smtp delivery: /');

        $transport->send(Message::create([EmailAddress::parse('cliente@example.com')], 'Asunto', 'Texto'));
    }
}
