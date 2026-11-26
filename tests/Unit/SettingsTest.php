<?php

declare(strict_types=1);

namespace SmtpMailer\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SmtpMailer\Config\Settings;

final class SettingsTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    public static function validEnvironment(): array
    {
        return [
            'SMTP_HOST' => 'smtp.example.com',
            'MAIL_FROM_ADDRESS' => 'no-reply@example.com',
            'API_TOKEN' => str_repeat('k', 32),
            'ALLOWED_RECIPIENT_DOMAINS' => 'example.com',
        ];
    }

    public function testAppliesSafeDefaults(): void
    {
        $settings = Settings::fromEnvironment(self::validEnvironment());

        self::assertSame(587, $settings->smtpPort);
        self::assertSame('tls', $settings->smtpEncryption);
        self::assertSame(10, $settings->maxRecipients);
        self::assertSame([], $settings->allowedOrigins);
        self::assertSame('', $settings->smtpUsername);
    }

    public function testReadsEveryVariable(): void
    {
        $settings = Settings::fromEnvironment([...self::validEnvironment(),
            'SMTP_PORT' => '465',
            'SMTP_ENCRYPTION' => 'SSL',
            'SMTP_USERNAME' => 'mailer',
            'SMTP_PASSWORD' => 'secret',
            'MAIL_FROM_NAME' => 'Kevin',
            'MAX_RECIPIENTS' => '3',
            'ALLOWED_ORIGINS' => 'https://kevin.example, https://shop.example',
            'UNRELATED' => false,
        ]);

        self::assertSame(465, $settings->smtpPort);
        self::assertSame('ssl', $settings->smtpEncryption);
        self::assertSame('mailer', $settings->smtpUsername);
        self::assertSame('secret', $settings->smtpPassword);
        self::assertSame('Kevin', $settings->fromName);
        self::assertSame(3, $settings->maxRecipients);
        self::assertSame(['https://kevin.example', 'https://shop.example'], $settings->allowedOrigins);
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function invalidEnvironments(): iterable
    {
        yield 'nothing set' => [[], 'SMTP_HOST is required; MAIL_FROM_ADDRESS is required; API_TOKEN is required; ALLOWED_RECIPIENT_DOMAINS is required'];
        yield 'short token' => [['API_TOKEN' => 'short'], 'API_TOKEN needs at least 32 characters'];
        yield 'unknown encryption' => [['SMTP_ENCRYPTION' => 'starttls'], 'SMTP_ENCRYPTION must be tls, ssl or none'];
        yield 'port out of range' => [['SMTP_PORT' => '70000'], 'SMTP_PORT must be between 1 and 65535'];
        yield 'port not a number' => [['SMTP_PORT' => 'smtp'], 'SMTP_PORT must be between 1 and 65535'];
        yield 'zero recipients' => [['MAX_RECIPIENTS' => '0'], 'MAX_RECIPIENTS must be between 1 and 100'];
    }

    /**
     * @param array<string, string> $overrides
     */
    #[DataProvider('invalidEnvironments')]
    public function testNamesEveryProblem(array $overrides, string $expected): void
    {
        $environment = $overrides === [] ? [] : [...self::validEnvironment(), ...$overrides];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("invalid configuration: {$expected}");

        Settings::fromEnvironment($environment);
    }
}
