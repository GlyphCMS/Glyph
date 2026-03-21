<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\adapters\http\Request;
use Glyph\adapters\http\Response;
use Glyph\adapters\security\CsrfTokenManager;
use Glyph\domain\auth\RoleCapabilities;
use Glyph\services\auth\AuthenticationManager;
use Glyph\services\system\MaintenanceManager;
use Glyph\services\system\MaintenanceSettings;
use Glyph\services\system\MigrationManager;
use Glyph\services\system\MigrationRunResult;
use Glyph\services\system\SystemBackupManager;
use Glyph\services\system\SystemInfoService;
use Glyph\services\system\UpdateApplyResult;
use Glyph\services\system\UpdateApplyService;
use Glyph\services\system\UpdateCheckResult;
use Glyph\services\system\UpdateManager;
use Glyph\services\system\UpdatePackageValidationResult;
use Glyph\services\system\UpdatePackageValidator;
use Glyph\services\system\UpdateService;
use Glyph\services\system\UpdateSettings;

final class SystemController
{
    /**
     * @param array<string, mixed> $maintenanceConfig
     * @param array<string, mixed> $updaterConfig
     */
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly CsrfTokenManager $csrfTokenManager,
        private readonly MaintenanceManager $maintenanceManager,
        private readonly SystemBackupManager $systemBackupManager,
        private readonly SystemInfoService $systemInfoService,
        private readonly UpdateManager $updateManager,
        private readonly UpdateService $updateService,
        private readonly UpdatePackageValidator $updatePackageValidator,
        private readonly UpdateApplyService $updateApplyService,
        private readonly MigrationManager $migrationManager,
        private readonly array $maintenanceConfig,
        private readonly array $updaterConfig,
    ) {
    }

    public function show(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        return $this->render(
            null,
            null,
            null,
            null,
            match (true) {
                $request->queryFlag('saved') => 'System settings saved successfully.',
                $request->queryFlag('applied') => 'Update package applied successfully.',
                $request->queryFlag('migrated') => 'Pending migrations ran successfully.',
                default => null,
            },
            null,
        );
    }

    public function saveMaintenance(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        if (!$this->csrfTokenManager->validate('system_maintenance', $request->postTrimmedString('_csrf_token'))) {
            return $this->render(null, null, null, null, null, 'Your session token is invalid. Please try again.', 400);
        }

        try {
            $this->maintenanceManager->save($this->maintenanceManager->inputFromPost($request->post()));
        } catch (\Throwable $throwable) {
            return $this->render(null, null, null, null, null, $throwable->getMessage(), 400);
        }

        return Response::redirect('/admin/system?saved=1');
    }

    public function saveUpdateSettings(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        if (!$this->csrfTokenManager->validate('system_update_settings', $request->postTrimmedString('_csrf_token'))) {
            return $this->render(null, null, null, null, null, 'Your session token is invalid. Please try again.', 400);
        }

        try {
            $this->updateManager->save($this->updateManager->inputFromPost($request->post()));
        } catch (\Throwable $throwable) {
            return $this->render(null, null, null, null, null, $throwable->getMessage(), 400);
        }

        return Response::redirect('/admin/system?saved=1');
    }

    public function runMigrations(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        if (!$this->csrfTokenManager->validate('system_migrations_run', $request->postTrimmedString('_csrf_token'))) {
            return $this->render(null, null, null, null, null, 'Your session token is invalid. Please try again.', 400);
        }

        try {
            $result = $this->migrationManager->runPending();
            $message = $result->wasAlreadyCurrent
                ? 'No pending migrations were found. Version state is already current.'
                : 'Pending migrations ran successfully.';

            return $this->render(null, null, null, $result, $message, null);
        } catch (\Throwable $throwable) {
            return $this->render(null, null, null, null, null, $throwable->getMessage(), 400);
        }
    }

    public function checkForUpdates(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        if (!$this->csrfTokenManager->validate('system_update_check', $request->postTrimmedString('_csrf_token'))) {
            return $this->render(null, null, null, null, null, 'Your session token is invalid. Please try again.', 400);
        }

        try {
            $settings = $this->currentUpdateSettings();
            $result = $this->updateService->check($settings->releaseManifestUrl, $settings->channel);
            return $this->render($result, null, null, null, null, null);
        } catch (\Throwable $throwable) {
            return $this->render(null, null, null, null, null, $throwable->getMessage(), 400);
        }
    }

    public function validateUpdatePackage(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        if (!$this->csrfTokenManager->validate('system_update_package', $request->postTrimmedString('_csrf_token'))) {
            return $this->render(null, null, null, null, null, 'Your session token is invalid. Please try again.', 400);
        }

        try {
            if (!isset($_FILES['update_package']) || !is_array($_FILES['update_package'])) {
                throw new \RuntimeException('Choose an update ZIP package to validate.');
            }

            $result = $this->updatePackageValidator->validate($_FILES['update_package']);
            return $this->render(null, $result, null, null, null, null);
        } catch (\Throwable $throwable) {
            return $this->render(null, null, null, null, null, $throwable->getMessage(), 400);
        }
    }

    public function applyUpdatePackage(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        if (!$this->csrfTokenManager->validate('system_update_apply', $request->postTrimmedString('_csrf_token'))) {
            return $this->render(null, null, null, null, null, 'Your session token is invalid. Please try again.', 400);
        }

        try {
            if (!isset($_FILES['update_package']) || !is_array($_FILES['update_package'])) {
                throw new \RuntimeException('Choose an update ZIP package to apply.');
            }

            $checksum = $request->postTrimmedString('expected_checksum_sha256');
            $result = $this->updateApplyService->apply($_FILES['update_package'], $checksum !== '' ? $checksum : null);

            return $this->render(null, null, $result, null, 'Update package applied successfully.', null);
        } catch (\Throwable $throwable) {
            return $this->render(null, null, null, null, null, $throwable->getMessage(), 400);
        }
    }

    public function backup(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        if (!$this->csrfTokenManager->validate('system_backup', $request->postTrimmedString('_csrf_token'))) {
            return $this->render(null, null, null, null, null, 'Your session token is invalid. Please try again.', 400);
        }

        try {
            $archivePath = $this->systemBackupManager->createBackupArchive();
            $contents = file_get_contents($archivePath);

            if (!is_string($contents)) {
                throw new \RuntimeException('Failed to read backup archive.');
            }

            return Response::download(basename($archivePath), $contents);
        } catch (\Throwable $throwable) {
            return $this->render(null, null, null, null, null, $throwable->getMessage(), 400);
        }
    }

    private function render(
        ?UpdateCheckResult $updateCheckResult,
        ?UpdatePackageValidationResult $packageValidationResult,
        ?UpdateApplyResult $updateApplyResult,
        ?MigrationRunResult $migrationRunResult,
        ?string $successMessage,
        ?string $errorMessage,
        int $statusCode = 200,
    ): Response {
        $renderer = new SystemPageRenderer();

        return Response::html($renderer->render(
            maintenance: $this->currentMaintenanceSettings(),
            updateSettings: $this->currentUpdateSettings(),
            versionState: $this->migrationManager->currentState(),
            systemInfo: $this->systemInfoService->collect(),
            maintenanceCsrfToken: $this->csrfTokenManager->token('system_maintenance'),
            backupCsrfToken: $this->csrfTokenManager->token('system_backup'),
            updateSettingsCsrfToken: $this->csrfTokenManager->token('system_update_settings'),
            updateCheckCsrfToken: $this->csrfTokenManager->token('system_update_check'),
            updatePackageCsrfToken: $this->csrfTokenManager->token('system_update_package'),
            updateApplyCsrfToken: $this->csrfTokenManager->token('system_update_apply'),
            migrationRunCsrfToken: $this->csrfTokenManager->token('system_migrations_run'),
            updateCheckResult: $updateCheckResult,
            packageValidationResult: $packageValidationResult,
            updateApplyResult: $updateApplyResult,
            migrationRunResult: $migrationRunResult,
            successMessage: $successMessage,
            errorMessage: $errorMessage,
        ), $statusCode);
    }

    private function currentMaintenanceSettings(): MaintenanceSettings
    {
        return new MaintenanceSettings(
            enabled: (bool) ($this->maintenanceConfig['enabled'] ?? false),
            message: is_string($this->maintenanceConfig['message'] ?? null) ? $this->maintenanceConfig['message'] : 'Glyph is currently undergoing maintenance. Please check back soon.',
        );
    }

    private function currentUpdateSettings(): UpdateSettings
    {
        return new UpdateSettings(
            channel: is_string($this->updaterConfig['channel'] ?? null) ? $this->updaterConfig['channel'] : 'stable',
            releaseManifestUrl: is_string($this->updaterConfig['release_manifest_url'] ?? null) ? $this->updaterConfig['release_manifest_url'] : '',
            allowPrerelease: (bool) ($this->updaterConfig['allow_prerelease'] ?? false),
        );
    }

    private function guard(): ?Response
    {
        $currentUser = $this->authenticationManager->currentUser();

        if ($currentUser === null) {
            return Response::redirect('/login');
        }

        \Glyph\ui\shared\DocumentRenderer::setAdminUserContext($currentUser->email, $currentUser->role, $currentUser->displayNameOrFallback());

        if (!$this->authenticationManager->hasCapability(RoleCapabilities::SETTINGS_MANAGE)) {
            return Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Forbidden</title></head><body><h1>403</h1><p>You do not have permission to access system tools.</p></body></html>', 403);
        }

        return null;
    }
}

