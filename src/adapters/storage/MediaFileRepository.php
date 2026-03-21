<?php

declare(strict_types=1);

namespace Glyph\adapters\storage;

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\domain\media\MediaRecord;

final class MediaFileRepository
{
    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly string $mediaPath,
    ) {
    }

    public function save(MediaRecord $media): void
    {
        $json = json_encode($media->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($json) || $json === '') {
            throw new \RuntimeException('Failed to encode media record.');
        }

        $this->filesystem->writeFile($this->metadataPath($media->id), $json . PHP_EOL);
    }

    public function findById(string $id): ?MediaRecord
    {
        $safeId = trim($id);

        if ($safeId === '') {
            return null;
        }

        $path = $this->metadataPath($safeId);

        if (!$this->filesystem->isFile($path)) {
            return null;
        }

        return $this->readRecord($path);
    }

    public function delete(MediaRecord $media): void
    {
        $this->filesystem->deleteFile($this->metadataPath($media->id));
    }

    /** @return list<MediaRecord> */
    public function findAll(): array
    {
        $files = glob($this->mediaPath . '/*.json');

        if ($files === false) {
            throw new \RuntimeException('Failed to read media records.');
        }

        $records = [];

        foreach ($files as $file) {
            if (!is_string($file)) {
                continue;
            }

            $records[] = $this->readRecord($file);
        }

        usort(
            $records,
            static fn (MediaRecord $left, MediaRecord $right): int => strcmp($right->createdAt, $left->createdAt)
        );

        return $records;
    }

    private function metadataPath(string $id): string
    {
        return $this->mediaPath . '/' . $id . '.json';
    }

    private function readRecord(string $path): MediaRecord
    {
        $contents = $this->filesystem->readFile($path);
        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException(sprintf('Invalid media metadata JSON: %s', $path));
        }

        /** @var array<string,mixed> $decoded */
        return MediaRecord::fromArray($decoded);
    }
}
