<?php

declare(strict_types=1);

use Glyph\services\media\MediaValidator;

$validator = new MediaValidator(
    allowedExtensions: ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    allowedMimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    maxUploadBytes: 1024,
);

$valid = $validator->validate([
    'original_name' => 'test.webp',
    'temporary_path' => '/tmp/test',
    'size_bytes' => 512,
    'extension' => 'webp',
    'mime_type' => 'image/webp',
    'width' => 100,
    'height' => 100,
]);

if ($valid !== null) {
    return false;
}

$tooLarge = $validator->validate([
    'original_name' => 'test.webp',
    'temporary_path' => '/tmp/test',
    'size_bytes' => 2048,
    'extension' => 'webp',
    'mime_type' => 'image/webp',
    'width' => 100,
    'height' => 100,
]);

if ($tooLarge === null) {
    return false;
}

$badType = $validator->validate([
    'original_name' => 'test.svg',
    'temporary_path' => '/tmp/test',
    'size_bytes' => 512,
    'extension' => 'svg',
    'mime_type' => 'image/svg+xml',
    'width' => 100,
    'height' => 100,
]);

if ($badType === null) {
    return false;
}

return true;
