<?php

declare(strict_types=1);

namespace Glyph\adapters\session;

final class SessionManager
{
    private bool $isStarted = false;

    /**
     * @param array<string, mixed> $authConfig
     */
    public function __construct(
        private readonly array $authConfig,
        private readonly string $sessionSavePath,
    ) {
    }

    public function start(): void
    {
        if ($this->isStarted || session_status() === PHP_SESSION_ACTIVE) {
            $this->isStarted = true;
            return;
        }

        if (!is_dir($this->sessionSavePath) && !mkdir($this->sessionSavePath, 0755, true) && !is_dir($this->sessionSavePath)) {
            throw new \RuntimeException(sprintf('Failed to create session path: %s', $this->sessionSavePath));
        }

        session_name($this->stringConfig('session_cookie_name'));
        session_save_path($this->sessionSavePath);
        session_set_cookie_params([
            'lifetime' => $this->intConfig('session_lifetime_seconds'),
            'path' => '/',
            'domain' => '',
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => $this->stringConfig('session_cookie_same_site'),
        ]);

        session_start([
            'use_strict_mode' => true,
            'cookie_httponly' => true,
            'cookie_secure' => $this->isHttps(),
            'cookie_samesite' => $this->stringConfig('session_cookie_same_site'),
        ]);

        $this->isStarted = true;
    }

    public function regenerate(): void
    {
        $this->start();

        if (!session_regenerate_id(true)) {
            throw new \RuntimeException('Failed to regenerate session id.');
        }
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function get(string $key): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? null;
    }

    public function has(string $key): bool
    {
        $this->start();
        return array_key_exists($key, $_SESSION);
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function destroy(): void
    {
        $this->start();

        $_SESSION = [];

        if (session_id() !== '') {
            session_destroy();
        }
    }

    private function intConfig(string $key): int
    {
        $value = $this->authConfig[$key] ?? null;

        if (!is_int($value)) {
            throw new \RuntimeException(sprintf('Invalid auth config integer: %s', $key));
        }

        return $value;
    }

    private function stringConfig(string $key): string
    {
        $value = $this->authConfig[$key] ?? null;

        if (!is_string($value) || $value === '') {
            throw new \RuntimeException(sprintf('Invalid auth config string: %s', $key));
        }

        return $value;
    }

    private function isHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? null;

        return is_string($https) && strtolower($https) !== 'off' && $https !== '';
    }
}