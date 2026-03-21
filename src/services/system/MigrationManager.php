<?php

declare(strict_types=1);

namespace Glyph\services\system;

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\storage\PhpConfigWriter;
use Glyph\services\system\migrations\EnsureRuntimeFilesMigration;
use Glyph\services\system\migrations\NormalizeVersionStateMigration;
use Glyph\services\system\migrations\RenameUserRolesMigration;

final class MigrationManager
{
    /**
     * @param array<string, mixed> $appConfig
     * @param array<string, mixed> $versioningConfig
     * @param array<string, string> $paths
     */
    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly PhpConfigWriter $configWriter,
        private readonly VersionStateManager $versionStateManager,
        private readonly array $appConfig,
        private readonly array $versioningConfig,
        private readonly array $paths,
    ) {
    }

    public function currentState(): VersionState
    {
        return $this->versionStateManager->load($this->currentAppVersion(), $this->targetSchemaVersion());
    }

    public function autoRunEnabled(): bool
    {
        return (bool) ($this->versioningConfig['auto_run_migrations'] ?? true);
    }

    public function runPending(): MigrationRunResult
    {
        $state = $this->currentState();
        $messages = [];
        $applied = [];
        $context = new MigrationContext($this->filesystem, $this->configWriter, $this->paths);

        foreach ($this->migrations() as $migration) {
            if (in_array($migration->id(), $state->appliedMigrations, true)) {
                continue;
            }

            $migration->apply($context);
            $applied[] = $migration->id();
            $messages[] = $migration->description();
            $state = $state->withState(
                appVersion: $this->currentAppVersion(),
                schemaVersion: $this->targetSchemaVersion(),
                lastMigratedAt: $this->timestamp(),
                appliedMigrations: array_values(array_unique([...$state->appliedMigrations, $migration->id()])),
            );
            $this->versionStateManager->save($state);
        }

        if ($applied === [] && ($state->appVersion !== $this->currentAppVersion() || $state->schemaVersion !== $this->targetSchemaVersion())) {
            $state = $state->withState(
                appVersion: $this->currentAppVersion(),
                schemaVersion: $this->targetSchemaVersion(),
                lastMigratedAt: $state->lastMigratedAt,
                appliedMigrations: $state->appliedMigrations,
            );
            $this->versionStateManager->save($state);
        }

        return new MigrationRunResult(
            appVersion: $this->currentAppVersion(),
            schemaVersion: $this->targetSchemaVersion(),
            appliedMigrationIds: $applied,
            messages: $messages,
            wasAlreadyCurrent: $applied === [],
        );
    }

    /**
     * @return list<MigrationInterface>
     */
    private function migrations(): array
    {
        return [
            new NormalizeVersionStateMigration(),
            new EnsureRuntimeFilesMigration(),
            new RenameUserRolesMigration(),
        ];
    }

    private function currentAppVersion(): string
    {
        $value = $this->appConfig['version'] ?? '0.0.0';

        return is_string($value) && $value !== '' ? $value : '0.0.0';
    }

    private function targetSchemaVersion(): string
    {
        $value = $this->versioningConfig['schema_version'] ?? '1.0.0';

        return is_string($value) && $value !== '' ? $value : '1.0.0';
    }

    private function timestamp(): string
    {
        $timestamp = gmdate('c');

        if (!is_string($timestamp) || $timestamp === '') {
            throw new \RuntimeException('Failed to determine migration timestamp.');
        }

        return $timestamp;
    }
}
