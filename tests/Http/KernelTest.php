<?php

declare(strict_types=1);

namespace SmtpMailer\Tests\Http;

use PHPUnit\Framework\TestCase;
use SmtpMailer\Application\RecipientPolicy;
use SmtpMailer\Application\SendMessage;
use SmtpMailer\Config\Settings;
use SmtpMailer\Http\Kernel;
use SmtpMailer\Http\Request;
use SmtpMailer\Http\Response;
use SmtpMailer\Tests\Support\FakeMailer;

final class KernelTest extends TestCase
{
    private const string TOKEN = 'test-token-with-at-least-32-characters';

    private FakeMailer $mailer;

    /** @var list<string> */
    private array $log = [];

    private function kernel(?FakeMailer $mailer = null): Kernel
    {
        $this->mailer = $mailer ?? new FakeMailer();
        $settings = Settings::fromEnvironment([
            'SMTP_HOST' => 'smtp.example.com',
            'MAIL_FROM_ADDRESS' => 'no-reply@example.com',
            'API_TOKEN' => self::TOKEN,
            'ALLOWED_RECIPIENT_DOMAINS' => 'example.com',
            'MAX_RECIPIENTS' => '2',
            'ALLOWED_ORIGINS' => 'https://kevin.example',
        ]);

        return new Kernel(
            new SendMessage($this->mailer, RecipientPolicy::fromList($settings->allowedRecipientDomains)),
            $settings,
            function (string $line): void {
                $this->log[] = $line;
            },
        );
    }

    /**
     * @param array<string, mixed>|string $body
     * @param array<string, string> $headers
     */
    private function post(array|string $body, array $headers = [], ?FakeMailer $mailer = null): Response
    {
        return $this->kernel($mailer)->handle(new Request(
            'POST',
            '/messages',
            [...['authorization' => 'Bearer ' . self::TOKEN, 'content-type' => 'application/json; charset=utf-8'], ...$headers],
            is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR),
        ));
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function decode(Response $response): array
    {
        $decoded = json_decode($response->body, true, 16, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function errorOf(Response $response): array
    {
        $error = self::decode($response)['error'] ?? null;
        self::assertIsArray($error);

        return $error;
    }

    /**
     * @return array<string, mixed>
     */
    private static function validPayload(): array
    {
        return [
            'to' => ['cliente@example.com'],
            'subject' => 'Registro completo',
            'text' => 'Hola, tu registro está completo.',
            'html' => '<p>Hola, tu registro está completo.</p>',
            'reply_to' => 'soporte@example.com',
        ];
    }

    public function testAcceptsAValidMessage(): void
    {
        $response = $this->post(self::validPayload());

        self::assertSame(202, $response->status);
        self::assertSame(['status' => 'sent', 'message_id' => '<fake-1@smtp-mailer-api.test>'], self::decode($response));
        self::assertSame('Registro completo', $this->mailer->sent[0]->subject);
        self::assertSame('no-store', $response->headers['Cache-Control']);
        self::assertSame('nosniff', $response->headers['X-Content-Type-Options']);
    }

    public function testASingleRecipientMayBeAString(): void
    {
        $response = $this->post([...self::validPayload(), 'to' => 'cliente@example.com']);

        self::assertSame(202, $response->status);
    }

    public function testRejectsMissingOrWrongTokens(): void
    {
        foreach (['', 'Bearer wrong', 'Basic ' . self::TOKEN, 'Bearer ' . self::TOKEN . 'x'] as $header) {
            $response = $this->post(self::validPayload(), ['authorization' => $header]);

            self::assertSame(401, $response->status, $header);
            self::assertSame('Bearer realm="smtp-mailer-api"', $response->headers['WWW-Authenticate']);
        }
        self::assertSame([], $this->mailer->sent);
    }

    public function testRejectsOversizedBodies(): void
    {
        $response = $this->post(str_repeat(' ', Kernel::MAX_BODY_BYTES + 1));

        self::assertSame(413, $response->status);
    }

    public function testRequiresJson(): void
    {
        $response = $this->post(self::validPayload(), ['content-type' => 'application/x-www-form-urlencoded']);

        self::assertSame(415, $response->status);
    }

    public function testRejectsMalformedJson(): void
    {
        self::assertSame('invalid_json', self::errorOf($this->post('{"to":'))['code'] ?? null);
        self::assertSame(400, $this->post('["a@example.com"]')->status);
        self::assertSame(400, $this->post('"text"')->status);
    }

    public function testReportsEveryInvalidField(): void
    {
        $response = $this->post(['to' => ['bad', 'cliente@example.com'], 'subject' => 3, 'html' => [], 'reply_to' => 'x']);

        self::assertSame(422, $response->status);
        self::assertSame([
            'code' => 'validation_failed',
            'message' => 'the message is invalid',
            'fields' => [
                'to[0]' => 'is not a valid email address',
                'reply_to' => 'is not a valid email address',
                'subject' => 'must be a string',
                'text' => 'must be a string',
                'html' => 'must be a string',
            ],
        ], self::errorOf($response));
    }

    public function testDomainRulesAreReportedToo(): void
    {
        $response = $this->post([...self::validPayload(), 'to' => ['a@example.com', 'b@example.com', 'c@example.com']]);

        self::assertSame(422, $response->status);
        self::assertSame(['to' => 'accepts at most 2 recipients'], self::errorOf($response)['fields'] ?? null);
    }

    public function testAMissingRecipientListIsInvalid(): void
    {
        $payload = self::validPayload();
        unset($payload['to']);

        self::assertSame(['to[0]' => 'is not a valid email address'], self::errorOf($this->post($payload))['fields'] ?? null);
    }

    public function testRefusesRecipientsOutsideTheAllowlist(): void
    {
        $response = $this->post([...self::validPayload(), 'to' => ['victim@gmail.com']]);

        self::assertSame(403, $response->status);
        self::assertSame('recipient_not_allowed', self::errorOf($response)['code'] ?? null);
    }

    public function testHidesSmtpFailuresFromClientsButLogsThem(): void
    {
        $response = $this->post(self::validPayload(), mailer: new FakeMailer('smtp delivery: 535 authentication failed'));

        self::assertSame(502, $response->status);
        self::assertStringNotContainsString('535', $response->body);
        self::assertSame(['smtp delivery: 535 authentication failed'], $this->log);
    }

    public function testExposesAHealthCheck(): void
    {
        $response = $this->kernel()->handle(new Request('GET', '/health'));

        self::assertSame(200, $response->status);
        self::assertSame(['status' => 'ok'], self::decode($response));
    }

    public function testUnknownRoutesAndMethods(): void
    {
        $kernel = $this->kernel();

        self::assertSame(404, $kernel->handle(new Request('GET', '/'))->status);
        $wrongMethod = $kernel->handle(new Request('GET', '/messages'));
        self::assertSame(405, $wrongMethod->status);
        self::assertSame('POST', $wrongMethod->headers['Allow']);
        self::assertSame('GET, HEAD', $kernel->handle(new Request('DELETE', '/health'))->headers['Allow']);
        self::assertSame(200, $kernel->handle(new Request('HEAD', '/health'))->status);
    }

    public function testCorsOnlyForConfiguredOrigins(): void
    {
        $kernel = $this->kernel();

        $allowed = $kernel->handle(new Request('OPTIONS', '/messages', ['origin' => 'https://kevin.example']));
        self::assertSame(204, $allowed->status);
        self::assertSame('https://kevin.example', $allowed->headers['Access-Control-Allow-Origin']);
        self::assertSame('Authorization, Content-Type', $allowed->headers['Access-Control-Allow-Headers']);

        $foreign = $kernel->handle(new Request('OPTIONS', '/messages', ['origin' => 'https://evil.example']));
        self::assertArrayNotHasKey('Access-Control-Allow-Origin', $foreign->headers);
    }

    public function testRequestHeadersAreCaseInsensitive(): void
    {
        self::assertSame('application/json', (new Request('GET', '/', ['content-type' => 'application/json']))->header('Content-Type'));
        self::assertSame('', (new Request('GET', '/'))->header('Origin'));
    }
}
