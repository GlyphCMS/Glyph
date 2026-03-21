<?php

declare(strict_types=1);

namespace Glyph\adapters\http;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly int $statusCode = 200,
        private readonly array $headers = [],
        private readonly string $body = '',
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public static function html(string $body, int $statusCode = 200, array $headers = []): self
    {
        return new self(
            $statusCode,
            ['Content-Type' => 'text/html; charset=UTF-8'] + $headers,
            $body,
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public static function json(array $payload, int $statusCode = 200, array $headers = []): self
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if (!is_string($encoded)) {
            throw new \RuntimeException('Failed to encode JSON response.');
        }

        return new self(
            $statusCode,
            ['Content-Type' => 'application/json; charset=UTF-8'] + $headers,
            $encoded,
        );
    }

    /**
     * @param array<string, string> $headers
     */
    public static function download(string $filename, string $contents, array $headers = []): self
    {
        return new self(
            200,
            [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
                'Content-Length' => (string) strlen($contents),
            ] + $headers,
            $contents,
        );
    }

    public static function redirect(string $location, int $statusCode = 302): self
    {
        return new self($statusCode, ['Location' => $location], '');
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        header_remove('X-Powered-By');

        foreach ($this->defaultHeaders() + $this->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }

        echo $this->body;
    }

    /**
     * @return array<string, string>
     */
    private function defaultHeaders(): array
    {
        return [
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
        ];
    }
}

