<?php

declare(strict_types=1);

namespace Glyph\services\system;

final class UpdateManifestFetcher
{
    /**
     * @return array<string, mixed>
     */
    public function fetch(string $manifestUrl): array
    {
        if ($manifestUrl === '') {
            throw new \RuntimeException('Configure a release manifest URL before checking for updates.');
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'ignore_errors' => true,
                'user_agent' => 'Glyph Updater',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $contents = @file_get_contents($manifestUrl, false, $context);
        if (!is_string($contents) || trim($contents) === '') {
            throw new \RuntimeException('Failed to fetch the release manifest.');
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('The release manifest is not valid JSON.');
        }

        $latest = $decoded['latest'] ?? null;
        if (!is_array($latest)) {
            throw new \RuntimeException('The release manifest must contain a latest release object.');
        }

        $version = $latest['version'] ?? null;
        if (!is_string($version) || trim($version) === '') {
            throw new \RuntimeException('The release manifest latest.version field is required.');
        }

        return $decoded;
    }
}
