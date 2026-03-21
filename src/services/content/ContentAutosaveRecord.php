<?php

declare(strict_types=1);

namespace Glyph\services\content;

final class ContentAutosaveRecord
{
    public function __construct(
        public readonly string $scopeKey,
        public readonly ?string $savedAt,
        public readonly ContentInput $input,
    ) {
    }
}
