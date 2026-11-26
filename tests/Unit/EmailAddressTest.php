<?php

declare(strict_types=1);

namespace SmtpMailer\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SmtpMailer\Domain\EmailAddress;
use SmtpMailer\Domain\ValidationException;

final class EmailAddressTest extends TestCase
{
    public function testLowerCasesTheDomainButKeepsTheLocalPart(): void
    {
        $address = EmailAddress::parse('  Ventas.Kevin@Example.COM ');

        self::assertSame('Ventas.Kevin@example.com', $address->value);
        self::assertSame('example.com', $address->domain);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAddresses(): iterable
    {
        yield 'empty' => [''];
        yield 'no at sign' => ['kevin.example.com'];
        yield 'no domain' => ['kevin@'];
        yield 'header injection' => ["kevin@example.com\r\nBcc: victim@example.org"];
        yield 'null byte' => ["kevin@example.com\0"];
        yield 'too long' => [str_repeat('a', 64) . '@' . str_repeat('b', 190) . '.com'];
    }

    #[DataProvider('invalidAddresses')]
    public function testRejectsInvalidAddresses(string $value): void
    {
        try {
            EmailAddress::parse($value, 'to[0]');
            self::fail('accepted an invalid address');
        } catch (ValidationException $e) {
            self::assertSame(['to[0]' => 'is not a valid email address'], $e->errors());
            self::assertSame('invalid message: to[0] is not a valid email address', $e->getMessage());
        }
    }
}
