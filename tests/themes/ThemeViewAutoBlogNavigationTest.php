<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\storage\CategoryFileRepository;
use Glyph\adapters\storage\ContentFileRepository;
use Glyph\adapters\storage\PhpConfigWriter;
use Glyph\adapters\storage\RedirectFileRepository;
use Glyph\adapters\security\SecretGenerator;
use Glyph\services\categories\CategoryInput;
use Glyph\services\categories\CategoryService;
use Glyph\services\content\SlugManager;
use Glyph\services\navigation\NavigationManager;
use Glyph\services\themes\ThemeData;
use Glyph\services\themes\ThemeView;
use Glyph\ui\shared\DocumentRenderer;

$root = sys_get_temp_dir() . '/glyph-theme-view-category-nav-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root . '/content/posts');
    $filesystem->ensureDirectoryExists($root . '/content/pages');
    $filesystem->ensureDirectoryExists($root . '/data/categories');
    $filesystem->ensureDirectoryExists($root . '/data/redirects');
    $filesystem->ensureDirectoryExists($root . '/data/system');

    $slugManager = new SlugManager();
    $contentRepository = new ContentFileRepository($filesystem, $root . '/content/posts', $root . '/content/pages');
    $redirectRepository = new RedirectFileRepository($filesystem, $root . '/data/redirects/redirects.json');
    $categoryService = new CategoryService(
        new CategoryFileRepository($filesystem, $root . '/data/categories/categories.json'),
        $slugManager,
        new SecretGenerator(),
        $contentRepository,
        $redirectRepository,
    );

    $guides = $categoryService->create(new CategoryInput('Guides', 'guides', '', null));
    $categoryService->create(new CategoryInput('Setup', 'setup', '', $guides->id));

    $themeView = new ThemeView(
        new DocumentRenderer(),
        new ThemeData('Default', 'default', $root . '/theme', '1.0.0', '', '', null, [], []),
        'Glyph',
        '',
        '',
        '',
        '',
        true,
        'F j, Y',
        'g:i A',
        'UTC',
        null,
        new NavigationManager(new PhpConfigWriter($filesystem), $root . '/data/system', ['menus' => ['primary' => [], 'footer' => [], 'sidebar' => []]]),
        null,
        [],
        $categoryService,
    );

    $primary = $themeView->navigation('primary');
    $footer = $themeView->navigation('footer');

    if ($primary === []) {
        return false;
    }

    $categories = $primary[count($primary) - 1];

    return $categories->label === 'Categories'
        && $categories->url === '/categories'
        && $categories->children !== []
        && $categories->children[0]->url === '/guides'
        && $categories->children[0]->children !== []
        && $categories->children[0]->children[0]->url === '/guides/setup'
        && $footer === [];
} finally {
    if (is_dir($root)) {
        $filesystem->deleteDirectoryRecursively($root);
    }
}