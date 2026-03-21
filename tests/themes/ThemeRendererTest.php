<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\storage\ContentFileRepository;
use Glyph\adapters\storage\RedirectFileRepository;
use Glyph\adapters\security\HtmlSanitizer;
use Glyph\adapters\security\SecretGenerator;
use Glyph\services\content\ContentListResult;
use Glyph\services\content\ContentService;
use Glyph\services\content\ContentValidator;
use Glyph\services\content\SlugManager;
use Glyph\services\themes\ThemeRenderer;
use Glyph\services\themes\ThemeResolver;
use Glyph\ui\shared\DocumentRenderer;

$root = sys_get_temp_dir() . '/glyph-theme-renderer-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root . '/posts');
    $filesystem->ensureDirectoryExists($root . '/pages');
    $filesystem->ensureDirectoryExists($root . '/redirects');
    $filesystem->ensureDirectoryExists($root . '/default/templates');
    $filesystem->ensureDirectoryExists($root . '/default/partials');
    $filesystem->ensureDirectoryExists($root . '/default/assets');
    file_put_contents($root . '/default/theme.json', json_encode(['name' => 'Default Theme'], JSON_PRETTY_PRINT));
    file_put_contents($root . '/default/assets/manifest.json', json_encode(['logo' => 'logo.svg'], JSON_PRETTY_PRINT));
    file_put_contents($root . '/default/assets/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    file_put_contents(
        $root . '/default/partials/header.php',
        <<<'TPL'
<?php declare(strict_types=1); ?>
<?php echo '<header>' . $__theme->escape($title) . '|' . $__theme->escape((string) $__theme->asset('logo')) . '</header>';
TPL
    );
    file_put_contents(
        $root . '/default/templates/home.php',
        <<<'TPL'
<?php declare(strict_types=1); ?>
<?php
echo $__theme->document($pageTitle, '<main>' . $__theme->partial('header', ['title' => $__theme->siteName()]) . '<p>Total: ' . $listResult->totalItems . '</p></main>', $metaDescription);
TPL
    );
    file_put_contents($root . '/default/templates/search.php', "<?php declare(strict_types=1); ?><?php echo \$__theme->document(\$pageTitle, '<main>search</main>', \$metaDescription);");
    file_put_contents($root . '/default/templates/content.php', "<?php declare(strict_types=1); ?><?php echo \$__theme->document(\$pageTitle, '<main>content</main>', \$metaDescription);");
    file_put_contents($root . '/default/templates/404.php', "<?php declare(strict_types=1); ?><?php echo \$__theme->document(\$pageTitle, '<main>404</main>', \$metaDescription);");

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
            'site_url' => '',
            'date_format' => 'F j, Y',
            'time_format' => 'g:i A',
            'timezone' => 'UTC',
        ],
        themeResolver: new ThemeResolver($filesystem, $root, 'default'),
        documentRenderer: new DocumentRenderer(),
        contentService: $contentService,
    );

    $html = $renderer->renderHome(new ContentListResult([], 1, 10, 0, 0));

    if (!str_contains($html, 'Glyph Demo')) {
        return false;
    }

    if (!str_contains($html, 'Total: 0')) {
        return false;
    }

    if (!str_contains($html, '/themes/default/assets/logo.svg')) {
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
