<?php

declare(strict_types=1);

namespace Glyph\services\themes;

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\filesystem\UploadedZipArchive;

final class ThemePackageInstaller
{
    private const MANIFEST_FILE = 'theme.json';
    private const MAX_UPLOAD_BYTES = 15728640;

    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly UploadedZipArchive $uploadedZipArchive,
        private readonly string $themesPath,
    ) {
    }

    /**
     * @param array<string, mixed> $uploadedFile
     */
    public function install(array $uploadedFile): ThemeInstallResult
    {
        return $this->uploadedZipArchive->withExtractedUpload(
            $uploadedFile,
            self::MAX_UPLOAD_BYTES,
            'theme',
            function (array $file, string $temporaryZipPath, string $extractPath, array $entryNames): ThemeInstallResult {
                $themeRootPath = $this->uploadedZipArchive->detectPackageRoot($extractPath, self::MANIFEST_FILE, 'theme');
                $manifest = $this->readManifest($themeRootPath);
                $themeDirectoryName = $this->directoryName($themeRootPath);
                $themeName = $this->displayName($manifest, $themeDirectoryName);
                $destinationPath = $this->themesPath . '/' . $themeDirectoryName;

                if ($this->filesystem->isDirectory($destinationPath)) {
                    throw new \RuntimeException('A theme with this directory already exists.');
                }

                $this->filesystem->copyDirectoryRecursively($themeRootPath, $destinationPath);

                return new ThemeInstallResult($themeDirectoryName, $themeName);
            }
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(string $themeRootPath): array
    {
        $manifestPath = $themeRootPath . '/' . self::MANIFEST_FILE;

        if (!$this->filesystem->isFile($manifestPath)) {
            throw new \RuntimeException('The uploaded package does not contain a valid theme.json manifest.');
        }

        $manifest = json_decode($this->filesystem->readFile($manifestPath), true);

        if (!is_array($manifest)) {
            throw new \RuntimeException('The uploaded theme manifest is invalid JSON.');
        }

        return $manifest;
    }

    private function directoryName(string $themeRootPath): string
    {
        $themeDirectoryName = basename($themeRootPath);

        if (!is_string($themeDirectoryName) || preg_match('/^[a-z0-9_-]+$/', $themeDirectoryName) !== 1) {
            throw new \RuntimeException('The theme directory name is invalid. Use lowercase letters, numbers, underscores, or hyphens.');
        }

        return $themeDirectoryName;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function displayName(array $manifest, string $fallback): string
    {
        $themeName = $manifest['name'] ?? $fallback;

        if (!is_string($themeName) || trim($themeName) === '') {
            return $fallback;
        }

        return trim($themeName);
    }
}

