<?php

declare(strict_types=1);

namespace Glyph\services\install;

final class InstallationResult
{
    public function __construct(
        private readonly bool $isSuccessful,
        private readonly ?string $errorMessage = null,
    ) {
    }

    public static function success(): self
    {
        return new self(true, null);
    }

    public static function failure(string $errorMessage): self
    {
        return new self(false, $errorMessage);
    }

    public function isSuccessful(): bool
    {
        return $this->isSuccessful;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }
}