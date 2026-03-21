<?php

declare(strict_types=1);

namespace Glyph\adapters\mail;

interface EmailTransport
{
    public function send(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $subject,
        string $htmlBody,
        string $textBody,
    ): void;
}
