<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\storage\ContentFileRepository;
use Glyph\adapters\storage\RedirectFileRepository;
use Glyph\adapters\security\HtmlSanitizer;
use Glyph\adapters\security\SecretGenerator;
use Glyph\domain\content\ContentRecord;
use Glyph\services\content\ContentService;
use Glyph\services\content\ContentValidator;
use Glyph\services\content\SlugManager;
use Glyph\services\themes\ThemeRenderer;
use Glyph\services\themes\ThemeResolver;
use Glyph\ui\shared\DocumentRenderer;

$root = sys_get_temp_dir() . '/glyph-theme-featured-image-version-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root . '/posts');
    $filesystem->ensureDirectoryExists($root . '/pages');
    $filesystem->ensureDirectoryExists($root . '/redirects');
    $filesystem->ensureDirectoryExists($root . '/default/templates');
    file_put_contents($root . '/default/theme.json', json_encode(['name' => 'Default Theme'], JSON_PRETTY_PRINT));
    file_put_contents(
        $root . '/default/templates/content.php',
        <<<'TPL'
<?php declare(strict_types=1); ?>
<?php echo $__theme->document($pageTitle, '<main><img src="' . $__theme->escape($__theme->contentImageUrl($contentRecord)) . '"></main>', $metaDescription, 'theme-frontend', $documentMeta);
TPL
    );

    $contentService = new ContentService(
        new ContentFileRepository($filesystem, $root . '/posts', $root . '/pages'),
        new RedirectFileRepository($filesystem, $root . '/redirects/redirects.json'),
        new ContentValidator(new SlugManager()),
        new SlugManager(),
        new SecretGenerator(),
        new HtmlSanitizer(),
    );

    $renderer = new ThemeRenderer(
        siteConfig: [
            'site_name' => 'Glyph Demo',
            'tagline' => '',
            'active_theme' => 'default',
            'site_url' => 'https://example.com',
            'site_social_image' => '/assets/social.webp',
            'date_format' => 'F j, Y',
            'time_format' => 'g:i A',
            'timezone' => 'UTC',
        ],
        themeResolver: new ThemeResolver($filesystem, $root, 'default'),
        documentRenderer: new DocumentRenderer(),
        contentService: $contentService,
    );

    $updatedAt = '2026-03-09T12:34:56Z';
    $version = sha1($updatedAt);
    $record = new ContentRecord(
        id: 'post_1',
        type: 'post',
        title: 'Versioned Image Post',
        slug: '/versioned-image-post',
        status: 'published',
        excerpt: 'Excerpt',
        bodyHtml: '<p>Body</p>',
        featuredImage: '/uploads/images/example.webp',
        authorId: 'owner',
        parentId: null,
        publishedAt: '2026-03-09T12:00:00Z',
        createdAt: '2026-03-09T12:00:00Z',
        updatedAt: $updatedAt,
        redirects: [],
        seoTitle: '',
        seoDescription: '',
        seoImage: '/uploads/images/seo.webp',
    );

    $html = $renderer->renderContent($record);

    if (!str_contains($html, '/uploads/images/example.webp?v=' . $version)) {
        return false;
    }

    if (!str_contains($html, 'https://example.com/uploads/images/seo.webp?v=' . $version)) {
        return false;
    }

    if (str_contains($html, 'https://example.com/uploads/images/example.webp?v=' . $version)) {
        return false;
    }

    return true;
} finally {
    if (is_dir($root)) {
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
