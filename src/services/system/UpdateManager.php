<?php

declare(strict_types=1);

namespace Glyph\services\system;

use Glyph\adapters\storage\PhpConfigWriter;

final class UpdateManager
{
    public function __construct(
        private readonly PhpConfigWriter $configWriter,
        private readonly string $systemPath,
    ) {
    }

    /**
     * @param array<string, mixed> $post
     */
    public function inputFromPost(array $post): UpdateSettings
    {
        return new UpdateSettings(
            channel: isset($post['channel']) && is_string($post['channel']) ? trim($post['channel']) : 'stable',
            releaseManifestUrl: isset($post['release_manifest_url']) && is_string($post['release_manifest_url']) ? trim($post['release_manifest_url']) : '',
            allowPrerelease: isset($post['allow_prerelease']) && $post['allow_prerelease'] === '1',
        );
    }

    public function save(UpdateSettings $settings): void
    {
        if (!in_array($settings->channel, ['stable', 'beta', 'nightly'], true)) {
            throw new \RuntimeException('Update channel is invalid.');
        }

        if ($settings->releaseManifestUrl !== '' && filter_var($settings->releaseManifestUrl, FILTER_VALIDATE_URL) === false) {
            throw new \RuntimeException('Release manifest URL must be a valid absolute URL.');
        }

        $this->configWriter->write($this->systemPath . '/updater.php', [
            'channel' => $settings->channel,
            'release_manifest_url' => $settings->releaseManifestUrl,
            'allow_prerelease' => $settings->allowPrerelease,
        ]);
    }
}
