<?php

declare(strict_types=1);

namespace SmtpMailer\Domain;

/**
 * An outgoing message as requested by a client. The sender is not part of it: the service always
 * sends from its configured mailbox, so a client can never impersonate another address.
 */
final readonly class Message
{
    public const int MAX_SUBJECT_LENGTH = 200;
    public const int MAX_BODY_BYTES = 100_000;

    /**
     * @param non-empty-list<EmailAddress> $to
     */
    private function __construct(
        public array $to,
        public string $subject,
        public string $text,
        public ?string $html,
        public ?EmailAddress $replyTo,
    ) {
    }

    /**
     * @param list<EmailAddress> $to
     *
     * @throws ValidationException
     */
    public static function create(
        array $to,
        string $subject,
        string $text,
        ?string $html = null,
        ?EmailAddress $replyTo = null,
        int $maxRecipients = 10,
    ): self {
        $errors = [];
        if ($to === []) {
            $errors['to'] = 'needs at least one recipient';
        } elseif (count($to) > $maxRecipients) {
            $errors['to'] = "accepts at most {$maxRecipients} recipients";
        }

        $subject = trim($subject);
        if ($subject === '') {
            $errors['subject'] = 'is required';
        } elseif (preg_match('/[\r\n]/', $subject) === 1) {
            $errors['subject'] = 'cannot contain line breaks';
        } elseif (mb_strlen($subject) > self::MAX_SUBJECT_LENGTH) {
            $errors['subject'] = 'is longer than ' . self::MAX_SUBJECT_LENGTH . ' characters';
        }

        if (trim($text) === '') {
            $errors['text'] = 'is required, also as the plain text alternative of html';
        } elseif (strlen($text) > self::MAX_BODY_BYTES) {
            $errors['text'] = 'is larger than ' . self::MAX_BODY_BYTES . ' bytes';
        }

        if ($html !== null && strlen($html) > self::MAX_BODY_BYTES) {
            $errors['html'] = 'is larger than ' . self::MAX_BODY_BYTES . ' bytes';
        }

        foreach (['subject' => $subject, 'text' => $text, 'html' => $html ?? ''] as $field => $value) {
            if (!isset($errors[$field]) && !mb_check_encoding($value, 'UTF-8')) {
                $errors[$field] = 'is not valid UTF-8';
            }
        }

        if ($errors !== [] || $to === []) {
            throw new ValidationException($errors);
        }

        return new self($to, $subject, $text, $html, $replyTo);
    }
}
