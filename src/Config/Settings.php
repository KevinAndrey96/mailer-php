<?php

declare(strict_types=1);

namespace SmtpMailer\Config;

use InvalidArgumentException;

/**
 * Service configuration, read once from environment variables. Credentials never travel in
 * requests: a client only proves it holds the API token.
 */
final readonly class Settings
{
    public const int MIN_TOKEN_LENGTH = 32;

    /**
     * @param 'tls'|'ssl'|'none' $smtpEncryption
     * @param list<string> $allowedOrigins
     */
    public function __construct(
        public string $smtpHost,
        public int $smtpPort,
        public string $smtpEncryption,
        public string $smtpUsername,
        public string $smtpPassword,
        public string $fromAddress,
        public string $fromName,
        public string $apiToken,
        public string $allowedRecipientDomains,
        public int $maxRecipients,
        public array $allowedOrigins,
    ) {
    }

    /**
     * @param array<string, string|false> $env usually getenv()
     *
     * @throws InvalidArgumentException naming every missing or invalid variable
     */
    public static function fromEnvironment(array $env): self
    {
        $value = static fn (string $name, string $default = ''): string => is_string($env[$name] ?? null) && $env[$name] !== ''
            ? $env[$name]
            : $default;
        $problems = [];
        foreach (['SMTP_HOST', 'MAIL_FROM_ADDRESS', 'API_TOKEN', 'ALLOWED_RECIPIENT_DOMAINS'] as $required) {
            if ($value($required) === '') {
                $problems[] = "{$required} is required";
            }
        }

        $token = $value('API_TOKEN');
        if ($token !== '' && strlen($token) < self::MIN_TOKEN_LENGTH) {
            $problems[] = 'API_TOKEN needs at least ' . self::MIN_TOKEN_LENGTH . ' characters';
        }

        $encryption = strtolower($value('SMTP_ENCRYPTION', 'tls'));
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            $problems[] = 'SMTP_ENCRYPTION must be tls, ssl or none';
        }

        $port = filter_var($value('SMTP_PORT', '587'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if ($port === false) {
            $problems[] = 'SMTP_PORT must be between 1 and 65535';
        }

        $maxRecipients = filter_var($value('MAX_RECIPIENTS', '10'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($maxRecipients === false) {
            $problems[] = 'MAX_RECIPIENTS must be between 1 and 100';
        }

        if ($problems !== [] || $port === false || $maxRecipients === false || !in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            throw new InvalidArgumentException('invalid configuration: ' . implode('; ', $problems));
        }

        return new self(
            smtpHost: $value('SMTP_HOST'),
            smtpPort: $port,
            smtpEncryption: $encryption,
            smtpUsername: $value('SMTP_USERNAME'),
            smtpPassword: $value('SMTP_PASSWORD'),
            fromAddress: $value('MAIL_FROM_ADDRESS'),
            fromName: $value('MAIL_FROM_NAME'),
            apiToken: $token,
            allowedRecipientDomains: $value('ALLOWED_RECIPIENT_DOMAINS'),
            maxRecipients: $maxRecipients,
            allowedOrigins: array_values(array_filter(
                array_map(trim(...), explode(',', $value('ALLOWED_ORIGINS'))),
                static fn (string $origin): bool => $origin !== '',
            )),
        );
    }
}
