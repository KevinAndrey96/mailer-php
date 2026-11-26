<?php

declare(strict_types=1);

namespace SmtpMailer\Application;

use DomainException;

/** A recipient is outside the configured allowlist. */
final class RecipientNotAllowedException extends DomainException
{
}
