<?php

declare(strict_types=1);

namespace Glyph\domain\media;

final class MediaRecord
{
    public function __construct(
        public readonly string $id,
        public readonly string $originalName,
        public readonly string $storagePath,
        public readonly string $publicPath,
        public readonly string $mimeType,
        public readonly int $sizeBytes,
        public readonly int $width,
        public readonly int $height,
        public readonly string $uploadedBy,
        public readonly string $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: self::requireString($data, 'id'),
            originalName: self::requireString($data, 'original_name'),
            storagePath: self::requireString($data, 'storage_path'),
            publicPath: self::requireString($data, 'public_path'),
            mimeType: self::requireString($data, 'mime_type'),
            sizeBytes: self::requireInt($data, 'size_bytes'),
            width: self::requireInt($data, 'width'),
            height: self::requireInt($data, 'height'),
            uploadedBy: self::requireString($data, 'uploaded_by'),
            createdAt: self::requireString($data, 'created_at'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->originalName,
            'storage_path' => $this->storagePath,
            'public_path' => $this->publicPath,
            'mime_type' => $this->mimeType,
            'size_bytes' => $this->sizeBytes,
            'width' => $this->width,
            'height' => $this->height,
            'uploaded_by' => $this->uploadedBy,
            'created_at' => $this->createdAt,
        ];
    }

    /** @param array<string,mixed> $data */
    private static function requireString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (!is_string($value) || $value === '') {
            throw new \RuntimeException(sprintf('Invalid media field: %s', $key));
        }

        return $value;
    }

    /** @param array<string,mixed> $data */
    private static function requireInt(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (!is_int($value)) {
            throw new \RuntimeException(sprintf('Invalid media field: %s', $key));
        }

        return $value;
    }
}
