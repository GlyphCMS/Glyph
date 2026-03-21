<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\filesystem\UploadedFileInspector;
use Glyph\adapters\security\SecretGenerator;
use Glyph\adapters\storage\MediaFileRepository;
use Glyph\domain\media\MediaRecord;
use Glyph\services\media\MediaService;
use Glyph\services\media\MediaValidator;

$root = sys_get_temp_dir() . '/glyph-media-delete-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root . '/data/media');
    $filesystem->ensureDirectoryExists($root . '/uploads/images/2026/03');

    $repository = new MediaFileRepository($filesystem, $root . '/data/media');
    $record = new MediaRecord(
        id: 'media_1',
        originalName: 'hero.webp',
        storagePath: 'uploads/images/2026/03/media_1.webp',
        publicPath: '/uploads/images/2026/03/media_1.webp',
        mimeType: 'image/webp',
        sizeBytes: 123,
        width: 640,
        height: 480,
        uploadedBy: 'owner',
        createdAt: '2026-03-09T00:00:00Z',
    );

    $repository->save($record);
    file_put_contents($root . '/uploads/images/2026/03/media_1.webp', 'image-bytes');

    $service = new MediaService(
        filesystem: $filesystem,
        uploadedFileInspector: new UploadedFileInspector(),
        mediaValidator: new MediaValidator(['webp'], ['image/webp'], 2048),
        mediaRepository: $repository,
        secretGenerator: new SecretGenerator(),
        uploadsImagesPath: $root . '/uploads/images',
    );

    if (!$service->deleteById('media_1')) {
        return false;
    }

    if (is_file($root . '/uploads/images/2026/03/media_1.webp')) {
        return false;
    }

    if (is_file($root . '/data/media/media_1.json')) {
        return false;
    }

    return $service->deleteById('media_1') === false;
} finally {
    if (is_dir($root)) {
        $filesystem->deleteDirectoryRecursively($root);
    }
}
