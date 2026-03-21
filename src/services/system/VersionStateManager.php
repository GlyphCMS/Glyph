<?php

declare(strict_types=1);

namespace Glyph\services\system;

use Glyph\adapters\storage\PhpConfigWriter;

final class VersionStateManager
{
    public function __construct(
        private readonly PhpConfigWriter $configWriter,
        private readonly string $statePath,
    ) {
    }

    public function load(string $defaultAppVersion, string $defaultSchemaVersion): VersionState
    {
        if (!is_file($this->statePath)) {
            return new VersionState(
                appVersion: $defaultAppVersion,
                schemaVersion: $defaultSchemaVersion,
                lastMigratedAt: null,
                appliedMigrations: [],
            );
        }

        $loaded = require $this->statePath;

        if (!is_array($loaded)) {
            throw new \RuntimeException('Invalid version state file.');
        }

        return VersionState::fromArray($loaded);
    }

    public function save(VersionState $state): void
    {
        $this->configWriter->write($this->statePath, $state->toArray());
    }
}
