<?php

declare(strict_types=1);

namespace Glyph\services\users;

final class UserFormValidationResult
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(
        private readonly array $errors,
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function firstError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }
}
