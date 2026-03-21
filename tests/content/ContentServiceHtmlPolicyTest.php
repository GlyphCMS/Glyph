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

$filesystem = new LocalFilesystem();
$rawRoot = sys_get_temp_dir() . '/glyph-content-html-policy-raw-' . bin2hex(random_bytes(6));
$sanitizedRoot = sys_get_temp_dir() . '/glyph-content-html-policy-sanitized-' . bin2hex(random_bytes(6));

$makeService = static function (string $root, bool $sanitizeContentHtml) use ($filesystem): ContentService {
    $filesystem->ensureDirectoryExists($root . '/content/posts');
    $filesystem->ensureDirectoryExists($root . '/content/pages');
    $filesystem->ensureDirectoryExists($root . '/data/redirects');

    $slugManager = new SlugManager();

    return new ContentService(
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
        sanitizeContentHtml: $sanitizeContentHtml,
    );
};

try {
    $rawService = $makeService($rawRoot, false);
    $rawBody = '<meta name="robots" content="noindex"><script>console.log(1)</script><p onclick="alert(1)">Hello</p>';

    $rawCreated = $rawService->create(
        new ContentInput(
            type: 'post',
            title: 'Raw HTML',
            slug: '/raw-html',
            status: 'published',
            excerpt: '',
            bodyHtml: $rawBody,
            featuredImage: null,
            parentId: null,
            seoTitle: '',
            seoDescription: '',
        ),
        'owner-1',
    );

    if ($rawCreated->bodyHtml !== $rawBody) {
        return false;
    }

    $sanitizedService = $makeService($sanitizedRoot, true);
    $sanitizedBody = '<meta name="robots" content="noindex"><script>console.log(1)</script><p onclick="alert(1)">Hello</p>';

    $sanitizedCreated = $sanitizedService->create(
        new ContentInput(
            type: 'post',
            title: 'Sanitized HTML',
            slug: '/sanitized-html',
            status: 'published',
            excerpt: '',
            bodyHtml: $sanitizedBody,
            featuredImage: null,
            parentId: null,
            seoTitle: '',
            seoDescription: '',
        ),
        'owner-1',
    );

    if (str_contains($sanitizedCreated->bodyHtml, '<script>')) {
        return false;
    }

    if (str_contains($sanitizedCreated->bodyHtml, '<meta')) {
        return false;
    }

    if (str_contains($sanitizedCreated->bodyHtml, 'onclick=')) {
        return false;
    }

    if (!str_contains($sanitizedCreated->bodyHtml, '<p>Hello</p>')) {
        return false;
    }

    $bypassed = $sanitizedService->create(
        new ContentInput(
            type: 'post',
            title: 'Bypassed HTML',
            slug: '/bypassed-html',
            status: 'published',
            excerpt: '',
            bodyHtml: $sanitizedBody,
            featuredImage: null,
            parentId: null,
            seoTitle: '',
            seoDescription: '',
            bypassHtmlSanitization: true,
        ),
        'owner-1',
        true,
    );

    if ($bypassed->bodyHtml !== $sanitizedBody) {
        return false;
    }

    if ($bypassed->bypassHtmlSanitization !== true) {
        return false;
    }

    $reloaded = $sanitizedService->findById('post', $bypassed->id);

    if ($reloaded === null || $reloaded->bodyHtml !== $sanitizedBody || $reloaded->bypassHtmlSanitization !== true) {
        return false;
    }

    return true;
} finally {
    foreach ([$rawRoot, $sanitizedRoot] as $root) {
        if (!is_dir($root)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($root);
    }
}
