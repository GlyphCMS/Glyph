<?php

declare(strict_types=1);

namespace Glyph\domain\navigation;

final class NavigationItem
{
    /**
     * @param list<NavigationItem> $children
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $url,
        public readonly string $target,
        public readonly string $parentId,
        public readonly int $sortOrder,
        public readonly ?string $contentId,
        public readonly array $children = [],
    ) {
    }
}
