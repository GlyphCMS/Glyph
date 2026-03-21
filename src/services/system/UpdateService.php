<?php

declare(strict_types=1);

namespace Glyph\services\system;

final class UpdateService
{
    /**
     * @param array<string, mixed> $appConfig
     */
    public function __construct(
        private readonly UpdateManifestFetcher $manifestFetcher,
        private readonly UpdatePreflightChecker $preflightChecker,
        private readonly array $appConfig,
    ) {
    }

    public function check(string $manifestUrl, string $channel): UpdateCheckResult
    {
        $manifest = $this->manifestFetcher->fetch($manifestUrl);
        $latest = is_array($manifest['latest'] ?? null) ? $manifest['latest'] : [];
        $currentVersion = is_string($this->appConfig['version'] ?? null) ? (string) $this->appConfig['version'] : '0.1.0-dev';
        $latestVersion = is_string($latest['version'] ?? null) ? trim($latest['version']) : '';
        $publishedAt = is_string($latest['published_at'] ?? null) ? trim($latest['published_at']) : null;
        $packageUrl = is_string($latest['package_url'] ?? null) ? trim($latest['package_url']) : null;
        $notesUrl = is_string($latest['notes_url'] ?? null) ? trim($latest['notes_url']) : null;
        $checksumSha256 = is_string($latest['checksum_sha256'] ?? null) ? trim($latest['checksum_sha256']) : null;
        $minPhpVersion = is_string($latest['min_php_version'] ?? null) ? trim($latest['min_php_version']) : null;

        $isUpdateAvailable = $latestVersion !== '' && version_compare($latestVersion, $currentVersion, '>');

        return new UpdateCheckResult(
            isUpdateAvailable: $isUpdateAvailable,
            currentVersion: $currentVersion,
            latestVersion: $latestVersion,
            channel: $channel,
            publishedAt: $publishedAt,
            packageUrl: $packageUrl,
            notesUrl: $notesUrl,
            checksumSha256: $checksumSha256,
            preflight: $this->preflightChecker->check($minPhpVersion),
            message: $isUpdateAvailable ? sprintf('Update %s is available.', $latestVersion) : 'You are already on the latest known version for this manifest.',
        );
    }
}
