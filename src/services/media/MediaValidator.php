<?php

declare(strict_types=1);

namespace Glyph\services\media;

final class MediaValidator
{
    /**
     * @param list<string> $allowedExtensions
     * @param list<string> $allowedMimeTypes
     */
    public function __construct(
        private readonly array $allowedExtensions,
        private readonly array $allowedMimeTypes,
        private readonly int $maxUploadBytes,
    ) {
    }

    /**
     * @param array{
     *   original_name:string,
     *   temporary_path:string,
     *   size_bytes:int,
     *   extension:string,
     *   mime_type:string,
     *   width:int,
     *   height:int
     * } $fileInfo
     */
    public function validate(array $fileInfo): ?string
    {
        if ($fileInfo['size_bytes'] > $this->maxUploadBytes) {
            return 'The uploaded file exceeds the maximum allowed size.';
        }

        if (!in_array($fileInfo['extension'], $this->allowedExtensions, true)) {
            return 'The uploaded file type is not allowed.';
        }

        if (!in_array($fileInfo['mime_type'], $this->allowedMimeTypes, true)) {
            return 'The uploaded file MIME type is not allowed.';
        }

        if ($fileInfo['width'] < 1 || $fileInfo['height'] < 1) {
            return 'The uploaded image dimensions are invalid.';
        }

        return null;
    }
}
