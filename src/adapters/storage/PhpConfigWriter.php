<?php

declare(strict_types=1);

namespace Glyph\adapters\storage;

use Glyph\adapters\filesystem\LocalFilesystem;

final class PhpConfigWriter
{
    public function __construct(
        private readonly LocalFilesystem $filesystem,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public function write(string $path, array $config): void
    {
        $export = var_export($config, true);

        if (!is_string($export) || $export === '') {
            throw new \RuntimeException('Failed to export configuration.');
        }

        $contents = <<<PHP
<?php

declare(strict_types=1);

return {$export};

PHP;

        $this->filesystem->writeFile($path, $contents);
        clearstatcache(true, $path);

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }
}
