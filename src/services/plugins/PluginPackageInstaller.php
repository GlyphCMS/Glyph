<?php

declare(strict_types=1);

namespace Glyph\services\plugins;

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\filesystem\UploadedZipArchive;

final class PluginPackageInstaller
{
    private const MANIFEST_FILE = 'plugin.json';
    private const MAX_UPLOAD_BYTES = 15728640;

    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly UploadedZipArchive $uploadedZipArchive,
        private readonly string $pluginsPath,
    ) {
    }

    /**
     * @param array<string, mixed> $uploadedFile
     */
    public function install(array $uploadedFile): PluginInstallResult
    {
        return $this->uploadedZipArchive->withExtractedUpload(
            $uploadedFile,
            self::MAX_UPLOAD_BYTES,
            'plugin',
            function (array $file, string $temporaryZipPath, string $extractPath, array $entryNames): PluginInstallResult {
                $pluginRootPath = $this->uploadedZipArchive->detectPackageRoot($extractPath, self::MANIFEST_FILE, 'plugin');
                $manifest = $this->readManifest($pluginRootPath);
                $this->assertBootstrapFileExists($pluginRootPath);
                $pluginDirectoryName = $this->directoryName($pluginRootPath);
                $pluginName = $this->displayName($manifest, $pluginDirectoryName);
                $destinationPath = $this->pluginsPath . '/' . $pluginDirectoryName;

                if ($this->filesystem->isDirectory($destinationPath)) {
                    throw new \RuntimeException('A plugin with this directory already exists.');
                }

                $this->filesystem->copyDirectoryRecursively($pluginRootPath, $destinationPath);
                $this->runInstallLifecycle($destinationPath, $pluginDirectoryName);

                return new PluginInstallResult($pluginDirectoryName, $pluginName);
            }
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(string $pluginRootPath): array
    {
        $manifestPath = $pluginRootPath . '/' . self::MANIFEST_FILE;

        if (!$this->filesystem->isFile($manifestPath)) {
            throw new \RuntimeException('The uploaded package does not contain a valid plugin.json manifest.');
        }

        $manifest = json_decode($this->filesystem->readFile($manifestPath), true);

        if (!is_array($manifest)) {
            throw new \RuntimeException('The uploaded plugin manifest is invalid JSON.');
        }

        return $manifest;
    }

    private function assertBootstrapFileExists(string $pluginRootPath): void
    {
        if (!$this->filesystem->isFile($pluginRootPath . '/bootstrap.php')) {
            throw new \RuntimeException('The uploaded plugin package must contain a bootstrap.php file.');
        }
    }

    private function directoryName(string $pluginRootPath): string
    {
        $pluginDirectoryName = basename($pluginRootPath);

        if (!is_string($pluginDirectoryName) || preg_match('/^[a-z0-9_-]+$/', $pluginDirectoryName) !== 1) {
            throw new \RuntimeException('The plugin directory name is invalid. Use lowercase letters, numbers, underscores, or hyphens.');
        }

        return $pluginDirectoryName;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function displayName(array $manifest, string $fallback): string
    {
        $pluginName = $manifest['name'] ?? $fallback;

        if (!is_string($pluginName) || trim($pluginName) === '') {
            return $fallback;
        }

        return trim($pluginName);
    }

    private function runInstallLifecycle(string $destinationPath, string $pluginDirectoryName): void
    {
        $installPath = $destinationPath . '/install.php';

        if (!$this->filesystem->isFile($installPath)) {
            return;
        }

        $callable = require $installPath;

        if (!is_callable($callable)) {
            throw new \RuntimeException(sprintf(
                'Plugin install lifecycle file for "%s" must return a callable.',
                $pluginDirectoryName
            ));
        }

        $callable();
    }
}

