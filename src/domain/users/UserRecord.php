<?php

declare(strict_types=1);

namespace Glyph\domain\users;

final class UserRecord
{
    /**
     * @param list<array{selector:string, token_hash:string, expires_at:string}> $rememberTokens
     * @param list<array{selector:string, token_hash:string, expires_at:string}> $passwordResetTokens
     */
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly string $role,
        public readonly bool $isActive,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?string $lastLoginAt,
        public readonly array $rememberTokens,
        public readonly array $passwordResetTokens,
        public readonly string $displayName = '',
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $id = self::requireString($data, 'id');
        $email = self::requireString($data, 'email');
        $passwordHash = self::requireString($data, 'password_hash');
        $role = self::requireString($data, 'role');
        $isActive = self::requireBool($data, 'is_active');
        $createdAt = self::requireString($data, 'created_at');
        $updatedAt = self::requireString($data, 'updated_at');
        $lastLoginAt = self::optionalString($data, 'last_login_at');
        $rememberTokens = self::parseTokenRecords($data['remember_tokens'] ?? []);
        $passwordResetTokens = self::parseTokenRecords($data['password_reset_tokens'] ?? []);
        $displayName = isset($data['display_name']) && is_string($data['display_name']) ? trim($data['display_name']) : '';

        return new self(
            id: $id,
            email: $email,
            passwordHash: $passwordHash,
            role: $role,
            isActive: $isActive,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            lastLoginAt: $lastLoginAt,
            rememberTokens: $rememberTokens,
            passwordResetTokens: $passwordResetTokens,
            displayName: $displayName,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'display_name' => $this->displayName,
            'password_hash' => $this->passwordHash,
            'role' => $this->role,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'last_login_at' => $this->lastLoginAt,
            'remember_tokens' => $this->rememberTokens,
            'password_reset_tokens' => $this->passwordResetTokens,
        ];
    }

    public function displayNameOrFallback(): string
    {
        if ($this->displayName !== '') {
            return $this->displayName;
        }

        $local = strstr($this->email, '@', true);

        return is_string($local) && $local !== '' ? $local : $this->email;
    }

    /**
     * @param list<array{selector:string, token_hash:string, expires_at:string}> $rememberTokens
     */
    public function withRememberTokens(array $rememberTokens, string $updatedAt): self
    {
        return new self($this->id, $this->email, $this->passwordHash, $this->role, $this->isActive, $this->createdAt, $updatedAt, $this->lastLoginAt, $rememberTokens, $this->passwordResetTokens, $this->displayName);
    }

    /**
     * @param list<array{selector:string, token_hash:string, expires_at:string}> $passwordResetTokens
     */
    public function withPasswordResetTokens(array $passwordResetTokens, string $updatedAt): self
    {
        return new self($this->id, $this->email, $this->passwordHash, $this->role, $this->isActive, $this->createdAt, $updatedAt, $this->lastLoginAt, $this->rememberTokens, $passwordResetTokens, $this->displayName);
    }

    public function withLastLoginAt(string $lastLoginAt): self
    {
        return new self($this->id, $this->email, $this->passwordHash, $this->role, $this->isActive, $this->createdAt, $lastLoginAt, $lastLoginAt, $this->rememberTokens, $this->passwordResetTokens, $this->displayName);
    }

    public function withProfile(string $email, string $role, bool $isActive, string $updatedAt, ?string $displayName = null): self
    {
        return new self($this->id, $email, $this->passwordHash, $role, $isActive, $this->createdAt, $updatedAt, $this->lastLoginAt, $this->rememberTokens, $this->passwordResetTokens, $displayName ?? $this->displayName);
    }

    public function withPasswordHash(string $passwordHash, string $updatedAt): self
    {
        return new self($this->id, $this->email, $passwordHash, $this->role, $this->isActive, $this->createdAt, $updatedAt, $this->lastLoginAt, [], [], $this->displayName);
    }

    /** @param array<string, mixed> $data */
    private static function requireString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \RuntimeException(sprintf('Invalid user record field: %s', $key));
        }
        return $value;
    }

    /** @param array<string, mixed> $data */
    private static function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || $value === '') {
            throw new \RuntimeException(sprintf('Invalid optional user record field: %s', $key));
        }
        return $value;
    }

    /** @param array<string, mixed> $data */
    private static function requireBool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;
        if (!is_bool($value)) {
            throw new \RuntimeException(sprintf('Invalid user record field: %s', $key));
        }
        return $value;
    }

    /**
     * @param mixed $value
     * @return list<array{selector:string, token_hash:string, expires_at:string}>
     */
    private static function parseTokenRecords(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $tokens = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                throw new \RuntimeException('Invalid token record.');
            }

            $selector = $item['selector'] ?? null;
            $tokenHash = $item['token_hash'] ?? null;
            $expiresAt = $item['expires_at'] ?? null;

            if (!is_string($selector) || $selector === '' || !is_string($tokenHash) || $tokenHash === '' || !is_string($expiresAt) || $expiresAt === '') {
                throw new \RuntimeException('Invalid token fields.');
            }

            $tokens[] = ['selector' => $selector, 'token_hash' => $tokenHash, 'expires_at' => $expiresAt];
        }

        return $tokens;
    }
}
