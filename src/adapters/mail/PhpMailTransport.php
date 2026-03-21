<?php

declare(strict_types=1);

namespace Glyph\adapters\mail;

final class PhpMailTransport implements EmailTransport
{
    public function send(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $subject,
        string $htmlBody,
        string $textBody,
    ): void {
        $boundary = 'glyph-' . bin2hex(random_bytes(12));
        $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8');
        $safeFromName = str_replace(['\r', '\n'], '', $fromName);
        $safeFromEmail = str_replace(['\r', '\n'], '', $fromEmail);

        $headers = [];
        $headers[] = sprintf('From: %s <%s>', $safeFromName, $safeFromEmail);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = sprintf('Content-Type: multipart/alternative; boundary="%s"', $boundary);

        $message = '';
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $message .= $textBody . "\r\n";
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $message .= $htmlBody . "\r\n";
        $message .= '--' . $boundary . "--\r\n";

        $sent = mail($toEmail, $encodedSubject, $message, implode("\r\n", $headers));

        if ($sent !== true) {
            throw new \RuntimeException('Failed to send email using PHP mail().');
        }
    }
}
