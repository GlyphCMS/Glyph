<?php

declare(strict_types=1);

namespace Glyph\services\categories;

final class CategoryInput
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly string $description,
        public readonly ?string $parentId,
    ) {
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function fromPost(array $post): self
    {
        return new self(
            name: self::stringValue($post['name'] ?? ''),
            slug: self::stringValue($post['slug'] ?? ''),
            description: self::stringValue($post['description'] ?? ''),
            parentId: self::nullableStringValue($post['parent_id'] ?? null),
        );
    }

    public function withSlug(string $slug): self
    {
        return new self(
            name: $this->name,
            slug: $slug,
            description: $this->description,
            parentId: $this->parentId,
        );
    }

    private static function stringValue(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private static function nullableStringValue(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
