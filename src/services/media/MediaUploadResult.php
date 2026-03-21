<?php

declare(strict_types=1);

namespace Glyph\services\media;

use Glyph\domain\media\MediaRecord;

final class MediaUploadResult
{
    public function __construct(
        public readonly bool $isSuccessful,
        public readonly ?MediaRecord $media = null,
        public readonly ?string $errorMessage = null,
    ) {
    }

    public static function success(MediaRecord $media): self
    {
        return new self(true, $media, null);
    }

    public static function failure(string $errorMessage): self
    {
        return new self(false, null, $errorMessage);
    }
}
