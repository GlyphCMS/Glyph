<?php

declare(strict_types=1);

namespace Glyph\services\system;

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\storage\UserFileRepository;
use Glyph\services\content\ContentService;
use Glyph\services\plugins\PluginResolver;
use Glyph\services\themes\ThemeResolver;

final class SystemInfoService
{
    /**
     * @param array<string, mixed> $appConfig
     * @param array<string, mixed> $siteConfig
     * @param array<string, string> $paths
     */
    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly ContentService $contentService,
        private readonly UserFileRepository $userRepository,
        private readonly ThemeResolver $themeResolver,
        private readonly PluginResolver $pluginResolver,
        private readonly array $appConfig,
        private readonly array $siteConfig,
        private readonly array $paths,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $contentCounts = [
            'posts' => 0,
            'pages' => 0,
            'published' => 0,
            'drafts' => 0,
            'published_posts' => 0,
            'draft_posts' => 0,
            'published_pages' => 0,
            'draft_pages' => 0,
        ];

        foreach ($this->contentService->listAll() as $content) {
            $isPublished = $content->status === 'published';

            if ($content->type === 'post') {
                $contentCounts['posts']++;
                if ($isPublished) {
                    $contentCounts['published_posts']++;
                } else {
                    $contentCounts['draft_posts']++;
                }
            } elseif ($content->type === 'page') {
                $contentCounts['pages']++;
                if ($isPublished) {
                    $contentCounts['published_pages']++;
                } else {
                    $contentCounts['draft_pages']++;
                }
            }

            if ($isPublished) {
                $contentCounts['published']++;
            } else {
                $contentCounts['drafts']++;
            }
        }

        $users = $this->userRepository->all();
        $activeUsers = 0;
        foreach ($users as $user) {
            if ($user->isActive) {
                $activeUsers++;
            }
        }

        $themes = $this->themeResolver->listThemes();
        $plugins = $this->pluginResolver->listPlugins();
        $enabledPlugins = 0;
        foreach ($plugins as $plugin) {
            if ($plugin->isEnabled) {
                $enabledPlugins++;
            }
        }

        $pathInfo = [];
        foreach ($this->paths as $name => $path) {
            $pathInfo[$name] = [
                'path' => $path,
                'exists' => file_exists($path),
                'writable' => file_exists($path) ? $this->filesystem->isWritable($path) : $this->filesystem->isWritable(dirname($path)),
            ];
        }

        $storagePath = $this->paths['storage'] ?? '';
        $storageTotal = is_string($storagePath) && $storagePath !== '' ? disk_total_space($storagePath) : false;
        $storageFree = is_string($storagePath) && $storagePath !== '' ? disk_free_space($storagePath) : false;
        $storageTotalBytes = is_int($storageTotal) || is_float($storageTotal) ? (int) $storageTotal : null;
        $storageFreeBytes = is_int($storageFree) || is_float($storageFree) ? (int) $storageFree : null;
        $storageUsedBytes = ($storageTotalBytes !== null && $storageFreeBytes !== null)
            ? max(0, $storageTotalBytes - $storageFreeBytes)
            : null;
        $storageUsedPercent = ($storageTotalBytes !== null && $storageTotalBytes > 0 && $storageUsedBytes !== null)
            ? round(($storageUsedBytes / $storageTotalBytes) * 100, 1)
            : null;

        $loadAverage = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
        $loadAverageOneMinute = is_array($loadAverage) && isset($loadAverage[0]) && is_numeric($loadAverage[0])
            ? (float) $loadAverage[0]
            : null;

        $siteUrl = $this->siteConfig['site_url'] ?? '';
        $isHttpsUrl = is_string($siteUrl) && str_starts_with(strtolower($siteUrl), 'https://');

        return [
            'version' => $this->appConfig['version'] ?? '0.1.0-dev',
            'environment' => $this->appConfig['environment'] ?? 'production',
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'timezone' => $this->siteConfig['timezone'] ?? 'UTC',
            'site_name' => $this->siteConfig['site_name'] ?? 'Glyph',
            'active_theme' => $this->siteConfig['active_theme'] ?? 'default',
            'theme_count' => count($themes),
            'plugin_count' => count($plugins),
            'enabled_plugin_count' => $enabledPlugins,
            'user_count' => count($users),
            'active_user_count' => $activeUsers,
            'content_counts' => $contentCounts,
            'storage_total_bytes' => $storageTotalBytes,
            'storage_free_bytes' => $storageFreeBytes,
            'storage_used_bytes' => $storageUsedBytes,
            'storage_used_percent' => $storageUsedPercent,
            'load_average_1m' => $loadAverageOneMinute,
            'extensions' => [
                'apcu' => extension_loaded('apcu'),
                'zip' => class_exists('ZipArchive'),
                'mbstring' => extension_loaded('mbstring'),
                'fileinfo' => extension_loaded('fileinfo'),
                'openssl' => extension_loaded('openssl'),
            ],
            'security' => [
                'debug_disabled' => (($this->appConfig['debug'] ?? false) === false),
                'production_environment' => (($this->appConfig['environment'] ?? 'production') === 'production'),
                'site_url_configured' => is_string($siteUrl) && $siteUrl !== '',
                'site_url_https' => $isHttpsUrl,
                'storage_outside_webroot_recommended' => !str_contains(str_replace('\\', '/', $this->paths['storage']), '/public/'),
            ],
            'paths' => $pathInfo,
        ];
    }
}
