<?php

declare(strict_types=1);

namespace Glyph\services\system;

interface MigrationInterface
{
    public function id(): string;

    public function description(): string;

    public function apply(MigrationContext $context): void;
}
