<?php

declare(strict_types=1);

namespace Glyph\services\system;

use Glyph\adapters\filesystem\LocalFilesystem;

final class UpdatePreflightChecker
{
    /**
     * @param array<string, string> $paths
     */
    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly array $paths,
    ) {
    }

    /**
     * @return array<string, array{status:string,message:string}>
     */
    public function check(?string $minPhpVersion = null): array
    {
        $results = [];
        $results['zip_extension'] = class_exists('ZipArchive')
            ? ['status' => 'ok', 'message' => 'ZIP support is available.']
            : ['status' => 'error', 'message' => 'ZIP support is required.'];

        if ($minPhpVersion === null || $minPhpVersion === '') {
            $results['php_version'] = ['status' => 'ok', 'message' => 'No minimum PHP version was declared by the manifest.'];
        } else {
            $results['php_version'] = version_compare(PHP_VERSION, $minPhpVersion, '>=')
                ? ['status' => 'ok', 'message' => sprintf('PHP %s satisfies %s.', PHP_VERSION, $minPhpVersion)]
                : ['status' => 'error', 'message' => sprintf('PHP %s does not satisfy %s.', PHP_VERSION, $minPhpVersion)];
        }

        foreach ($this->paths as $name => $path) {
            $exists = file_exists($path);
            $writable = $exists ? $this->filesystem->isWritable($path) : $this->filesystem->isWritable(dirname($path));
            $results['path_' . $name] = $writable
                ? ['status' => 'ok', 'message' => sprintf('%s is writable.', $name)]
                : ['status' => 'error', 'message' => sprintf('%s is not writable.', $name)];
        }

        return $results;
    }
}
