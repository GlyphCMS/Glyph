<?php

declare(strict_types=1);

namespace Glyph\adapters\storage;

use Glyph\adapters\filesystem\LocalFilesystem;

final class RedirectFileRepository
{
    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly string $redirectFilePath,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        if (!$this->filesystem->isFile($this->redirectFilePath)) {
            return [];
        }

        $contents = $this->filesystem->readFile($this->redirectFilePath);
        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid redirects file.');
        }

        $redirects = [];

        foreach ($decoded as $fromPath => $toPath) {
            if (!is_string($fromPath) || !is_string($toPath) || $fromPath === '' || $toPath === '') {
                throw new \RuntimeException('Invalid redirect entry.');
            }

            $redirects[$fromPath] = $toPath;
        }

        return $redirects;
    }

    /**
     * @param array<string, string> $redirects
     */
    public function saveAll(array $redirects): void
    {
        ksort($redirects);

        $json = json_encode($redirects, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($json) || $json === '') {
            throw new \RuntimeException('Failed to encode redirects.');
        }

        $this->filesystem->writeFile($this->redirectFilePath, $json . PHP_EOL);
    }

    public function replace(string $fromPath, string $toPath): void
    {
        $redirects = $this->all();
        $redirects[$fromPath] = $toPath;
        $this->saveAll($redirects);
    }

    public function findTarget(string $fromPath): ?string
    {
        $redirects = $this->all();

        return $redirects[$fromPath] ?? null;
    }

    public function removeByTarget(string $targetPath): void
    {
        $redirects = $this->all();

        foreach ($redirects as $fromPath => $toPath) {
            if ($toPath === $targetPath) {
                unset($redirects[$fromPath]);
            }
        }

        $this->saveAll($redirects);
    }
}