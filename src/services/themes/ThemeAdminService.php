<?php

declare(strict_types=1);

namespace Glyph\services\themes;

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\storage\PhpConfigWriter;

final class ThemeAdminService
{
    /**
     * @param array<string, mixed> $siteConfig
     */
    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly PhpConfigWriter $configWriter,
        private readonly ThemeResolver $themeResolver,
        private readonly string $systemPath,
        private readonly string $themesPath,
        private readonly array $siteConfig,
        private readonly string $defaultTheme,
    ) {
    }

    public function activate(string $themeDirectoryName): void
    {
        $theme = $this->themeResolver->resolve($themeDirectoryName);
        $siteConfig = $this->siteConfig;
        $siteConfig['active_theme'] = $theme->directoryName;

        $this->configWriter->write($this->systemPath . '/site.php', $this->siteSettingsPayload($siteConfig));
    }

    public function delete(string $themeDirectoryName): void
    {
        if ($themeDirectoryName === $this->defaultTheme) {
            throw new \RuntimeException('The default theme cannot be deleted.');
        }

        $activeTheme = $this->siteConfig['active_theme'] ?? $this->defaultTheme;
        if (is_string($activeTheme) && $themeDirectoryName === $activeTheme) {
            throw new \RuntimeException('The active theme cannot be deleted.');
        }

        $theme = $this->themeResolver->resolve($themeDirectoryName);
        $themePath = $this->themesPath . '/' . $theme->directoryName;

        if (!$this->filesystem->isDirectory($themePath)) {
            throw new \RuntimeException('Theme not found.');
        }

        $this->filesystem->deleteDirectoryRecursively($themePath);
    }

    /**
     * @param array<string, mixed> $siteConfig
     * @return array<string, mixed>
     */
    private function siteSettingsPayload(array $siteConfig): array
    {
        return [
            'site_name' => is_string($siteConfig['site_name'] ?? null) ? $siteConfig['site_name'] : 'Glyph',
            'tagline' => is_string($siteConfig['tagline'] ?? null) ? $siteConfig['tagline'] : '',
            'active_theme' => is_string($siteConfig['active_theme'] ?? null) ? $siteConfig['active_theme'] : $this->defaultTheme,
            'homepage_mode' => is_string($siteConfig['homepage_mode'] ?? null) ? $siteConfig['homepage_mode'] : 'posts',
        ];
    }
}
