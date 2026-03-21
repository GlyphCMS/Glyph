<?php

declare(strict_types=1);

namespace Glyph\services\system;

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\filesystem\UploadedZipArchive;

final class UpdateApplyService
{
    private const MAX_UPLOAD_BYTES = 52428800;
    private const PRESERVED_PREFIXES = ['content', 'data', 'uploads'];
    private const ALLOWED_TOP_LEVEL_PATHS = [
        '.htaccess',
        'INSTALL.md',
        'README.md',
        'UPGRADE.md',
        'assets',
        'bootstrap',
        'config',
        'content',
        'data',
        'index.php',
        'install',
        'plugins',
        'src',
        'storage',
        'tests',
        'themes',
        'uploads',
    ];

    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly UploadedZipArchive $uploadedZipArchive,
        private readonly SystemBackupManager $systemBackupManager,
        private readonly string $installRootPath,
        private readonly string $systemPath,
    ) {
    }

    /**
     * @param array<string, mixed> $uploadedFile
     */
    public function apply(array $uploadedFile, ?string $expectedChecksumSha256 = null): UpdateApplyResult
    {
        return $this->uploadedZipArchive->withExtractedUpload(
            $uploadedFile,
            self::MAX_UPLOAD_BYTES,
            'update',
            function (array $file, string $temporaryZipPath, string $extractPath, array $entryNames) use ($expectedChecksumSha256): UpdateApplyResult {
                $packageSha256 = hash_file('sha256', $temporaryZipPath);

                if (!is_string($packageSha256) || $packageSha256 === '') {
                    throw new \RuntimeException('Failed to compute package checksum.');
                }

                if ($expectedChecksumSha256 !== null && $expectedChecksumSha256 !== '' && !hash_equals(strtolower($expectedChecksumSha256), strtolower($packageSha256))) {
                    throw new \RuntimeException('The uploaded package checksum does not match the expected SHA-256 checksum.');
                }

                $glyphRootPath = $this->detectGlyphRoot($extractPath);
                $this->assertAllowedTopLevelPaths($glyphRootPath);
                $backupArchivePath = $this->systemBackupManager->createBackupArchive();

                $appliedFileCount = 0;
                $ensuredDirectoryCount = 0;
                $warnings = [];

                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($glyphRootPath, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $item) {
                    $sourcePath = $item->getPathname();
                    $relativePath = ltrim(str_replace($glyphRootPath, '', $sourcePath), '/\\');
                    $relativePath = str_replace('\\', '/', $relativePath);

                    if ($relativePath === '' || $this->isPreservedRelativePath($relativePath)) {
                        if ($relativePath !== '' && $this->isPreservedRelativePath($relativePath)) {
                            $warnings[] = sprintf('Skipped preserved path: %s', $relativePath);
                        }
                        continue;
                    }

                    $destinationPath = $this->installRootPath . '/' . $relativePath;

                    if ($item->isDir()) {
                        if (!$this->filesystem->isDirectory($destinationPath)) {
                            $this->filesystem->ensureDirectoryExists($destinationPath);
                            $ensuredDirectoryCount++;
                        }
                        continue;
                    }

                    $this->filesystem->copyFile($sourcePath, $destinationPath);
                    $appliedFileCount++;
                }

                $detectedVersion = $this->detectVersionFromPackage($glyphRootPath);
                $this->writeLastApplyState($packageSha256, $backupArchivePath, $detectedVersion);

                return new UpdateApplyResult(
                    backupArchivePath: $backupArchivePath,
                    appliedFileCount: $appliedFileCount,
                    ensuredDirectoryCount: $ensuredDirectoryCount,
                    packageSha256: $packageSha256,
                    detectedVersion: $detectedVersion,
                    warnings: array_values(array_unique($warnings)),
                );
            }
        );
    }

    private function detectGlyphRoot(string $extractPath): string
    {
        $candidates = [$extractPath];
        foreach ($this->filesystem->listDirectories($extractPath) as $directory) {
            $candidates[] = $directory;
        }

        foreach ($candidates as $candidate) {
            if (
                $this->filesystem->isFile($candidate . '/bootstrap/app.php')
                && $this->filesystem->isFile($candidate . '/bootstrap/config.php')
                && $this->filesystem->isFile($candidate . '/config/app.php')
                && $this->filesystem->isDirectory($candidate . '/src')
                && $this->filesystem->isDirectory($candidate . '/themes')
            ) {
                return $candidate;
            }
        }

        throw new \RuntimeException('The uploaded package does not contain a valid Glyph application root.');
    }

    private function isPreservedRelativePath(string $relativePath): bool
    {
        foreach (self::PRESERVED_PREFIXES as $prefix) {
            if ($relativePath === $prefix || str_starts_with($relativePath, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    private function assertAllowedTopLevelPaths(string $glyphRootPath): void
    {
        $allowed = array_flip(self::ALLOWED_TOP_LEVEL_PATHS);

        foreach (scandir($glyphRootPath) ?: [] as $entry) {
            if (!is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }

            if (!isset($allowed[$entry])) {
                throw new \RuntimeException(sprintf(
                    'The update package contains an unexpected top-level path: %s',
                    $entry
                ));
            }
        }
    }

    private function detectVersionFromPackage(string $glyphRootPath): ?string
    {
        $configPath = $glyphRootPath . '/config/app.php';

        if (!$this->filesystem->isFile($configPath)) {
            return null;
        }

        $config = require $configPath;

        if (!is_array($config)) {
            return null;
        }

        $version = $config['version'] ?? null;

        return is_string($version) && $version !== '' ? $version : null;
    }

    private function writeLastApplyState(string $packageSha256, string $backupArchivePath, ?string $detectedVersion): void
    {
        $path = $this->systemPath . '/updater-last-apply.php';
        $state = [
            'applied_at' => gmdate('c'),
            'package_sha256' => $packageSha256,
            'backup_archive' => basename($backupArchivePath),
            'detected_version' => $detectedVersion,
        ];

        $export = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($state, true) . ";\n";
        $this->filesystem->writeFile($path, $export);
    }
}

