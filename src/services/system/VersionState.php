<?php

declare(strict_types=1);

namespace Glyph\services\system;

final class VersionState
{
    /**
     * @param list<string> $appliedMigrations
     */
    public function __construct(
        public readonly string $appVersion,
        public readonly string $schemaVersion,
        public readonly ?string $lastMigratedAt,
        public readonly array $appliedMigrations,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $appVersion = isset($data['app_version']) && is_string($data['app_version']) && $data['app_version'] !== ''
            ? $data['app_version']
            : '0.0.0';
        $schemaVersion = isset($data['schema_version']) && is_string($data['schema_version']) && $data['schema_version'] !== ''
            ? $data['schema_version']
            : '0.0.0';
        $lastMigratedAt = isset($data['last_migrated_at']) && is_string($data['last_migrated_at']) && $data['last_migrated_at'] !== ''
            ? $data['last_migrated_at']
            : null;

        $appliedMigrations = [];
        $rawApplied = $data['applied_migrations'] ?? [];
        if (is_array($rawApplied)) {
            foreach ($rawApplied as $migration) {
                if (is_string($migration) && $migration !== '') {
                    $appliedMigrations[] = $migration;
                }
            }
        }

        return new self(
            appVersion: $appVersion,
            schemaVersion: $schemaVersion,
            lastMigratedAt: $lastMigratedAt,
            appliedMigrations: array_values(array_unique($appliedMigrations)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'app_version' => $this->appVersion,
            'schema_version' => $this->schemaVersion,
            'last_migrated_at' => $this->lastMigratedAt,
            'applied_migrations' => $this->appliedMigrations,
        ];
    }

    /**
     * @param list<string> $appliedMigrations
     */
    public function withState(string $appVersion, string $schemaVersion, ?string $lastMigratedAt, array $appliedMigrations): self
    {
        return new self(
            appVersion: $appVersion,
            schemaVersion: $schemaVersion,
            lastMigratedAt: $lastMigratedAt,
            appliedMigrations: $appliedMigrations,
        );
    }
}
