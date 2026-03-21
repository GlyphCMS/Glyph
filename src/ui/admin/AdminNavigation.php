<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\domain\auth\RoleCapabilities;

final class AdminNavigation
{
    /**
     * @return list<array{description: string, href: string, label: string}>
     */
    public static function quickLinksForRole(string $role): array
    {
        return array_map(
            static fn (array $link): array => [
                'description' => $link['description'],
                'href' => $link['href'],
                'label' => $link['label'],
            ],
            self::visibleLinksForRole($role),
        );
    }

    /**
     * @return list<array{label: string, links: list<array{href: string, label: string, svg_paths: string}>}>
     */
    public static function sidebarSectionsForRole(string $role): array
    {
        $sections = [];

        foreach (self::visibleLinksForRole($role) as $link) {
            $sectionLabel = $link['section'];
            if (!isset($sections[$sectionLabel])) {
                $sections[$sectionLabel] = [];
            }

            $sections[$sectionLabel][] = [
                'href' => $link['href'],
                'label' => $link['label'],
                'svg_paths' => $link['svg_paths'],
            ];
        }

        $orderedSections = [];
        foreach ($sections as $label => $links) {
            $orderedSections[] = [
                'label' => $label,
                'links' => $links,
            ];
        }

        return $orderedSections;
    }

    /**
     * @return list<array{
     *     capability: string,
     *     description: string,
     *     href: string,
     *     label: string,
     *     section: string,
     *     svg_paths: string
     * }>
     */
    private static function visibleLinksForRole(string $role): array
    {
        return array_values(array_filter(
            self::links(),
            static fn (array $link): bool => RoleCapabilities::hasCapability($role, $link['capability']),
        ));
    }

    /**
     * @return list<array{
     *     capability: string,
     *     description: string,
     *     href: string,
     *     label: string,
     *     section: string,
     *     svg_paths: string
     * }>
     */
    private static function links(): array
    {
        return [
            [
                'capability' => RoleCapabilities::CONTENT_READ,
                'description' => 'Write and update posts and pages.',
                'href' => '/admin/content',
                'label' => 'Content',
                'section' => 'Content',
                'svg_paths' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
            ],
            [
                'capability' => RoleCapabilities::MEDIA_UPLOAD,
                'description' => 'Upload and reuse site images.',
                'href' => '/admin/media',
                'label' => 'Media',
                'section' => 'Content',
                'svg_paths' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
            ],
            [
                'capability' => RoleCapabilities::CATEGORY_MANAGE,
                'description' => 'Organize shared categories for posts and pages.',
                'href' => '/admin/categories',
                'label' => 'Categories',
                'section' => 'Content',
                'svg_paths' => '<path d="M3 6h7v5H3z"/><path d="M14 4h7v7h-7z"/><path d="M3 15h7v5H3z"/><path d="M14 13h7v7h-7z"/>',
            ],
            [
                'capability' => RoleCapabilities::SETTINGS_MANAGE,
                'description' => 'Adjust menus and sidebar links.',
                'href' => '/admin/navigation',
                'label' => 'Navigation',
                'section' => 'Content',
                'svg_paths' => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
            ],
            [
                'capability' => RoleCapabilities::THEME_MANAGE,
                'description' => 'Change the active frontend design.',
                'href' => '/admin/themes',
                'label' => 'Themes',
                'section' => 'Site',
                'svg_paths' => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/>',
            ],
            [
                'capability' => RoleCapabilities::PLUGIN_MANAGE,
                'description' => 'Enable or disable extensions.',
                'href' => '/admin/plugins',
                'label' => 'Plugins',
                'section' => 'Site',
                'svg_paths' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>',
            ],
            [
                'capability' => RoleCapabilities::SETTINGS_MANAGE,
                'description' => 'Manage site name, cache, and email.',
                'href' => '/admin/settings',
                'label' => 'Settings',
                'section' => 'Site',
                'svg_paths' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
            ],
            [
                'capability' => RoleCapabilities::SETTINGS_MANAGE,
                'description' => 'Backups, maintenance mode, and diagnostics.',
                'href' => '/admin/system',
                'label' => 'System',
                'section' => 'Site',
                'svg_paths' => '<path d="M4 4h16v16H4z"/><path d="M4 9h16"/><path d="M9 20V9"/>',
            ],
            [
                'capability' => RoleCapabilities::USER_MANAGE,
                'description' => 'Manage roles, passwords, and account state.',
                'href' => '/admin/users',
                'label' => 'Users',
                'section' => 'Users',
                'svg_paths' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            ],
        ];
    }
}

