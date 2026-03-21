<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\http\Request;
use Glyph\adapters\storage\ContentFileRepository;
use Glyph\adapters\storage\RedirectFileRepository;
use Glyph\adapters\security\HtmlSanitizer;
use Glyph\adapters\security\SecretGenerator;
use Glyph\services\content\ContentInput;
use Glyph\services\content\ContentService;
use Glyph\services\content\ContentValidator;
use Glyph\services\content\SlugManager;
use Glyph\services\themes\ThemeRenderer;
use Glyph\services\themes\ThemeResolver;
use Glyph\ui\frontend\FrontendController;
use Glyph\ui\shared\DocumentRenderer;

$root = sys_get_temp_dir() . '/glyph-homepage-mode-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root . '/posts');
    $filesystem->ensureDirectoryExists($root . '/pages');
    $filesystem->ensureDirectoryExists($root . '/redirects');
    $filesystem->ensureDirectoryExists($root . '/themes/default/templates');
    $filesystem->ensureDirectoryExists($root . '/themes/default/partials');

    file_put_contents($root . '/themes/default/theme.json', json_encode(['name' => 'Default'], JSON_PRETTY_PRINT));
    file_put_contents($root . '/themes/default/templates/home.php', <<<'PHP'
<?php declare(strict_types=1); ?>
<?php echo $__theme->document($pageTitle, '<main>home</main>', $metaDescription);
PHP);
    file_put_contents($root . '/themes/default/templates/search.php', <<<'PHP'
<?php declare(strict_types=1); ?>
<?php echo $__theme->document($pageTitle, '<main>search</main>', $metaDescription);
PHP);
    file_put_contents($root . '/themes/default/templates/content.php', <<<'PHP'
<?php declare(strict_types=1); ?>
<?php echo $__theme->document($pageTitle, '<main>' . $__theme->escape($contentRecord->title) . '</main>', $metaDescription);
PHP);
    file_put_contents($root . '/themes/default/templates/404.php', <<<'PHP'
<?php declare(strict_types=1); ?>
<?php echo $__theme->document($pageTitle, '<main>404</main>', $metaDescription);
PHP);

    $service = new ContentService(
        new ContentFileRepository($filesystem, $root . '/posts', $root . '/pages'),
        new RedirectFileRepository($filesystem, $root . '/redirects/redirects.json'),
        new ContentValidator(new SlugManager()),
        new SlugManager(),
        new SecretGenerator(),
        new HtmlSanitizer(),
    );

    $page = $service->create(
        new ContentInput('page', 'Home Page', '/home', 'published', 'Excerpt', '<p>Body</p>', null, null, '', ''),
        'owner'
    );

    $themeRenderer = new ThemeRenderer(
        [
            'site_name' => 'Glyph',
            'tagline' => '',
            'site_url' => 'https://example.com',
            'site_meta_description' => '',
            'active_theme' => 'default',
            'homepage_mode' => 'page',
            'homepage_page_id' => $page->id,
            'posts_per_page' => 10,
            'timezone' => 'UTC',
            'date_format' => 'F j, Y',
            'time_format' => 'g:i A',
        ],
        new ThemeResolver($filesystem, $root . '/themes', 'default'),
        new DocumentRenderer(),
        $service,
    );

    $controller = new FrontendController($service, $themeRenderer, [
        'homepage_mode' => 'page',
        'homepage_page_id' => $page->id,
        'posts_per_page' => 10,
    ]);

    $request = new Request('GET', '/', [], [], [], []);
    $response = $controller->home($request);

    ob_start();
    $response->send();
    $body = ob_get_clean();

    if (!is_string($body) || !str_contains($body, 'Home Page')) {
        return false;
    }

    return true;
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
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
