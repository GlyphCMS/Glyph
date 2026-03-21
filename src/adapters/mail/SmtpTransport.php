<?php

declare(strict_types=1);

namespace Glyph\adapters\mail;

final class SmtpTransport implements EmailTransport
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $encryption,
        private readonly string $username,
        private readonly string $password,
        private readonly int $timeoutSeconds = 15,
    ) {
    }

    public function send(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $subject,
        string $htmlBody,
        string $textBody,
    ): void {
        $socket = $this->openSocket();

        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO localhost', [250]);

            if ($this->encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);

                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('Failed to enable TLS for SMTP connection.');
                }

                $this->command($socket, 'EHLO localhost', [250]);
            }

            if ($this->username !== '' || $this->password !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($this->username), [334]);
                $this->command($socket, base64_encode($this->password), [235]);
            }

            $this->command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);

            $data = $this->buildMessage($fromEmail, $fromName, $toEmail, $subject, $htmlBody, $textBody);
            $this->write($socket, $data . "\r\n.\r\n");
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    /**
     * @return resource
     */
    private function openSocket()
    {
        $transportHost = $this->encryption === 'ssl'
            ? 'ssl://' . $this->host
            : $this->host;

        $socket = @fsockopen($transportHost, $this->port, $errorNumber, $errorMessage, $this->timeoutSeconds);

        if (!is_resource($socket)) {
            throw new \RuntimeException(sprintf('Failed to connect to SMTP server: %s (%d)', $errorMessage, $errorNumber));
        }

        stream_set_timeout($socket, $this->timeoutSeconds);

        return $socket;
    }

    /**
     * @param resource $socket
     * @param list<int> $expectedCodes
     */
    private function command($socket, string $command, array $expectedCodes): void
    {
        $this->write($socket, $command . "\r\n");
        $this->expect($socket, $expectedCodes);
    }

    /**
     * @param resource $socket
     * @param list<int> $expectedCodes
     */
    private function expect($socket, array $expectedCodes): void
    {
        $response = '';
        $line = '';

        do {
            $line = fgets($socket, 1024);

            if (!is_string($line)) {
                throw new \RuntimeException('Unexpected end of SMTP response.');
            }

            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');

        $code = (int) substr($response, 0, 3);

        if (!in_array($code, $expectedCodes, true)) {
            throw new \RuntimeException(sprintf('Unexpected SMTP response: %s', trim($response)));
        }
    }

    /**
     * @param resource $socket
     */
    private function write($socket, string $data): void
    {
        $written = fwrite($socket, $data);

        if ($written === false || $written < strlen($data)) {
            throw new \RuntimeException('Failed to write to SMTP socket.');
        }
    }

    private function buildMessage(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $subject,
        string $htmlBody,
        string $textBody,
    ): string {
        $boundary = 'glyph-' . bin2hex(random_bytes(12));
        $safeFromName = str_replace(['\r', '\n'], '', $fromName);
        $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8');

        $headers = [];
        $headers[] = sprintf('From: %s <%s>', $safeFromName, $fromEmail);
        $headers[] = sprintf('To: <%s>', $toEmail);
        $headers[] = 'Subject: ' . $encodedSubject;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = sprintf('Content-Type: multipart/alternative; boundary="%s"', $boundary);

        $message = implode("\r\n", $headers) . "\r\n\r\n";
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $message .= $textBody . "\r\n";
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $message .= $htmlBody . "\r\n";
        $message .= '--' . $boundary . '--';

        return $message;
    }
}
