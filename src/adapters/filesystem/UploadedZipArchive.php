<?php

declare(strict_types=1);

namespace Glyph\adapters\filesystem;

final class UploadedZipArchive
{
    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly UploadedZipInspector $uploadedZipInspector,
    ) {
    }

    /**
     * @template TResult
     * @param array<string, mixed> $uploadedFile
     * @param callable(array{original_name:string, temporary_path:string, size_bytes:int, extension:string, mime_type:string}, string, \ZipArchive, list<string>): TResult $callback
     * @return TResult
     */
    public function withStagedUpload(array $uploadedFile, int $maxUploadBytes, string $packageType, callable $callback): mixed
    {
        $descriptor = $this->packageDescriptor($packageType);

        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('ZIP support is not available on this server.');
        }

        $file = $this->uploadedZipInspector->inspect($uploadedFile, $descriptor);

        if ($file['size_bytes'] > $maxUploadBytes) {
            throw new \RuntimeException(sprintf('The uploaded %s is too large.', $descriptor));
        }

        $temporaryZipPath = $this->temporaryZipPath($packageType);
        $zip = new \ZipArchive();
        $opened = false;

        try {
            $this->filesystem->moveUploadedFile($file['temporary_path'], $temporaryZipPath);

            $opened = $zip->open($temporaryZipPath) === true;

            if (!$opened) {
                throw new \RuntimeException(sprintf('Failed to open the uploaded %s.', $descriptor));
            }

            $entryNames = $this->validatedEntryNames($zip, $descriptor);

            return $callback($file, $temporaryZipPath, $zip, $entryNames);
        } finally {
            if ($opened) {
                $zip->close();
            }

            if ($this->filesystem->isFile($temporaryZipPath)) {
                $this->filesystem->deleteFile($temporaryZipPath);
            }
        }
    }

    /**
     * @template TResult
     * @param array<string, mixed> $uploadedFile
     * @param callable(array{original_name:string, temporary_path:string, size_bytes:int, extension:string, mime_type:string}, string, string, list<string>): TResult $callback
     * @return TResult
     */
    public function withExtractedUpload(array $uploadedFile, int $maxUploadBytes, string $packageType, callable $callback): mixed
    {
        $descriptor = $this->packageDescriptor($packageType);
        $extractPath = $this->extractPath($packageType);

        try {
            return $this->withStagedUpload(
                $uploadedFile,
                $maxUploadBytes,
                $packageType,
                function (array $file, string $temporaryZipPath, \ZipArchive $zip, array $entryNames) use ($callback, $descriptor, $extractPath) {
                    $this->filesystem->ensureDirectoryExists($extractPath);

                    if (!$zip->extractTo($extractPath)) {
                        throw new \RuntimeException(sprintf('Failed to extract the %s.', $descriptor));
                    }

                    return $callback($file, $temporaryZipPath, $extractPath, $entryNames);
                }
            );
        } finally {
            if ($this->filesystem->isDirectory($extractPath)) {
                $this->filesystem->deleteDirectoryRecursively($extractPath);
            }
        }
    }

    public function detectPackageRoot(string $extractPath, string $manifestFileName, string $packageType): string
    {
        $manifestAtRoot = $extractPath . '/' . $manifestFileName;

        if ($this->filesystem->isFile($manifestAtRoot)) {
            return $extractPath;
        }

        $directories = $this->filesystem->listDirectories($extractPath);

        if (count($directories) !== 1) {
            throw new \RuntimeException(sprintf(
                'The %s package must contain exactly one %s directory or a manifest at the archive root.',
                $packageType,
                $packageType
            ));
        }

        $candidate = $directories[0];

        if (!$this->filesystem->isFile($candidate . '/' . $manifestFileName)) {
            throw new \RuntimeException(sprintf(
                'The %s package must contain a %s manifest.',
                $packageType,
                $manifestFileName
            ));
        }

        return $candidate;
    }

    /**
     * @return list<string>
     */
    private function validatedEntryNames(\ZipArchive $zip, string $descriptor): array
    {
        $entryNames = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = $zip->getNameIndex($index);

            if (!is_string($entryName) || $entryName === '') {
                throw new \RuntimeException(sprintf('The %s contains an invalid entry.', $descriptor));
            }

            if (str_contains($entryName, '..') || str_starts_with($entryName, '/') || str_contains($entryName, '\\')) {
                throw new \RuntimeException(sprintf('The %s contains an unsafe path.', $descriptor));
            }

            $entryNames[] = $entryName;
        }

        return $entryNames;
    }

    private function temporaryZipPath(string $packageType): string
    {
        return sys_get_temp_dir() . '/glyph-' . $packageType . '-' . bin2hex(random_bytes(8)) . '.zip';
    }

    private function extractPath(string $packageType): string
    {
        return sys_get_temp_dir() . '/glyph-' . $packageType . '-extract-' . bin2hex(random_bytes(8));
    }

    private function packageDescriptor(string $packageType): string
    {
        return $packageType . ' package';
    }
}
