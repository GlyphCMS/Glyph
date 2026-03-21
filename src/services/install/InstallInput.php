<?php

declare(strict_types=1);

namespace Glyph\services\install;

final class InstallInput
{
    public function __construct(
        public readonly string $siteName,
        public readonly string $siteUrl,
        public readonly string $adminEmail,
        public readonly string $password,
        public readonly string $passwordConfirmation,
        public readonly string $cacheDriver,
    ) {
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function fromPost(array $post): self
    {
        return new self(
            self::stringValue($post['site_name'] ?? ''),
            self::stringValue($post['site_url'] ?? ''),
            self::stringValue($post['admin_email'] ?? ''),
            self::stringValue($post['password'] ?? ''),
            self::stringValue($post['password_confirmation'] ?? ''),
            self::stringValue($post['cache_driver'] ?? 'file'),
        );
    }

    private static function stringValue(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }
}
