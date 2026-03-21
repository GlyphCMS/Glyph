<?php

declare(strict_types=1);

namespace Glyph\services\system;

use Glyph\adapters\filesystem\LocalFilesystem;

final class SystemBackupManager
{
    /**
     * @param array<string, string> $paths
     */
    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly string $rootPath,
        private readonly string $backupPath,
        private readonly array $paths,
    ) {
    }

    public function createBackupArchive(): string
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('ZIP support is not available on this server.');
        }

        $this->filesystem->ensureDirectoryExists($this->backupPath);

        $timestamp = gmdate('Ymd-His');
        if (!is_string($timestamp) || $timestamp === '') {
            throw new \RuntimeException('Failed to determine backup timestamp.');
        }

        $archivePath = $this->backupPath . '/glyph-backup-' . $timestamp . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to create backup archive.');
        }

        try {
            foreach ($this->backupDirectories() as $label => $absolutePath) {
                if (!is_dir($absolutePath)) {
                    continue;
                }

                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($absolutePath, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $item) {
                    $itemPath = $item->getPathname();
                    $relativePath = $label . '/' . ltrim(str_replace($absolutePath, '', $itemPath), '/\\');

                    if ($item->isDir()) {
                        $zip->addEmptyDir(str_replace('\\', '/', $relativePath));
                        continue;
                    }

                    $zip->addFile($itemPath, str_replace('\\', '/', $relativePath));
                }
            }

            $zip->addFromString('backup-manifest.json', (string) json_encode([
                'created_at' => gmdate('c'),
                'version' => 1,
                'included_directories' => array_keys($this->backupDirectories()),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } finally {
            $zip->close();
        }

        return $archivePath;
    }

    /**
     * @return array<string, string>
     */
    private function backupDirectories(): array
    {
        return [
            'content' => $this->paths['content'],
            'data' => $this->paths['data'],
            'uploads' => $this->paths['uploads'],
            'themes' => $this->paths['themes'],
            'plugins' => $this->paths['plugins'],
            'config' => $this->paths['config'],
        ];
    }
}
