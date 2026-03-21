<?php

declare(strict_types=1);

namespace Glyph\domain\auth;

final class RoleCapabilities
{
    public const ADMIN_ACCESS = 'admin.access';
    public const CONTENT_READ = 'content.read';
    public const CONTENT_CREATE = 'content.create';
    public const CONTENT_UPDATE = 'content.update';
    public const CATEGORY_MANAGE = 'category.manage';
    public const CONTENT_DELETE = 'content.delete';
    public const CONTENT_PUBLISH = 'content.publish';
    public const MEDIA_UPLOAD = 'media.upload';
    public const USER_MANAGE = 'user.manage';
    public const SETTINGS_MANAGE = 'settings.manage';
    public const THEME_MANAGE = 'theme.manage';
    public const PLUGIN_MANAGE = 'plugin.manage';
    public const SITE_OWN = 'site.own';

    /**
     * @return list<string>
     */
    public static function capabilitiesForRole(string $role): array
    {
        return match ($role) {
            'reader' => [],
            'contributor' => [
                self::ADMIN_ACCESS,
                self::CONTENT_READ,
                self::CONTENT_CREATE,
                self::CONTENT_UPDATE,
                self::CONTENT_DELETE,
                self::MEDIA_UPLOAD,
            ],
            'author' => [
                self::ADMIN_ACCESS,
                self::CONTENT_READ,
                self::CONTENT_CREATE,
                self::CONTENT_UPDATE,
                self::CONTENT_DELETE,
                self::CONTENT_PUBLISH,
                self::MEDIA_UPLOAD,
            ],
            'editor' => [
                self::ADMIN_ACCESS,
                self::CONTENT_READ,
                self::CONTENT_CREATE,
                self::CONTENT_UPDATE,
                self::CONTENT_DELETE,
                self::CONTENT_PUBLISH,
                self::CATEGORY_MANAGE,
                self::MEDIA_UPLOAD,
                self::USER_MANAGE,
                self::THEME_MANAGE,
                self::PLUGIN_MANAGE,
            ],
            'administrator', 'owner' => [
                self::ADMIN_ACCESS,
                self::CONTENT_READ,
                self::CONTENT_CREATE,
                self::CONTENT_UPDATE,
                self::CONTENT_DELETE,
                self::CONTENT_PUBLISH,
                self::CATEGORY_MANAGE,
                self::MEDIA_UPLOAD,
                self::USER_MANAGE,
                self::SETTINGS_MANAGE,
                self::THEME_MANAGE,
                self::PLUGIN_MANAGE,
                self::SITE_OWN,
            ],
            default => [],
        };
    }

    public static function hasCapability(string $role, string $capability): bool
    {
        return in_array($capability, self::capabilitiesForRole($role), true);
    }
}

