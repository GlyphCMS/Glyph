<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\security\HtmlSanitizer;
use Glyph\adapters\security\SecretGenerator;
use Glyph\adapters\storage\ContentFileRepository;
use Glyph\adapters\storage\RedirectFileRepository;
use Glyph\services\content\ContentInput;
use Glyph\services\content\ContentService;
use Glyph\services\content\ContentValidator;
use Glyph\services\content\SlugManager;

$root = sys_get_temp_dir() . '/glyph-content-validate-' . bin2hex(random_bytes(6));
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
        sanitizeContentHtml: true,
    );

    $stripped = $service->validate(new ContentInput(
        type: 'post',
        title: 'Dangerous',
        slug: '/dangerous',
        status: 'draft',
        excerpt: '',
        bodyHtml: '<script>alert(1)</script>',
        featuredImage: null,
        parentId: null,
        seoTitle: '',
        seoDescription: '',
    ));

    if ($stripped->firstError('body_html') === null) {
        return false;
    }

    $safeText = $service->validate(new ContentInput(
        type: 'post',
        title: 'Safe',
        slug: '/safe',
        status: 'draft',
        excerpt: '',
        bodyHtml: '<div class="callout" style="color: red;"><span>Kept text</span></div>',
        featuredImage: null,
        parentId: null,
        seoTitle: '',
        seoDescription: '',
    ));

    if (!$safeText->isValid()) {
        return false;
    }

    $safeEmbed = $service->validate(new ContentInput(
        type: 'post',
        title: 'Embed',
        slug: '/embed',
        status: 'draft',
        excerpt: '',
        bodyHtml: '<iframe src="https://example.com/embed" loading="lazy"></iframe>',
        featuredImage: null,
        parentId: null,
        seoTitle: '',
        seoDescription: '',
    ));

    return $safeEmbed->isValid();
} finally {
    if (is_dir($root)) {
        $filesystem->deleteDirectoryRecursively($root);
    }
}
