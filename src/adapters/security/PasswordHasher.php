<?php

declare(strict_types=1);

namespace Glyph\adapters\security;

final class PasswordHasher
{
    public function hash(string $plainPassword): string
    {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

        if (!is_string($hash) || $hash === '') {
            throw new \RuntimeException('Failed to hash password.');
        }

        return $hash;
    }

    public function verify(string $plainPassword, string $passwordHash): bool
    {
        return password_verify($plainPassword, $passwordHash);
    }
}