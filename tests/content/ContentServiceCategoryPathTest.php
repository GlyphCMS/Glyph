<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\security\HtmlSanitizer;
use Glyph\adapters\security\SecretGenerator;
use Glyph\adapters\storage\CategoryFileRepository;
use Glyph\adapters\storage\ContentFileRepository;
use Glyph\adapters\storage\RedirectFileRepository;
use Glyph\services\categories\CategoryInput;
use Glyph\services\categories\CategoryService;
use Glyph\services\content\ContentInput;
use Glyph\services\content\ContentService;
use Glyph\services\content\ContentValidator;
use Glyph\services\content\SlugManager;

$root = sys_get_temp_dir() . '/glyph-content-category-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root . '/content/posts');
    $filesystem->ensureDirectoryExists($root . '/content/pages');
    $filesystem->ensureDirectoryExists($root . '/data/categories');
    $filesystem->ensureDirectoryExists($root . '/data/redirects');

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
    $contentService = new ContentService(
        $contentRepository,
        $redirectRepository,
        new ContentValidator($slugManager, $categoryService),
        $slugManager,
        new SecretGenerator(),
        new HtmlSanitizer(),
        $categoryService,
    );

    $parent = $categoryService->create(new CategoryInput('Guides', 'guides', 'Guides archive', null));
    $child = $categoryService->create(new CategoryInput('Getting Started', 'getting-started', '', $parent->id));

    $created = $contentService->create(
        new ContentInput(
            type: 'post',
            title: 'Install Glyph',
            slug: 'install-glyph',
            status: 'published',
            excerpt: 'Install guide',
            bodyHtml: '<p>Install</p>',
            featuredImage: null,
            parentId: null,
            categoryId: $child->id,
            seoTitle: '',
            seoDescription: '',
        ),
        'owner',
    );

    if ($created->slug !== '/guides/getting-started/install-glyph') {
        return false;
    }

    if ($categoryService->archivePathFor($child) !== '/guides/getting-started') {
        return false;
    }

    if ($contentService->findPublishedBySlug('/guides/getting-started/install-glyph') === null) {
        return false;
    }

    $archive = $contentService->listPublishedByCategory($parent, 1);

    return $archive->totalItems === 1 && $archive->items[0]->id === $created->id;
} finally {
    if (is_dir($root)) {
        $filesystem->deleteDirectoryRecursively($root);
    }
}
