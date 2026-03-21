<?php

declare(strict_types=1);

namespace Glyph\services\install;

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\domain\shared\AppPaths;

final class EnvironmentChecker
{
    private const MINIMUM_PHP_VERSION = '8.3.0';

    /**
     * @var list<string>
     */
    private const REQUIRED_EXTENSIONS = [
        'json',
        'mbstring',
        'fileinfo',
        'openssl',
    ];

    /**
     * @var list<string>
     */
    private const OPTIONAL_EXTENSIONS = [
        'apcu',
    ];

    /**
     * @var list<string>
     */
    private const WRITABLE_ROOT_PATH_KEYS = [
        'content',
        'data',
        'storage',
        'uploads',
    ];

    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly AppPaths $paths,
    ) {
    }

    public function check(): EnvironmentCheckResult
    {
        $errors = [];
        $warnings = [];

        if (version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '<')) {
            $errors[] = sprintf(
                'Glyph requires PHP %s or newer. Current version: %s.',
                self::MINIMUM_PHP_VERSION,
                PHP_VERSION
            );
        }

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            if (!extension_loaded($extension)) {
                $errors[] = sprintf('Missing required PHP extension: %s.', $extension);
            }
        }

        foreach (self::OPTIONAL_EXTENSIONS as $extension) {
            if (!extension_loaded($extension)) {
                $warnings[] = sprintf('Optional PHP extension not enabled: %s.', $extension);
            }
        }

        foreach (self::WRITABLE_ROOT_PATH_KEYS as $pathKey) {
            $path = $this->paths->get($pathKey);
            $parentDirectory = dirname($path);

            if (!$this->filesystem->isDirectory($parentDirectory)) {
                $errors[] = sprintf(
                    'Required parent directory does not exist for "%s": %s.',
                    $pathKey,
                    $parentDirectory
                );
                continue;
            }

            if (!$this->filesystem->isWritable($parentDirectory)) {
                $errors[] = sprintf(
                    'Required parent directory is not writable for "%s": %s.',
                    $pathKey,
                    $parentDirectory
                );
            }
        }

        return new EnvironmentCheckResult($errors, $warnings);
    }

    public function isApcuAvailable(): bool
    {
        if (!extension_loaded('apcu')) {
            return false;
        }

        $apcuEnabled = ini_get('apc.enabled');

        return $apcuEnabled !== false && $apcuEnabled !== '' && $apcuEnabled !== '0';
    }
}
