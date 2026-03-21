<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\storage\PhpConfigWriter;
use Glyph\services\navigation\NavigationManager;
use Glyph\services\themes\ThemeData;
use Glyph\services\themes\ThemeView;
use Glyph\ui\shared\DocumentRenderer;

$filesystem = new LocalFilesystem();
$navigationManager = new NavigationManager(
    configWriter: new PhpConfigWriter($filesystem),
    systemPath: sys_get_temp_dir() . '/glyph-theme-nav-' . bin2hex(random_bytes(6)),
    navigationConfig: [
        'menus' => [
            'primary' => [
                ['id' => 'poop', 'label' => 'Poop', 'url' => '/poop', 'target' => '_self', 'parent_id' => '', 'sort_order' => '0', 'content_id' => ''],
                ['id' => 'pee', 'label' => 'Pee', 'url' => '/pee', 'target' => '_self', 'parent_id' => 'poop', 'sort_order' => '0', 'content_id' => ''],
            ],
            'footer' => [],
            'sidebar' => [],
        ],
        'sidebar_settings' => [
            'display_latest_posts' => false,
            'latest_posts_limit' => 5,
        ],
    ],
);

$view = new ThemeView(
    documentRenderer: new DocumentRenderer(),
    theme: new ThemeData('Default', 'default', __DIR__, '1.0.0', 'Glyph', 'Default theme', null, [], []),
    siteName: 'Glyph Demo',
    siteTagline: 'Demo',
    siteUrl: 'https://example.com',
    siteSocialImage: '',
    dateFormat: 'F j, Y',
    timeFormat: 'g:i A',
    timezone: 'UTC',
    hookManager: null,
    navigationManager: $navigationManager,
    userRepository: null,
    pages: [],
);

$html = $view->navigationHtml('primary', 'site-header__nav-list');

$poopPosition = strpos($html, 'Poop');
$submenuPosition = strpos($html, 'site-header__nav-list--depth-1');
$peePosition = strpos($html, 'Pee');

if ($poopPosition === false || $submenuPosition === false || $peePosition === false) {
    return false;
}

if (!str_contains($html, 'site-nav__item--has-children')) {
    return false;
}

if (!str_contains($html, 'site-nav__item--depth-0') || !str_contains($html, 'site-nav__item--depth-1')) {
    return false;
}

if (!($poopPosition < $submenuPosition && $submenuPosition < $peePosition)) {
    return false;
}

return true;