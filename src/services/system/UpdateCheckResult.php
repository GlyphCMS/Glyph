<?php

declare(strict_types=1);

namespace Glyph\services\system;

final class UpdateCheckResult
{
    /**
     * @param array<string, array{status:string,message:string}> $preflight
     */
    public function __construct(
        public readonly bool $isUpdateAvailable,
        public readonly string $currentVersion,
        public readonly string $latestVersion,
        public readonly string $channel,
        public readonly ?string $publishedAt,
        public readonly ?string $packageUrl,
        public readonly ?string $notesUrl,
        public readonly ?string $checksumSha256,
        public readonly array $preflight,
        public readonly ?string $message,
    ) {
    }
}
