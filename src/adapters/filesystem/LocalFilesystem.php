<?php

declare(strict_types=1);

namespace Glyph\adapters\filesystem;

final class LocalFilesystem
{
    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    public function isWritable(string $path): bool
    {
        return is_writable($path);
    }

    public function createDirectory(string $path, int $permissions = 0755): void
    {
        if ($this->isDirectory($path)) {
            return;
        }

        if (!mkdir($path, $permissions, true) && !$this->isDirectory($path)) {
            throw new \RuntimeException(sprintf('Failed to create directory: %s', $path));
        }
    }

    public function ensureDirectoryExists(string $path, int $permissions = 0755): void
    {
        if ($this->isDirectory($path)) {
            return;
        }

        $parentDirectory = dirname($path);

        if (!$this->isDirectory($parentDirectory)) {
            $this->ensureDirectoryExists($parentDirectory, $permissions);
        }

        $this->createDirectory($path, $permissions);
    }

    public function readFile(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException(sprintf('Failed to read file: %s', $path));
        }

        return $contents;
    }

    public function writeFile(string $path, string $contents): void
    {
        $parentDirectory = dirname($path);

        if (!$this->isDirectory($parentDirectory)) {
            $this->ensureDirectoryExists($parentDirectory);
        }

        $bytes = file_put_contents($path, $contents, LOCK_EX);

        if ($bytes === false) {
            throw new \RuntimeException(sprintf('Failed to write file: %s', $path));
        }
    }

    public function deleteFile(string $path): void
    {
        if (!$this->isFile($path) && !is_link($path)) {
            return;
        }

        if (!unlink($path)) {
            throw new \RuntimeException(sprintf('Failed to delete file: %s', $path));
        }
    }

    public function deleteDirectoryRecursively(string $path): void
    {
        if (is_link($path)) {
            $this->deleteFile($path);
            return;
        }

        if (!$this->isDirectory($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            throw new \RuntimeException(sprintf('Failed to scan directory: %s', $path));
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;

            if (is_link($itemPath)) {
                $this->deleteFile($itemPath);
                continue;
            }

            if (is_dir($itemPath)) {
                $this->deleteDirectoryRecursively($itemPath);
                continue;
            }

            $this->deleteFile($itemPath);
        }

        if (!rmdir($path)) {
            throw new \RuntimeException(sprintf('Failed to remove directory: %s', $path));
        }
    }

    /**
     * @return list<string>
     */
    public function listDirectories(string $path): array
    {
        if (!$this->isDirectory($path)) {
            return [];
        }

        $items = scandir($path);

        if ($items === false) {
            throw new \RuntimeException(sprintf('Failed to scan directory: %s', $path));
        }

        $directories = [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;

            if (is_dir($itemPath) && !is_link($itemPath)) {
                $directories[] = $itemPath;
            }
        }

        sort($directories);

        return $directories;
    }

    public function copyDirectoryRecursively(string $sourcePath, string $destinationPath): void
    {
        if (is_link($sourcePath)) {
            throw new \RuntimeException(sprintf('Refusing to copy symlinked directory: %s', $sourcePath));
        }

        if (!$this->isDirectory($sourcePath)) {
            throw new \RuntimeException(sprintf('Source directory does not exist: %s', $sourcePath));
        }

        $this->ensureDirectoryExists($destinationPath);

        $items = scandir($sourcePath);

        if ($items === false) {
            throw new \RuntimeException(sprintf('Failed to scan directory: %s', $sourcePath));
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourceItemPath = $sourcePath . '/' . $item;
            $destinationItemPath = $destinationPath . '/' . $item;

            if (is_link($sourceItemPath)) {
                throw new \RuntimeException(sprintf('Refusing to copy symlinked path: %s', $sourceItemPath));
            }

            if (is_dir($sourceItemPath)) {
                $this->copyDirectoryRecursively($sourceItemPath, $destinationItemPath);
                continue;
            }

            $this->copyFile($sourceItemPath, $destinationItemPath);
        }
    }

    public function copyFile(string $sourcePath, string $destinationPath): void
    {
        if (is_link($sourcePath)) {
            throw new \RuntimeException(sprintf('Refusing to copy symlinked file: %s', $sourcePath));
        }

        $parentDirectory = dirname($destinationPath);

        if (!$this->isDirectory($parentDirectory)) {
            $this->ensureDirectoryExists($parentDirectory);
        }

        if (!copy($sourcePath, $destinationPath)) {
            throw new \RuntimeException(sprintf('Failed to copy file: %s', $sourcePath));
        }
    }

    public function moveUploadedFile(string $temporaryPath, string $destinationPath): void
    {
        $parentDirectory = dirname($destinationPath);

        if (!$this->isDirectory($parentDirectory)) {
            $this->ensureDirectoryExists($parentDirectory);
        }

        $moved = move_uploaded_file($temporaryPath, $destinationPath);

        if (!$moved && PHP_SAPI === 'cli') {
            $moved = rename($temporaryPath, $destinationPath);
        }

        if (!$moved) {
            throw new \RuntimeException(sprintf('Failed to move uploaded file: %s', $temporaryPath));
        }
    }
}




