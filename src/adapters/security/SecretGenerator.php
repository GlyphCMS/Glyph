<?php

declare(strict_types=1);

namespace Glyph\adapters\security;

final class SecretGenerator
{
    public function generateHex(int $bytes = 32): string
    {
        if ($bytes < 16) {
            throw new \InvalidArgumentException('Secret byte length must be at least 16.');
        }

        return bin2hex(random_bytes($bytes));
    }

    public function generateId(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }
}