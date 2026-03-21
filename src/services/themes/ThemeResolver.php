<?php

declare(strict_types=1);

namespace Glyph\services\themes;

use Glyph\adapters\filesystem\LocalFilesystem;

final class ThemeResolver
{
    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly string $themesPath,
        private readonly string $defaultTheme,
    ) {
    }

    public function resolve(string $activeTheme): ThemeData
    {
        $themeName = $this->sanitizeThemeName($activeTheme);

        if ($themeName === '' || !$this->themeExists($themeName)) {
            $themeName = $this->defaultTheme;
        }

        if (!$this->themeExists($themeName)) {
            throw new \RuntimeException(sprintf('Theme not found: %s', $themeName));
        }

        return $this->loadTheme($themeName);
    }

    /**
     * @return list<ThemeData>
     */
    public function listThemes(): array
    {
        $themes = [];

        foreach ($this->filesystem->listDirectories($this->themesPath) as $directoryPath) {
            $directoryName = basename($directoryPath);

            if (!is_string($directoryName) || $this->sanitizeThemeName($directoryName) === '') {
                continue;
            }

            $themes[] = $this->loadTheme($directoryName);
        }

        usort(
            $themes,
            static fn (ThemeData $left, ThemeData $right): int => strcmp($left->name, $right->name)
        );

        return $themes;
    }

    private function loadTheme(string $themeName): ThemeData
    {
        $path = $this->themesPath . '/' . $themeName;
        $manifestPath = $path . '/theme.json';
        $manifest = [];

        if ($this->filesystem->isFile($manifestPath)) {
            $contents = $this->filesystem->readFile($manifestPath);
            $decoded = json_decode($contents, true);

            if (!is_array($decoded)) {
                throw new \RuntimeException(sprintf('Invalid theme manifest: %s', $manifestPath));
            }

            $manifest = $decoded;
        }

        $name = $this->manifestString($manifest, 'name', $themeName);
        $version = $this->manifestString($manifest, 'version', '0.1.0');
        $author = $this->manifestString($manifest, 'author', 'Unknown');
        $description = $this->manifestString($manifest, 'description', '');
        $screenshot = $this->manifestString($manifest, 'screenshot', '');
        $screenshotUrl = $this->themeAssetUrl($themeName, $screenshot !== '' ? $screenshot : 'assets/screenshot.svg');
        $assets = $this->loadAssetsManifest($path);

        return new ThemeData(
            name: $name,
            directoryName: $themeName,
            path: $path,
            version: $version,
            author: $author,
            description: $description,
            screenshotUrl: $screenshotUrl,
            assets: $assets,
            manifest: $manifest,
        );
    }

    /**
     * @return array<string, string>
     */
    private function loadAssetsManifest(string $themePath): array
    {
        $manifestPath = $themePath . '/assets/manifest.json';

        if (!$this->filesystem->isFile($manifestPath)) {
            return [];
        }

        $contents = $this->filesystem->readFile($manifestPath);
        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException(sprintf('Invalid theme asset manifest: %s', $manifestPath));
        }

        $assets = [];

        foreach ($decoded as $key => $value) {
            if (!is_string($key) || $key === '' || !is_string($value) || $value === '') {
                continue;
            }

            $assets[$key] = $value;
        }

        return $assets;
    }

    private function themeExists(string $themeName): bool
    {
        return $this->filesystem->isDirectory($this->themesPath . '/' . $themeName);
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function manifestString(array $manifest, string $key, string $default): string
    {
        $value = $manifest[$key] ?? null;

        if (!is_string($value) || trim($value) === '') {
            return $default;
        }

        return trim($value);
    }

    private function sanitizeThemeName(string $themeName): string
    {
        if (preg_match('/^[a-z0-9_-]+$/', $themeName) !== 1) {
            return '';
        }

        return $themeName;
    }

    private function themeAssetUrl(string $themeName, string $relativePath): ?string
    {
        $normalizedPath = ltrim($relativePath, '/');

        if ($normalizedPath === '' || preg_match('/^[a-zA-Z0-9_\/.:-]+$/', $normalizedPath) !== 1) {
            return null;
        }

        $absolutePath = $this->themesPath . '/' . $themeName . '/' . $normalizedPath;

        if (!$this->filesystem->isFile($absolutePath)) {
            return null;
        }

        return '/themes/' . rawurlencode($themeName) . '/' . str_replace('%2F', '/', rawurlencode($normalizedPath));
    }
}
