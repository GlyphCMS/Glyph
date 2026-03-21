<?php

declare(strict_types=1);

namespace Glyph\domain\shared;

final class AppPaths
{
    /**
     * @param array<string, string> $paths
     */
    public function __construct(
        private readonly string $rootPath,
        private readonly array $paths,
    ) {
    }

    public function root(): string
    {
        return $this->rootPath;
    }

    public function get(string $key): string
    {
        if (!array_key_exists($key, $this->paths)) {
            throw new \RuntimeException(sprintf('Unknown path key: %s', $key));
        }

        return $this->paths[$key];
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->paths;
    }
}