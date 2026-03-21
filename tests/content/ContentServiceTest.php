<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\security\SecretGenerator;
use Glyph\adapters\security\HtmlSanitizer;
use Glyph\adapters\storage\ContentFileRepository;
use Glyph\adapters\storage\RedirectFileRepository;
use Glyph\services\content\ContentInput;
use Glyph\services\content\ContentService;
use Glyph\services\content\ContentValidator;
use Glyph\services\content\SlugManager;

$root = sys_get_temp_dir() . '/glyph-test-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root . '/content/posts');
    $filesystem->ensureDirectoryExists($root . '/content/pages');
    $filesystem->ensureDirectoryExists($root . '/data/redirects');

    $slugManager = new SlugManager();
    $service = new ContentService(
        contentRepository: new ContentFileRepository(
            filesystem: $filesystem,
            postsPath: $root . '/content/posts',
            pagesPath: $root . '/content/pages',
        ),
        redirectRepository: new RedirectFileRepository(
            filesystem: $filesystem,
            redirectFilePath: $root . '/data/redirects/redirects.json',
        ),
        validator: new ContentValidator($slugManager),
        slugManager: $slugManager,
        secretGenerator: new SecretGenerator(),
        htmlSanitizer: new HtmlSanitizer(),
    );

    $created = $service->create(
        new ContentInput(
            type: 'post',
            title: 'Hello',
            slug: '/hello',
            status: 'published',
            excerpt: 'Hello excerpt',
            bodyHtml: '<p>Hello</p>',
            featuredImage: null,
            parentId: null,
            seoTitle: '',
            seoDescription: '',
        ),
        'owner-1',
    );

    if ($service->findPublishedBySlug('/hello') === null) {
        return false;
    }

    $updated = $service->update(
        $created,
        new ContentInput(
            type: 'post',
            title: 'Hello Updated',
            slug: '/Hello Updated',
            status: 'published',
            excerpt: 'Updated excerpt',
            bodyHtml: '<div class="callout" style="color: red;"><p>Updated</p></div>',
            featuredImage: null,
            parentId: null,
            seoTitle: '',
            seoDescription: '',
        ),
    );

    if ($updated->slug !== '/hello-updated') {
        return false;
    }

    if (!str_contains($updated->bodyHtml, '<div class="callout"')) {
        return false;
    }

    if (!str_contains($updated->bodyHtml, 'style="color: red;"')) {
        return false;
    }

    if (!str_contains($updated->bodyHtml, '<p>Updated</p>')) {
        return false;
    }

    $reloaded = $service->findById('post', $updated->id);
    if ($reloaded === null || !str_contains($reloaded->bodyHtml, 'style="color: red;"')) {
        return false;
    }

    if ($service->findRedirectTarget('/hello') !== '/hello-updated') {
        return false;
    }

    if ($service->findPublishedBySlug('/hello-updated') === null) {
        return false;
    }

    $search = $service->searchPublished('updated', 1);

    if ($search->totalItems < 1) {
        return false;
    }

    return true;
} finally {
    if (is_dir($root)) {
        $filesystem->deleteDirectoryRecursively($root);
    }
}

