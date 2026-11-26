<?php

declare(strict_types=1);

use SmtpMailer\Application\RecipientPolicy;
use SmtpMailer\Application\SendMessage;
use SmtpMailer\Config\Settings;
use SmtpMailer\Http\Kernel;
use SmtpMailer\Http\Request;
use SmtpMailer\Http\Response;
use SmtpMailer\Infrastructure\PhpMailerTransport;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $settings = Settings::fromEnvironment(getenv());
} catch (InvalidArgumentException $e) {
    error_log($e->getMessage());
    Response::error(500, 'misconfigured', 'the service is not configured')->send();

    return;
}

$kernel = new Kernel(
    new SendMessage(new PhpMailerTransport($settings), RecipientPolicy::fromList($settings->allowedRecipientDomains)),
    $settings,
    static function (string $line): void {
        error_log($line);
    },
);
$kernel->handle(Request::fromGlobals(Kernel::MAX_BODY_BYTES))->send();
