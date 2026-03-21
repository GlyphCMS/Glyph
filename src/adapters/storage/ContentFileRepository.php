<?php

declare(strict_types=1);

namespace Glyph\adapters\storage;

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\domain\content\ContentRecord;

final class ContentFileRepository
{
    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly string $postsPath,
        private readonly string $pagesPath,
    ) {
    }

    public function findById(string $type, string $id): ?ContentRecord
    {
        $directory = $this->directoryPath($type, $id);

        if (!$this->filesystem->isDirectory($directory)) {
            return null;
        }

        return $this->readFromDirectory($directory);
    }

    /**
     * @return list<ContentRecord>
     */
    public function findAll(): array
    {
        $records = [];

        foreach ([$this->postsPath, $this->pagesPath] as $basePath) {
            foreach ($this->filesystem->listDirectories($basePath) as $directory) {
                $records[] = $this->readFromDirectory($directory);
            }
        }

        usort(
            $records,
            static fn (ContentRecord $left, ContentRecord $right): int => strcmp($right->updatedAt, $left->updatedAt)
        );

        return $records;
    }

    /**
     * @return list<ContentRecord>
     */
    public function findByType(string $type): array
    {
        $records = [];

        foreach ($this->filesystem->listDirectories($this->basePath($type)) as $directory) {
            $records[] = $this->readFromDirectory($directory);
        }

        usort(
            $records,
            static fn (ContentRecord $left, ContentRecord $right): int => strcmp($right->updatedAt, $left->updatedAt)
        );

        return $records;
    }

    public function save(ContentRecord $content): void
    {
        $directory = $this->directoryPath($content->type, $content->id);
        $this->filesystem->ensureDirectoryExists($directory);

        $metaJson = json_encode($content->meta(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($metaJson) || $metaJson === '') {
            throw new \RuntimeException('Failed to encode content metadata.');
        }

        $this->filesystem->writeFile($directory . '/meta.json', $metaJson . PHP_EOL);
        $this->filesystem->writeFile($directory . '/body.html', $content->bodyHtml);
    }

    public function delete(ContentRecord $content): void
    {
        $this->filesystem->deleteDirectoryRecursively($this->directoryPath($content->type, $content->id));
    }

    private function readFromDirectory(string $directory): ContentRecord
    {
        $metaPath = $directory . '/meta.json';
        $bodyPath = $directory . '/body.html';

        $metaContents = $this->filesystem->readFile($metaPath);
        $metaDecoded = json_decode($metaContents, true);

        if (!is_array($metaDecoded)) {
            throw new \RuntimeException(sprintf('Invalid content metadata: %s', $metaPath));
        }

        $bodyHtml = $this->filesystem->readFile($bodyPath);

        /** @var array<string, mixed> $metaDecoded */
        return ContentRecord::fromStorage($metaDecoded, $bodyHtml);
    }

    private function directoryPath(string $type, string $id): string
    {
        return $this->basePath($type) . '/' . $id;
    }

    private function basePath(string $type): string
    {
        return match ($type) {
            'post' => $this->postsPath,
            'page' => $this->pagesPath,
            default => throw new \InvalidArgumentException(sprintf('Unsupported content type: %s', $type)),
        };
    }
}