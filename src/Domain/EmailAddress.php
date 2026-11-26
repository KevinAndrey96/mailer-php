<?php

declare(strict_types=1);

namespace SmtpMailer\Domain;

/**
 * A syntactically valid mailbox. The domain is lower-cased so allowlists compare reliably, and
 * control characters are rejected outright to rule out SMTP header injection.
 */
final readonly class EmailAddress
{
    private const int MAX_LENGTH = 254;

    private function __construct(public string $value, public string $domain)
    {
    }

    /**
     * @throws ValidationException
     */
    public static function parse(string $value, string $field = 'email'): self
    {
        $hasControlCharacters = preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
        $value = trim($value, ' ');
        if (
            $hasControlCharacters
            || $value === ''
            || strlen($value) > self::MAX_LENGTH
            || filter_var($value, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw ValidationException::forField($field, 'is not a valid email address');
        }

        $at = strrpos($value, '@');
        $local = substr($value, 0, (int) $at);
        $domain = strtolower(substr($value, (int) $at + 1));

        return new self("{$local}@{$domain}", $domain);
    }
}
