<?php

declare(strict_types=1);

namespace Glyph\services\media;

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\filesystem\UploadedFileInspector;
use Glyph\adapters\security\SecretGenerator;
use Glyph\adapters\storage\MediaFileRepository;
use Glyph\domain\media\MediaRecord;

final class MediaService
{
    private const STORAGE_PREFIX = 'uploads/images/';

    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly UploadedFileInspector $uploadedFileInspector,
        private readonly MediaValidator $mediaValidator,
        private readonly MediaFileRepository $mediaRepository,
        private readonly SecretGenerator $secretGenerator,
        private readonly string $uploadsImagesPath,
    ) {
    }

    /** @return list<MediaRecord> */
    public function listAll(): array
    {
        return $this->mediaRepository->findAll();
    }

    /** @param array<string,mixed> $file */
    public function upload(array $file, string $uploadedBy): MediaUploadResult
    {
        try {
            $fileInfo = $this->uploadedFileInspector->inspect($file);
            $validationError = $this->mediaValidator->validate($fileInfo);

            if ($validationError !== null) {
                return MediaUploadResult::failure($validationError);
            }

            $createdAt = gmdate('c');
            $year = gmdate('Y');
            $month = gmdate('m');

            if (!is_string($createdAt) || !is_string($year) || !is_string($month) || $createdAt === '' || $year === '' || $month === '') {
                throw new \RuntimeException('Failed to determine media timestamp.');
            }

            $id = $this->secretGenerator->generateId();
            $targetDirectory = $this->uploadsImagesPath . '/' . $year . '/' . $month;
            $targetFilename = $id . '.' . $fileInfo['extension'];
            $targetPath = $targetDirectory . '/' . $targetFilename;

            $this->filesystem->ensureDirectoryExists($targetDirectory);
            $this->filesystem->moveUploadedFile($fileInfo['temporary_path'], $targetPath);

            $publicPath = '/uploads/images/' . $year . '/' . $month . '/' . $targetFilename;
            $storagePath = self::STORAGE_PREFIX . $year . '/' . $month . '/' . $targetFilename;

            $media = new MediaRecord(
                id: $id,
                originalName: $fileInfo['original_name'],
                storagePath: $storagePath,
                publicPath: $publicPath,
                mimeType: $fileInfo['mime_type'],
                sizeBytes: $fileInfo['size_bytes'],
                width: $fileInfo['width'],
                height: $fileInfo['height'],
                uploadedBy: $uploadedBy,
                createdAt: $createdAt,
            );

            $this->mediaRepository->save($media);

            return MediaUploadResult::success($media);
        } catch (\Throwable $throwable) {
            return MediaUploadResult::failure($throwable->getMessage());
        }
    }

    public function deleteById(string $id): bool
    {
        $media = $this->mediaRepository->findById($id);

        if ($media === null) {
            return false;
        }

        $this->filesystem->deleteFile($this->absoluteStoragePath($media));
        $this->mediaRepository->delete($media);

        return true;
    }

    private function absoluteStoragePath(MediaRecord $media): string
    {
        $storagePath = ltrim($media->storagePath, '/');

        if (!str_starts_with($storagePath, self::STORAGE_PREFIX) || str_contains($storagePath, '..')) {
            throw new \RuntimeException('Media storage path is invalid.');
        }

        $relativePath = substr($storagePath, strlen(self::STORAGE_PREFIX));

        if (!is_string($relativePath) || $relativePath === '') {
            throw new \RuntimeException('Media storage path is invalid.');
        }

        return rtrim($this->uploadsImagesPath, '/') . '/' . ltrim($relativePath, '/');
    }
}
