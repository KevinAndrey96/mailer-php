<?php

declare(strict_types=1);

namespace SmtpMailer\Http;

final readonly class Request
{
    /**
     * @param array<string, string> $headers lower-case header name => value
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $headers = [],
        public string $body = '',
    ) {
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    /**
     * Builds the request from PHP globals, reading at most one byte more than the body limit so an
     * oversized upload is detected without loading it whole.
     */
    public static function fromGlobals(int $maxBodyBytes): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($value) && str_starts_with((string) $key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr((string) $key, 5)))] = $value;
            }
        }
        if (is_string($_SERVER['CONTENT_TYPE'] ?? null)) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        // Apache hides Authorization from $_SERVER unless CGIPassAuth is on; getallheaders() always has it.
        foreach (function_exists('getallheaders') ? getallheaders() : [] as $name => $value) {
            if (is_string($value)) {
                $headers[strtolower((string) $name)] = $value;
            }
        }

        $input = fopen('php://input', 'rb');
        $body = $input === false ? '' : (string) stream_get_contents($input, $maxBodyBytes + 1);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = is_string($uri) ? parse_url($uri, PHP_URL_PATH) : '/';

        return new self(
            is_string($method) ? strtoupper($method) : 'GET',
            is_string($path) ? $path : '/',
            $headers,
            $body,
        );
    }
}
