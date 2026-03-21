<?php

declare(strict_types=1);

namespace Glyph\services\content;

final class ContentValidationResult
{
    /**
     * @param array<string, string> $fieldErrors
     */
    public function __construct(
        private readonly array $fieldErrors,
    ) {
    }

    public function isValid(): bool
    {
        return $this->fieldErrors === [];
    }

    /**
     * @return array<string, string>
     */
    public function fieldErrors(): array
    {
        return $this->fieldErrors;
    }

    public function firstError(string $field): ?string
    {
        return $this->fieldErrors[$field] ?? null;
    }
}