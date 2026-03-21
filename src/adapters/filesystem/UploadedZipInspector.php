<?php

declare(strict_types=1);

namespace Glyph\adapters\filesystem;

final class UploadedZipInspector
{
    /**
     * @param array<string, mixed> $file
     * @return array{
     *   original_name:string,
     *   temporary_path:string,
     *   size_bytes:int,
     *   extension:string,
     *   mime_type:string
     * }
     */
    public function inspect(array $file, string $uploadLabel = 'package'): array
    {
        $error = $file['error'] ?? null;
        if (!is_int($error) || $error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('The upload did not complete successfully.');
        }

        $originalName = $file['name'] ?? null;
        $temporaryPath = $file['tmp_name'] ?? null;
        $sizeBytes = $file['size'] ?? null;

        if (!is_string($originalName) || $originalName === '' || !is_string($temporaryPath) || $temporaryPath === '' || !is_int($sizeBytes) || $sizeBytes < 1) {
            throw new \RuntimeException('Invalid uploaded file data.');
        }

        if (!is_uploaded_file($temporaryPath) && PHP_SAPI !== 'cli') {
            throw new \RuntimeException('The uploaded file could not be verified.');
        }

        $extension = mb_strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== 'zip') {
            throw new \RuntimeException(sprintf('The uploaded %s must be a ZIP file.', $uploadLabel));
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($temporaryPath);

        if (!is_string($mimeType) || $mimeType === '') {
            throw new \RuntimeException('The uploaded ZIP MIME type could not be determined.');
        }

        return [
            'original_name' => $originalName,
            'temporary_path' => $temporaryPath,
            'size_bytes' => $sizeBytes,
            'extension' => $extension,
            'mime_type' => $mimeType,
        ];
    }
}
