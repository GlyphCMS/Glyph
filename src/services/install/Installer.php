<?php

declare(strict_types=1);

namespace Glyph\services\install;

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\security\PasswordHasher;
use Glyph\adapters\security\SecretGenerator;
use Glyph\adapters\storage\PhpConfigWriter;
use Glyph\domain\shared\AppPaths;

final class Installer
{
    private const DEFAULT_ADMINISTRATOR_ROLE = 'administrator';

    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly PhpConfigWriter $configWriter,
        private readonly PasswordHasher $passwordHasher,
        private readonly SecretGenerator $secretGenerator,
        private readonly AppPaths $paths,
    ) {
    }

    public function install(InstallInput $input, bool $isApcuAvailable): InstallationResult
    {
        try {
            $this->createRequiredDirectories();

            $installedAt = gmdate('c');
            if (!is_string($installedAt) || $installedAt === '') {
                throw new \RuntimeException('Failed to determine installation timestamp.');
            }

            $administratorUserId = $this->secretGenerator->generateId();
            $secretKey = $this->secretGenerator->generateHex();

            $this->writeGeneratedConfig(
                siteUrl: rtrim($input->siteUrl, '/'),
                installedAt: $installedAt,
                ownerUserId: $administratorUserId,
                secretKey: $secretKey,
            );

            $this->writeSiteConfig($input->siteName);
            $this->writeCacheConfig($input->cacheDriver, $isApcuAvailable);
            $this->writeMailConfig($input->siteName, $input->adminEmail);

            $this->writeAdministratorUser(
                userId: $administratorUserId,
                installedAt: $installedAt,
                adminEmail: mb_strtolower($input->adminEmail),
                password: $input->password,
            );

            $this->filesystem->writeFile(
                $this->paths->get('data') . '/install.lock',
                $installedAt
            );

            return InstallationResult::success();
        } catch (\Throwable $throwable) {
            return InstallationResult::failure(
                sprintf('Installation failed: %s', $throwable->getMessage())
            );
        }
    }

    private function createRequiredDirectories(): void
    {
        $directories = [
            $this->paths->get('content'),
            $this->paths->get('content_posts'),
            $this->paths->get('content_pages'),
            $this->paths->get('data'),
            $this->paths->get('data_cache'),
            $this->paths->get('data_indexes'),
            $this->paths->get('data_media'),
            $this->paths->get('data_redirects'),
            $this->paths->get('data_sessions'),
            $this->paths->get('data_system'),
            $this->paths->get('data_users'),
            $this->paths->get('plugins'),
            $this->paths->get('storage'),
            $this->paths->get('storage_logs'),
            $this->paths->get('themes'),
            $this->paths->get('uploads'),
            $this->paths->get('uploads_images'),
        ];

        foreach ($directories as $directory) {
            $this->filesystem->ensureDirectoryExists($directory);
        }
    }

    private function writeGeneratedConfig(
        string $siteUrl,
        string $installedAt,
        string $ownerUserId,
        string $secretKey,
    ): void {
        $this->configWriter->write(
            $this->paths->get('data_system') . '/generated.php',
            [
                'is_installed' => true,
                'site_url' => $siteUrl,
                'installed_at' => $installedAt,
                'owner_user_id' => $ownerUserId,
                'secret_key' => $secretKey,
            ],
        );
    }

    private function writeSiteConfig(string $siteName): void
    {
        $this->configWriter->write(
            $this->paths->get('data_system') . '/site.php',
            [
                'site_name' => $siteName,
            ],
        );
    }

    private function writeCacheConfig(string $selectedDriver, bool $isApcuAvailable): void
    {
        $driver = $selectedDriver === 'apcu' && $isApcuAvailable ? 'apcu' : 'file';

        $this->configWriter->write(
            $this->paths->get('data_system') . '/cache.php',
            [
                'driver' => $driver,
                'apcu_enabled' => $driver === 'apcu',
            ],
        );
    }

    private function writeMailConfig(string $siteName, string $adminEmail): void
    {
        $this->configWriter->write(
            $this->paths->get('data_system') . '/mail.php',
            [
                'from_name' => $siteName,
                'from_email' => $adminEmail,
            ],
        );
    }

    private function writeAdministratorUser(
        string $userId,
        string $installedAt,
        string $adminEmail,
        string $password,
    ): void {
        $userData = [
            'id' => $userId,
            'email' => $adminEmail,
            'password_hash' => $this->passwordHasher->hash($password),
            'role' => self::DEFAULT_ADMINISTRATOR_ROLE,
            'is_active' => true,
            'created_at' => $installedAt,
            'updated_at' => $installedAt,
            'last_login_at' => null,
            'remember_tokens' => [],
        ];

        $userPath = $this->paths->get('data_users') . '/' . $userId . '.json';
        $json = json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($json) || $json === '') {
            throw new \RuntimeException('Failed to encode administrator user record.');
        }

        $this->filesystem->writeFile($userPath, $json . PHP_EOL);
    }
}
