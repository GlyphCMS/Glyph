<?php

declare(strict_types=1);

namespace Glyph\services\install;

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\domain\shared\AppPaths;

final class InstallState
{
    public function __construct(
        private readonly array $generatedConfig,
        private readonly AppPaths $paths,
        private readonly LocalFilesystem $filesystem,
    ) {
    }

    public function isInstalled(): bool
    {
        $flag = $this->generatedConfig['is_installed'] ?? false;
        $secretKey = $this->generatedConfig['secret_key'] ?? '';

        if (!is_bool($flag) || !is_string($secretKey)) {
            return false;
        }

        $lockFile = $this->paths->get('data') . '/install.lock';

        return $flag === true
            && $secretKey !== ''
            && $this->filesystem->isFile($lockFile);
    }
}