<?php

declare(strict_types=1);

namespace SmtpMailer\Domain;

use DomainException;

/**
 * Raised when a message or address breaks a rule. Carries one message per invalid field so the
 * HTTP layer can report them all at once.
 */
final class ValidationException extends DomainException
{
    /**
     * @param array<string, string> $errors field name => reason
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('invalid message: ' . implode('; ', array_map(
            static fn (string $field, string $reason): string => "{$field} {$reason}",
            array_keys($errors),
            $errors,
        )));
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public static function forField(string $field, string $reason): self
    {
        return new self([$field => $reason]);
    }
}
