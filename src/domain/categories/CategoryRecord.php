<?php

declare(strict_types=1);

namespace Glyph\domain\categories;

final class CategoryRecord
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $description,
        public readonly ?string $parentId,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: self::requireString($data, 'id'),
            name: self::requireString($data, 'name'),
            slug: self::requireString($data, 'slug'),
            description: self::stringValue($data['description'] ?? ''),
            parentId: self::optionalString($data, 'parent_id'),
            createdAt: self::requireString($data, 'created_at'),
            updatedAt: self::requireString($data, 'updated_at'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'parent_id' => $this->parentId,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function withChanges(string $name, string $slug, string $description, ?string $parentId, string $updatedAt): self
    {
        return new self(
            id: $this->id,
            name: $name,
            slug: $slug,
            description: $description,
            parentId: $parentId,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function requireString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (!is_string($value) || trim($value) === '') {
            throw new \RuntimeException(sprintf('Invalid category field: %s', $key));
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new \RuntimeException(sprintf('Invalid category field: %s', $key));
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function stringValue(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
