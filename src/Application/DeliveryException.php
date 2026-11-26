<?php

declare(strict_types=1);

namespace SmtpMailer\Application;

use RuntimeException;

/** The outgoing server could not accept the message. */
final class DeliveryException extends RuntimeException
{
}
