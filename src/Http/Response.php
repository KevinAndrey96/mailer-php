<?php

declare(strict_types=1);

namespace SmtpMailer\Http;

final readonly class Response
{
    private const array SECURITY_HEADERS = [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-store',
        'X-Content-Type-Options' => 'nosniff',
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
        'Referrer-Policy' => 'no-referrer',
    ];

    /**
     * @param array<string, string> $headers
     */
    public function __construct(public int $status, public array $headers, public string $body)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public static function json(int $status, array $payload, array $headers = []): self
    {
        return new self(
            $status,
            [...self::SECURITY_HEADERS, ...$headers],
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, string> $headers
     */
    public static function error(int $status, string $code, string $message, array $fields = [], array $headers = []): self
    {
        $error = ['code' => $code, 'message' => $message];
        if ($fields !== []) {
            $error['fields'] = $fields;
        }

        return self::json($status, ['error' => $error], $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self($this->status, [...$this->headers, ...$headers], $this->body);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body;
    }
}
