<?php

declare(strict_types=1);

namespace SmtpMailer\Application;

use SmtpMailer\Domain\EmailAddress;

/**
 * Restricts the domains the service may write to, so a leaked token cannot turn it into a spam
 * relay. A single "*" entry allows every domain and must be chosen explicitly.
 */
final readonly class RecipientPolicy
{
    private const string ANY = '*';

    /**
     * @param list<string> $allowedDomains lower-case domains, or ["*"]
     */
    public function __construct(private array $allowedDomains)
    {
    }

    public static function fromList(string $domains): self
    {
        $parsed = array_values(array_filter(
            array_map(static fn (string $domain): string => strtolower(trim($domain)), explode(',', $domains)),
            static fn (string $domain): bool => $domain !== '',
        ));

        return new self($parsed);
    }

    public function allows(EmailAddress $address): bool
    {
        return in_array(self::ANY, $this->allowedDomains, true)
            || in_array($address->domain, $this->allowedDomains, true);
    }
}
