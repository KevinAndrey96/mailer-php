<?php

declare(strict_types=1);

namespace SmtpMailer\Http;

use Closure;
use JsonException;
use SmtpMailer\Application\DeliveryException;
use SmtpMailer\Application\RecipientNotAllowedException;
use SmtpMailer\Application\SendMessage;
use SmtpMailer\Config\Settings;
use SmtpMailer\Domain\EmailAddress;
use SmtpMailer\Domain\Message;
use SmtpMailer\Domain\ValidationException;

/**
 * Routes requests: GET /health and POST /messages, with Bearer authentication, JSON validation
 * and CORS for the configured origins only.
 */
final readonly class Kernel
{
    public const int MAX_BODY_BYTES = 256_000;

    /**
     * @param Closure(string): void $log receives server-side details that clients must not see
     */
    public function __construct(
        private SendMessage $sendMessage,
        private Settings $settings,
        private Closure $log,
    ) {
    }

    public function handle(Request $request): Response
    {
        $response = match (true) {
            $request->method === 'OPTIONS' => new Response(204, [], ''),
            $request->path === '/health' && in_array($request->method, ['GET', 'HEAD'], true) => Response::json(200, ['status' => 'ok']),
            $request->path === '/messages' && $request->method === 'POST' => $this->send($request),
            $request->path === '/messages', $request->path === '/health' => Response::error(
                405,
                'method_not_allowed',
                'method not allowed',
                headers: ['Allow' => $request->path === '/health' ? 'GET, HEAD' : 'POST'],
            ),
            default => Response::error(404, 'not_found', 'route not found'),
        };

        return $response->withHeaders($this->cors($request));
    }

    private function send(Request $request): Response
    {
        if (!$this->authorized($request)) {
            return Response::error(401, 'unauthorized', 'missing or invalid bearer token', headers: [
                'WWW-Authenticate' => 'Bearer realm="smtp-mailer-api"',
            ]);
        }
        if (strlen($request->body) > self::MAX_BODY_BYTES) {
            return Response::error(413, 'payload_too_large', 'body is larger than ' . self::MAX_BODY_BYTES . ' bytes');
        }
        if (!str_starts_with(strtolower($request->header('Content-Type')), 'application/json')) {
            return Response::error(415, 'unsupported_media_type', 'content type must be application/json');
        }

        try {
            $payload = json_decode($request->body, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return Response::error(400, 'invalid_json', 'body is not valid json');
        }
        if (!is_array($payload) || array_is_list($payload)) {
            return Response::error(400, 'invalid_json', 'body must be a json object');
        }

        try {
            $id = ($this->sendMessage)($this->message($payload));
        } catch (ValidationException $e) {
            return Response::error(422, 'validation_failed', 'the message is invalid', $e->errors());
        } catch (RecipientNotAllowedException $e) {
            return Response::error(403, 'recipient_not_allowed', $e->getMessage());
        } catch (DeliveryException $e) {
            ($this->log)($e->getMessage());

            return Response::error(502, 'delivery_failed', 'the mail server did not accept the message');
        }

        return Response::json(202, ['status' => 'sent', 'message_id' => $id]);
    }

    private function authorized(Request $request): bool
    {
        $header = $request->header('Authorization');

        return str_starts_with($header, 'Bearer ')
            && hash_equals($this->settings->apiToken, substr($header, strlen('Bearer ')));
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @throws ValidationException
     */
    private function message(array $payload): Message
    {
        $errors = [];
        $to = $payload['to'] ?? null;
        $recipients = [];
        foreach (is_string($to) ? [$to] : (is_array($to) ? $to : [null]) as $index => $address) {
            try {
                $recipients[] = EmailAddress::parse(is_string($address) ? $address : '', "to[{$index}]");
            } catch (ValidationException $e) {
                $errors = [...$errors, ...$e->errors()];
            }
        }

        $replyTo = null;
        if (isset($payload['reply_to'])) {
            try {
                $replyTo = EmailAddress::parse(is_string($payload['reply_to']) ? $payload['reply_to'] : '', 'reply_to');
            } catch (ValidationException $e) {
                $errors = [...$errors, ...$e->errors()];
            }
        }

        foreach (['subject', 'text'] as $field) {
            if (!is_string($payload[$field] ?? null)) {
                $errors[$field] = 'must be a string';
            }
        }
        $html = $payload['html'] ?? null;
        if ($html !== null && !is_string($html)) {
            $errors['html'] = 'must be a string';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return Message::create(
            $recipients,
            is_string($payload['subject']) ? $payload['subject'] : '',
            is_string($payload['text']) ? $payload['text'] : '',
            is_string($html) ? $html : null,
            $replyTo,
            $this->settings->maxRecipients,
        );
    }

    /**
     * @return array<string, string>
     */
    private function cors(Request $request): array
    {
        $origin = $request->header('Origin');
        if ($origin === '' || !in_array($origin, $this->settings->allowedOrigins, true)) {
            return [];
        }

        return [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type',
            'Access-Control-Max-Age' => '600',
            'Vary' => 'Origin',
        ];
    }
}
