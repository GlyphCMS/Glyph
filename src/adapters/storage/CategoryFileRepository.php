<?php

declare(strict_types=1);

namespace Glyph\adapters\storage;

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\domain\categories\CategoryRecord;

final class CategoryFileRepository
{
    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly string $filePath,
    ) {
    }

    /**
     * @return list<CategoryRecord>
     */
    public function findAll(): array
    {
        if (!$this->filesystem->isFile($this->filePath)) {
            return [];
        }

        $decoded = json_decode($this->filesystem->readFile($this->filePath), true);

        if (!is_array($decoded)) {
            throw new \RuntimeException(sprintf('Invalid categories file: %s', $this->filePath));
        }

        $categories = [];

        foreach ($decoded as $row) {
            if (!is_array($row)) {
                throw new \RuntimeException('Invalid category record.');
            }

            $categories[] = CategoryRecord::fromArray($row);
        }

        return $categories;
    }

    /**
     * @param list<CategoryRecord> $categories
     */
    public function saveAll(array $categories): void
    {
        $payload = array_map(
            static fn (CategoryRecord $category): array => $category->toArray(),
            $categories,
        );

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($encoded)) {
            throw new \RuntimeException('Failed to encode categories.');
        }

        $this->filesystem->writeFile($this->filePath, $encoded . PHP_EOL);
    }
}
