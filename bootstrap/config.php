<?php

declare(strict_types=1);

use Glyph\domain\shared\AppPaths;

require_once __DIR__ . '/autoload.php';

$rootPath = dirname(__DIR__);
$pathConfig = require $rootPath . '/config/paths.php';

$resolvedPaths = [];

foreach ($pathConfig as $key => $relativePath) {
    if (!is_string($key) || !is_string($relativePath) || $relativePath === '') {
        throw new RuntimeException('Invalid paths configuration.');
    }

    $resolvedPaths[$key] = $rootPath . '/' . ltrim($relativePath, '/');
}

$paths = new AppPaths($rootPath, $resolvedPaths);

$config = [
    'app' => glyphLoadPhpArray($rootPath . '/config/app.php'),
    'auth' => glyphLoadPhpArray($rootPath . '/config/auth.php'),
    'cache' => loadRuntimeConfig(
        defaultConfig: glyphLoadPhpArray($rootPath . '/config/cache.php'),
        runtimeConfigPath: $paths->get('data_system') . '/cache.php',
    ),
    'generated' => loadRuntimeConfig(
        defaultConfig: [
            'is_installed' => false,
            'site_url' => '',
            'installed_at' => null,
            'owner_user_id' => null,
            'secret_key' => '',
        ],
        runtimeConfigPath: $paths->get('data_system') . '/generated.php',
    ),
    'navigation' => loadRuntimeConfig(
        defaultConfig: glyphLoadPhpArray($rootPath . '/config/navigation.php'),
        runtimeConfigPath: $paths->get('data_system') . '/navigation.php',
    ),
    'updater' => loadRuntimeConfig(
        defaultConfig: glyphLoadPhpArray($rootPath . '/config/updater.php'),
        runtimeConfigPath: $paths->get('data_system') . '/updater.php',
    ),
    'versioning' => glyphLoadPhpArray($rootPath . '/config/versioning.php'),
    'maintenance' => loadRuntimeConfig(
        defaultConfig: glyphLoadPhpArray($rootPath . '/config/maintenance.php'),
        runtimeConfigPath: $paths->get('data_system') . '/maintenance.php',
    ),
    'mail' => loadRuntimeConfig(
        defaultConfig: glyphLoadPhpArray($rootPath . '/config/mail.php'),
        runtimeConfigPath: $paths->get('data_system') . '/mail.php',
    ),
    'plugins' => loadRuntimeConfig(
        defaultConfig: glyphLoadPhpArray($rootPath . '/config/plugins.php'),
        runtimeConfigPath: $paths->get('data_system') . '/plugins.php',
    ),
    'media' => glyphLoadPhpArray($rootPath . '/config/media.php'),
    'paths' => $pathConfig,
    'site' => loadRuntimeConfig(
        defaultConfig: glyphLoadPhpArray($rootPath . '/config/site.php'),
        runtimeConfigPath: $paths->get('data_system') . '/site.php',
    ),
];

if (
    (!isset($config['site']['site_url']) || !is_string($config['site']['site_url']) || $config['site']['site_url'] === '')
    && isset($config['generated']['site_url'])
    && is_string($config['generated']['site_url'])
    && $config['generated']['site_url'] !== ''
) {
    $config['site']['site_url'] = $config['generated']['site_url'];
}

if (
    !isset($config['app']['timezone']) ||
    !is_string($config['app']['timezone']) ||
    $config['app']['timezone'] === ''
) {
    throw new RuntimeException('Invalid application timezone configuration.');
}

$siteTimezone = $config['site']['timezone'] ?? $config['app']['timezone'];

if (!is_string($siteTimezone) || $siteTimezone === '' || !in_array($siteTimezone, timezone_identifiers_list(), true)) {
    $siteTimezone = $config['app']['timezone'];
}

date_default_timezone_set($siteTimezone);

return [
    'config' => $config,
    'paths' => $paths,
];

/**
 * @param array<string, mixed> $defaultConfig
 * @return array<string, mixed>
 */
function loadRuntimeConfig(array $defaultConfig, string $runtimeConfigPath): array
{
    if (!is_file($runtimeConfigPath)) {
        return $defaultConfig;
    }

    $runtimeConfig = glyphLoadPhpArray($runtimeConfigPath);

    return array_replace($defaultConfig, $runtimeConfig);
}

/** @return array<string, mixed> */
function glyphLoadPhpArray(string $path): array
{
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($path, true);
    }

    $value = require $path;

    if (!is_array($value)) {
        throw new RuntimeException(sprintf('Invalid runtime configuration file: %s', $path));
    }

    return $value;
}

