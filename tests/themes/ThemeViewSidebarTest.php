<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\storage\PhpConfigWriter;
use Glyph\domain\content\ContentRecord;
use Glyph\services\navigation\NavigationManager;
use Glyph\services\themes\ThemeData;
use Glyph\services\themes\ThemeView;
use Glyph\ui\shared\DocumentRenderer;

$filesystem = new LocalFilesystem();
$navigationManager = new NavigationManager(
    configWriter: new PhpConfigWriter($filesystem),
    systemPath: sys_get_temp_dir() . '/glyph-theme-sidebar-' . bin2hex(random_bytes(6)),
    navigationConfig: [
        'menus' => [
            'primary' => [],
            'footer' => [],
            'sidebar' => [
                ['id' => 'resources', 'label' => 'Resources', 'url' => '/resources', 'target' => '_self', 'parent_id' => '', 'sort_order' => '0', 'content_id' => ''],
            ],
        ],
        'sidebar_settings' => [
            'display_latest_posts' => true,
            'latest_posts_limit' => 2,
        ],
    ],
);

$currentPost = new ContentRecord('post_current', 'post', 'Current Post', '/blog/current', 'published', '', '<p></p>', null, 'owner', null, '2026-03-09T00:00:00Z', '2026-03-09T00:00:00Z', '2026-03-09T00:00:00Z', [], '', '', '', 0, true);
$olderPost = new ContentRecord('post_older', 'post', 'Older Post', '/blog/older', 'published', '', '<p></p>', null, 'owner', null, '2026-03-08T00:00:00Z', '2026-03-08T00:00:00Z', '2026-03-08T00:00:00Z', [], '', '', '', 0, true);
$oldestPost = new ContentRecord('post_oldest', 'post', 'Oldest Post', '/blog/oldest', 'published', '', '<p></p>', null, 'owner', null, '2026-03-07T00:00:00Z', '2026-03-07T00:00:00Z', '2026-03-07T00:00:00Z', [], '', '', '', 0, true);
$draftPost = new ContentRecord('post_draft', 'post', 'Draft Post', '/blog/draft', 'draft', '', '<p></p>', null, 'owner', null, null, '2026-03-06T00:00:00Z', '2026-03-06T00:00:00Z', [], '', '', '', 0, true);
$page = new ContentRecord('page_about', 'page', 'About', '/about', 'published', '', '<p></p>', null, 'owner', null, '2026-03-05T00:00:00Z', '2026-03-05T00:00:00Z', '2026-03-05T00:00:00Z', [], '', '', '', 0, true);

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
    pages: [$currentPost, $olderPost, $oldestPost, $draftPost, $page],
);

$html = $view->renderSidebar($currentPost);

if (!str_contains($html, 'Sidebar Links')) {
    return false;
}

if (!str_contains($html, 'Resources')) {
    return false;
}

if (!str_contains($html, 'Latest Posts')) {
    return false;
}

if (!str_contains($html, 'Older Post') || !str_contains($html, 'Oldest Post')) {
    return false;
}

if (str_contains($html, 'Current Post') || str_contains($html, 'Draft Post')) {
    return false;
}

return true;
