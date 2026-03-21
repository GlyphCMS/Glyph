<?php

declare(strict_types=1);

namespace Glyph\services\mail;

use Glyph\adapters\mail\EmailTransport;
use Glyph\adapters\mail\PhpMailTransport;
use Glyph\adapters\mail\SmtpTransport;

final class MailManager
{
    /**
     * @param array<string, mixed> $mailConfig
     */
    public function __construct(
        private readonly array $mailConfig,
    ) {
    }

    public function send(
        string $toEmail,
        string $subject,
        string $htmlBody,
        string $textBody,
    ): void {
        $transport = $this->transport();
        $transport->send(
            fromEmail: $this->fromEmail(),
            fromName: $this->fromName(),
            toEmail: $toEmail,
            subject: $subject,
            htmlBody: $htmlBody,
            textBody: $textBody,
        );
    }

    private function transport(): EmailTransport
    {
        $transport = $this->mailConfig['transport'] ?? 'php_mail';

        if ($transport === 'smtp') {
            $smtp = $this->mailConfig['smtp'] ?? null;

            if (!is_array($smtp)) {
                throw new \RuntimeException('Invalid SMTP mail configuration.');
            }

            return new SmtpTransport(
                host: $this->stringFromArray($smtp, 'host'),
                port: $this->intFromArray($smtp, 'port'),
                encryption: $this->stringFromArray($smtp, 'encryption'),
                username: $this->stringFromArray($smtp, 'username'),
                password: $this->stringFromArray($smtp, 'password'),
                timeoutSeconds: $this->intFromArray($smtp, 'timeout_seconds'),
            );
        }

        return new PhpMailTransport();
    }

    private function fromName(): string
    {
        $fromName = $this->mailConfig['from_name'] ?? '';

        if (!is_string($fromName) || $fromName === '') {
            throw new \RuntimeException('Invalid mail from_name configuration.');
        }

        return $fromName;
    }

    private function fromEmail(): string
    {
        $fromEmail = $this->mailConfig['from_email'] ?? '';

        if (!is_string($fromEmail) || $fromEmail === '') {
            throw new \RuntimeException('Invalid mail from_email configuration.');
        }

        return $fromEmail;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringFromArray(array $values, string $key): string
    {
        $value = $values[$key] ?? '';

        if (!is_string($value)) {
            throw new \RuntimeException(sprintf('Invalid mail configuration: %s.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function intFromArray(array $values, string $key): int
    {
        $value = $values[$key] ?? null;

        if (!is_int($value)) {
            throw new \RuntimeException(sprintf('Invalid mail configuration: %s.', $key));
        }

        return $value;
    }
}
