<?php

declare(strict_types=1);

namespace Glyph\adapters\http;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, string> $server
     * @param array<string, string> $cookies
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $post,
        private readonly array $server,
        private readonly array $cookies,
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])
            ? strtoupper($_SERVER['REQUEST_METHOD'])
            : 'GET';

        $requestUri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
            ? $_SERVER['REQUEST_URI']
            : '/';

        $path = parse_url($requestUri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        $normalizedPath = '/' . trim($path, '/');
        if ($normalizedPath === '//') {
            $normalizedPath = '/';
        }

        /** @var array<string, mixed> $query */
        $query = $_GET;
        /** @var array<string, mixed> $post */
        $post = $_POST;

        $server = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($value)) {
                $server[$key] = $value;
            }
        }

        $cookies = [];
        foreach ($_COOKIE as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $cookies[$key] = $value;
            }
        }

        return new self($method, $normalizedPath, $query, $post, $server, $cookies);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return $this->query;
    }

    public function queryString(string $key): ?string
    {
        $value = $this->query[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public function queryTrimmedString(string $key): string
    {
        return trim((string) ($this->queryString($key) ?? ''));
    }

    public function queryFlag(string $key): bool
    {
        return $this->queryString($key) === '1';
    }

    /**
     * @return array<string, mixed>
     */
    public function post(): array
    {
        return $this->post;
    }

    public function postString(string $key): ?string
    {
        $value = $this->post[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public function postTrimmedString(string $key): string
    {
        return trim((string) ($this->postString($key) ?? ''));
    }

    public function server(string $key, ?string $default = null): ?string
    {
        return $this->server[$key] ?? $default;
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        return $this->cookies[$key] ?? $default;
    }

    public function clientIp(): string
    {
        $remoteAddress = $this->server('REMOTE_ADDR', '');

        if (!is_string($remoteAddress) || $remoteAddress === '') {
            return 'unknown';
        }

        return $remoteAddress;
    }
}
