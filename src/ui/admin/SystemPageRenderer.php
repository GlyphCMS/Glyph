<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\services\system\MaintenanceSettings;
use Glyph\services\system\MigrationRunResult;
use Glyph\services\system\UpdateApplyResult;
use Glyph\services\system\UpdateCheckResult;
use Glyph\services\system\UpdatePackageValidationResult;
use Glyph\services\system\UpdateSettings;
use Glyph\services\system\VersionState;
use Glyph\ui\shared\DocumentRenderer;

final class SystemPageRenderer
{
    /**
     * @param array<string, mixed> $systemInfo
     */
    public function render(
        MaintenanceSettings $maintenance,
        UpdateSettings $updateSettings,
        VersionState $versionState,
        array $systemInfo,
        string $maintenanceCsrfToken,
        string $backupCsrfToken,
        string $updateSettingsCsrfToken,
        string $updateCheckCsrfToken,
        string $updatePackageCsrfToken,
        string $updateApplyCsrfToken,
        string $migrationRunCsrfToken,
        ?UpdateCheckResult $updateCheckResult,
        ?UpdatePackageValidationResult $packageValidationResult,
        ?UpdateApplyResult $updateApplyResult,
        ?MigrationRunResult $migrationRunResult,
        ?string $successMessage,
        ?string $errorMessage,
    ): string {
        $document = new DocumentRenderer();

        $content = '<main class="page-shell stack">';
        $content .= '<section class="hero"><div class="toolbar"><div><p class="hero__eyebrow">Glyph System</p><h1 class="hero__title">Maintenance, diagnostics, and updates</h1><p class="hero__text">System tools for maintenance mode, backups, diagnostics, and safe staged updates.</p></div><a class="button button-secondary" href="/admin">Back to dashboard</a></div></section>';

        if ($successMessage !== null && $successMessage !== '') {
            $content .= '<div class="notice notice--success"><p><strong>' . $document->escape($successMessage) . '</strong></p></div>';
        }
        if ($errorMessage !== null && $errorMessage !== '') {
            $content .= '<div class="notice notice--error"><p><strong>' . $document->escape($errorMessage) . '</strong></p></div>';
        }

        $content .= '<section class="panel stack"><div><p class="kicker">Maintenance</p><h2 class="page-title">Public site maintenance mode</h2></div>';
        $content .= '<form method="post" action="/admin/system/maintenance" class="form-grid">';
        $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($maintenanceCsrfToken) . '">';
        $content .= '<div class="field"><label for="enabled">Status</label><select id="enabled" name="enabled"><option value="0"' . (!$maintenance->enabled ? ' selected' : '') . '>Disabled</option><option value="1"' . ($maintenance->enabled ? ' selected' : '') . '>Enabled</option></select></div>';
        $content .= '<div class="field field--full"><label for="message">Maintenance Message</label><textarea id="message" name="message">' . $document->escape($maintenance->message) . '</textarea></div>';
        $content .= '<div class="field cluster"><button type="submit">Save Maintenance Settings</button></div></form></section>';

        $content .= '<section class="panel stack"><div><p class="kicker">Versioning</p><h2 class="page-title">App version and migrations</h2><p class="page-subtitle">Track runtime version state and run pending migrations after releases or manual updates.</p></div>';
        $content .= '<div class="helper-card"><p class="helper-card__title">Current state</p><p class="muted">App version: <strong>' . $document->escape($versionState->appVersion) . '</strong> | Schema version: <strong>' . $document->escape($versionState->schemaVersion) . '</strong></p><p class="muted">Last migrated: ' . $document->escape($versionState->lastMigratedAt ?? 'Never') . '</p><p class="muted">Applied migrations: ' . $document->escape((string) count($versionState->appliedMigrations)) . '</p></div>';
        $content .= '<form method="post" action="/admin/system/migrations/run" class="cluster"><input type="hidden" name="_csrf_token" value="' . $document->escape($migrationRunCsrfToken) . '"><button type="submit">Run Pending Migrations</button></form></section>';
        $content .= '<section class="panel stack"><div><p class="kicker">Backups</p><h2 class="page-title">Export a system backup</h2><p class="page-subtitle">Create a ZIP snapshot of content, data, uploads, themes, plugins, and config for recovery or migration.</p></div>';
        $content .= '<form method="post" action="/admin/system/backup" class="cluster"><input type="hidden" name="_csrf_token" value="' . $document->escape($backupCsrfToken) . '"><button type="submit">Download Backup ZIP</button></form></section>';

        $content .= '<section class="panel stack"><div><p class="kicker">Updates</p><h2 class="page-title">Updater foundation</h2><p class="page-subtitle">Configure a release manifest URL, run preflight checks, validate update ZIPs, and apply staged update packages while preserving content, data, and uploads.</p></div>';
        $content .= '<form method="post" action="/admin/system/update-settings" class="form-grid form-grid--two">';
        $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($updateSettingsCsrfToken) . '">';
        $content .= '<div class="field"><label for="channel">Channel</label><select id="channel" name="channel">';
        foreach (['stable' => 'Stable', 'beta' => 'Beta', 'nightly' => 'Nightly'] as $value => $label) {
            $content .= '<option value="' . $document->escape($value) . '"' . ($updateSettings->channel === $value ? ' selected' : '') . '>' . $document->escape($label) . '</option>';
        }
        $content .= '</select></div>';
        $content .= '<div class="field"><label for="release_manifest_url">Release Manifest URL</label><input id="release_manifest_url" name="release_manifest_url" type="text" value="' . $document->escape($updateSettings->releaseManifestUrl) . '"></div>';
        $content .= '<div class="field"><label for="allow_prerelease">Allow prerelease</label><select id="allow_prerelease" name="allow_prerelease"><option value="0"' . (!$updateSettings->allowPrerelease ? ' selected' : '') . '>No</option><option value="1"' . ($updateSettings->allowPrerelease ? ' selected' : '') . '>Yes</option></select></div>';
        $content .= '<div class="field cluster"><button type="submit">Save Update Settings</button></div></form>';

        $content .= '<form method="post" action="/admin/system/update-check" class="cluster"><input type="hidden" name="_csrf_token" value="' . $document->escape($updateCheckCsrfToken) . '"><button type="submit">Check for Updates</button></form>';

        $content .= '<form method="post" action="/admin/system/update-package-validate" enctype="multipart/form-data" class="form-grid form-grid--two">';
        $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($updatePackageCsrfToken) . '">';
        $content .= '<div class="field"><label for="update_package_validate">Update ZIP Package</label><input id="update_package_validate" name="update_package" type="file" accept=".zip,application/zip"></div>';
        $content .= '<div class="field cluster"><button type="submit">Validate Update Package</button></div></form>';

        $content .= '<form method="post" action="/admin/system/update-package-apply" enctype="multipart/form-data" class="form-grid form-grid--two">';
        $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($updateApplyCsrfToken) . '">';
        $content .= '<div class="field"><label for="update_package_apply">Apply Update ZIP Package</label><input id="update_package_apply" name="update_package" type="file" accept=".zip,application/zip"></div>';
        $content .= '<div class="field"><label for="expected_checksum_sha256">Expected SHA-256 Checksum</label><input id="expected_checksum_sha256" name="expected_checksum_sha256" type="text" value="' . $document->escape($updateCheckResult?->checksumSha256 ?? '') . '"></div>';
        $content .= '<div class="field field--full"><small class="field-help">Applying an update creates a backup first, preserves <code>content/</code>, <code>data/</code>, and <code>uploads/</code>, and only overwrites shipped code paths from the package. It does not delete old files that are not present in the package yet.</small></div>';
        $content .= '<div class="field cluster"><button type="submit">Apply Update Package</button></div></form>';

        if ($migrationRunResult !== null) {
            $content .= '<div class="helper-card"><p class="helper-card__title">Migration run result</p><p class="muted">App version: <strong>' . $document->escape($migrationRunResult->appVersion) . '</strong> · Schema version: <strong>' . $document->escape($migrationRunResult->schemaVersion) . '</strong></p><p class="muted">' . $document->escape($migrationRunResult->wasAlreadyCurrent ? 'No pending migrations were found.' : 'Applied migrations: ' . implode(', ', $migrationRunResult->appliedMigrationIds)) . '</p></div>';
        }

        if ($updateCheckResult !== null) {
            $content .= '<div class="helper-card"><p class="helper-card__title">Update check result</p><p class="muted">Current: <strong>' . $document->escape($updateCheckResult->currentVersion) . '</strong> · Latest: <strong>' . $document->escape($updateCheckResult->latestVersion) . '</strong></p><p class="muted">' . $document->escape($updateCheckResult->message ?? '') . '</p>';
            if ($updateCheckResult->packageUrl) {
                $content .= '<p class="muted">Package URL: ' . $document->escape($updateCheckResult->packageUrl) . '</p>';
            }
            if ($updateCheckResult->checksumSha256) {
                $content .= '<p class="muted">SHA-256: <code>' . $document->escape($updateCheckResult->checksumSha256) . '</code></p>';
            }
            $content .= '<div class="table-card"><div class="table-wrap"><table class="table"><thead><tr><th>Preflight Check</th><th>Status</th><th>Message</th></tr></thead><tbody>';
            foreach ($updateCheckResult->preflight as $label => $result) {
                $content .= '<tr><td>' . $document->escape($label) . '</td><td>' . $document->escape($result['status']) . '</td><td>' . $document->escape($result['message']) . '</td></tr>';
            }
            $content .= '</tbody></table></div></div></div>';
        }

        if ($packageValidationResult !== null) {
            $content .= '<div class="helper-card"><p class="helper-card__title">Update package validation</p><p class="muted">Package: <strong>' . $document->escape($packageValidationResult->packageName) . '</strong></p><p class="muted">Valid Glyph package: <strong>' . $document->escape($packageValidationResult->isValid ? 'Yes' : 'No') . '</strong></p>';
            if ($packageValidationResult->glyphRoot !== null) {
                $content .= '<p class="muted">Detected Glyph root: ' . $document->escape($packageValidationResult->glyphRoot) . '</p>';
            }
            if ($packageValidationResult->detectedTopLevelEntries !== []) {
                $content .= '<p class="muted">Top-level entries: ' . $document->escape(implode(', ', $packageValidationResult->detectedTopLevelEntries)) . '</p>';
            }
            if ($packageValidationResult->requiredEntriesFound !== []) {
                $content .= '<p class="muted">Required entries found: ' . $document->escape(implode(', ', $packageValidationResult->requiredEntriesFound)) . '</p>';
            }
            if ($packageValidationResult->warnings !== []) {
                $content .= '<ul class="redirect-list">';
                foreach ($packageValidationResult->warnings as $warning) {
                    $content .= '<li>' . $document->escape($warning) . '</li>';
                }
                $content .= '</ul>';
            }
            $content .= '</div>';
        }

        if ($updateApplyResult !== null) {
            $content .= '<div class="helper-card"><p class="helper-card__title">Update apply result</p>';
            $content .= '<p class="muted">Files applied: <strong>' . $document->escape((string) $updateApplyResult->appliedFileCount) . '</strong> · Directories ensured: <strong>' . $document->escape((string) $updateApplyResult->ensuredDirectoryCount) . '</strong></p>';
            $content .= '<p class="muted">Backup archive: <strong>' . $document->escape(basename($updateApplyResult->backupArchivePath)) . '</strong></p>';
            if ($updateApplyResult->detectedVersion !== null) {
                $content .= '<p class="muted">Detected package version: <strong>' . $document->escape($updateApplyResult->detectedVersion) . '</strong></p>';
            }
            if ($updateApplyResult->packageSha256 !== null) {
                $content .= '<p class="muted">Applied package SHA-256: <code>' . $document->escape($updateApplyResult->packageSha256) . '</code></p>';
            }
            if ($updateApplyResult->warnings !== []) {
                $content .= '<ul class="redirect-list">';
                foreach ($updateApplyResult->warnings as $warning) {
                    $content .= '<li>' . $document->escape($warning) . '</li>';
                }
                $content .= '</ul>';
            }
            $content .= '</div>';
        }

        $content .= '</section>';

                $content .= '<section class="panel stack"><div><p class="kicker">Security</p><h2 class="page-title">Security posture</h2><p class="page-subtitle">Quick checks for common production hardening issues.</p></div><div class="table-card"><div class="table-wrap"><table class="table"><thead><tr><th>Check</th><th>Status</th></tr></thead><tbody>';
        foreach (($systemInfo['security'] ?? []) as $name => $passed) {
            $content .= '<tr><td>' . $document->escape(str_replace('_', ' ', ucfirst((string) $name))) . '</td><td><span class="badge badge--status-' . ($passed ? 'published' : 'draft') . '">' . ($passed ? 'Pass' : 'Review') . '</span></td></tr>';
        }
        $content .= '</tbody></table></div></div></section>';

$content .= '<section class="panel stack"><div><p class="kicker">Diagnostics</p><h2 class="page-title">System info</h2><p class="page-subtitle">Environment, storage, extension, and path checks useful for support and update preflight.</p></div>';
        $content .= '<div class="grid grid--three">';
        $content .= $this->infoCard('Glyph Version', (string) ($systemInfo['version'] ?? '0.1.0-dev'), $document);
        $content .= $this->infoCard('PHP Version', (string) ($systemInfo['php_version'] ?? PHP_VERSION), $document);
        $content .= $this->infoCard('Environment', (string) ($systemInfo['environment'] ?? 'production'), $document);
        $content .= $this->infoCard('Site Name', (string) ($systemInfo['site_name'] ?? 'Glyph'), $document);
        $content .= $this->infoCard('Active Theme', (string) ($systemInfo['active_theme'] ?? 'default'), $document);
        $content .= $this->infoCard('Timezone', (string) ($systemInfo['timezone'] ?? 'UTC'), $document);
        $content .= '</div>';

        $counts = $systemInfo['content_counts'] ?? [];
        $content .= '<div class="table-card"><div class="table-wrap"><table class="table"><thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>';
        $rows = [
            'Posts' => (string) ($counts['posts'] ?? 0),
            'Pages' => (string) ($counts['pages'] ?? 0),
            'Published Content' => (string) ($counts['published'] ?? 0),
            'Draft Content' => (string) ($counts['drafts'] ?? 0),
            'Users' => (string) ($systemInfo['user_count'] ?? 0),
            'Active Users' => (string) ($systemInfo['active_user_count'] ?? 0),
            'Themes' => (string) ($systemInfo['theme_count'] ?? 0),
            'Plugins' => (string) ($systemInfo['plugin_count'] ?? 0),
            'Enabled Plugins' => (string) ($systemInfo['enabled_plugin_count'] ?? 0),
            'PHP SAPI' => (string) ($systemInfo['sapi'] ?? 'unknown'),
        ];
        foreach ($rows as $label => $value) {
            $content .= '<tr><td>' . $document->escape($label) . '</td><td>' . $document->escape($value) . '</td></tr>';
        }
        $content .= '</tbody></table></div></div>';

        $extensions = $systemInfo['extensions'] ?? [];
        $content .= '<div class="grid grid--three">';
        foreach ($extensions as $name => $enabled) {
            $content .= '<article class="panel stack"><p class="theme-card__title">' . $document->escape(strtoupper((string) $name)) . '</p><p class="muted">' . $document->escape($enabled ? 'Available' : 'Unavailable') . '</p></article>';
        }
        $content .= '</div>';

        $content .= '<div class="table-card"><div class="table-wrap"><table class="table"><thead><tr><th>Path</th><th>Exists</th><th>Writable</th></tr></thead><tbody>';
        foreach (($systemInfo['paths'] ?? []) as $name => $info) {
            $content .= '<tr><td><strong>' . $document->escape((string) $name) . '</strong><div class="table-meta">' . $document->escape((string) ($info['path'] ?? '')) . '</div></td><td>' . $document->escape(($info['exists'] ?? false) ? 'Yes' : 'No') . '</td><td>' . $document->escape(($info['writable'] ?? false) ? 'Yes' : 'No') . '</td></tr>';
        }
        $content .= '</tbody></table></div></div></section></main>';

        return $document->render('Glyph System', $content, 'System maintenance, diagnostics, and updater tools.', 'theme-admin');
    }

    private function infoCard(string $label, string $value, DocumentRenderer $document): string
    {
        return '<article class="panel stack"><p class="stat-label">' . $document->escape($label) . '</p><p class="stat-value">' . $document->escape($value) . '</p></article>';
    }
}
