<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\storage\PhpConfigWriter;
use Glyph\domain\content\ContentRecord;
use Glyph\services\navigation\NavigationManager;

$root = sys_get_temp_dir() . '/glyph-navigation-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root);

    $manager = new NavigationManager(
        configWriter: new PhpConfigWriter($filesystem),
        systemPath: $root,
        navigationConfig: [
            'menus' => [
                'primary' => [
                    ['id' => 'home', 'label' => 'Home', 'url' => '/', 'target' => '_self', 'parent_id' => '', 'sort_order' => '0', 'content_id' => ''],
                    ['id' => 'about', 'label' => '', 'url' => '', 'target' => '_self', 'parent_id' => 'home', 'sort_order' => '1', 'content_id' => 'page_about'],
                ],
                'footer' => [],
                'sidebar' => [
                    ['id' => 'resources', 'label' => 'Resources', 'url' => '/resources', 'target' => '_self', 'parent_id' => '', 'sort_order' => '0', 'content_id' => ''],
                ],
            ],
            'sidebar_settings' => [
                'display_latest_posts' => false,
                'latest_posts_limit' => 5,
            ],
        ],
    );

    $pages = [
        new ContentRecord('page_about', 'page', 'About', '/about', 'published', '', '<p></p>', null, 'owner', null, '2026-03-08T00:00:00Z', '2026-03-08T00:00:00Z', '2026-03-08T00:00:00Z', [], '', '', 'About Us', 2, true),
    ];

    $menu = $manager->menu('primary', $pages);
    if (count($menu) !== 1) {
        return false;
    }
    if ($menu[0]->children === [] || $menu[0]->children[0]->label !== 'About Us') {
        return false;
    }

    $sidebarMenu = $manager->menu('sidebar', $pages);
    if (count($sidebarMenu) !== 1 || $sidebarMenu[0]->label !== 'Resources') {
        return false;
    }

    $manager->save(
        [
            'primary' => [['id' => 'x', 'label' => 'X', 'url' => '/x', 'target' => '_self', 'parent_id' => '', 'sort_order' => '0', 'content_id' => '']],
            'footer' => [],
            'sidebar' => [['id' => 'support', 'label' => 'Support', 'url' => '/support', 'target' => '_self', 'parent_id' => '', 'sort_order' => '0', 'content_id' => '']],
        ],
        ['display_latest_posts' => '1', 'latest_posts_limit' => '3'],
    );

    $saved = require $root . '/navigation.php';
    if ((($saved['menus'] ?? [])['primary'][0]['label'] ?? '') !== 'X') {
        return false;
    }
    if ((($saved['menus'] ?? [])['sidebar'][0]['label'] ?? '') !== 'Support') {
        return false;
    }
    if ((($saved['sidebar_settings'] ?? [])['display_latest_posts'] ?? false) !== true) {
        return false;
    }
    if ((($saved['sidebar_settings'] ?? [])['latest_posts_limit'] ?? 0) !== 3) {
        return false;
    }

    $liveMenus = $manager->rawMenus();
    if (($liveMenus['primary'][0]['label'] ?? '') !== 'X') {
        return false;
    }
    if (($liveMenus['sidebar'][0]['label'] ?? '') !== 'Support') {
        return false;
    }

    $liveSettings = $manager->rawSidebarSettings();
    if ($liveSettings['display_latest_posts'] !== true || $liveSettings['latest_posts_limit'] !== 3) {
        return false;
    }

    return true;
} finally {
    if (is_dir($root)) {
        $filesystem->deleteDirectoryRecursively($root);
    }
}
