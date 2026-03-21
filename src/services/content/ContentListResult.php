<?php

declare(strict_types=1);

namespace Glyph\services\content;

use Glyph\domain\content\ContentRecord;

final class ContentListResult
{
    /**
     * @param list<ContentRecord> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $currentPage,
        public readonly int $perPage,
        public readonly int $totalItems,
        public readonly int $totalPages,
    ) {
    }
}