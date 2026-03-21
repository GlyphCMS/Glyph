<?php

declare(strict_types=1);

use Glyph\ui\shared\DocumentRenderer;

$renderer = new DocumentRenderer();

$html = $renderer->render(
    'Admin Title',
    '<main class="page-shell"><section class="panel">Admin content</section></main>',
    'Admin description',
    'theme-admin',
    'owner@example.com',
    'Administrator',
);

$footerPosition = strpos($html, 'class="admin-footer"');
$contentPosition = strpos($html, 'Admin content');

return $footerPosition !== false
    && $contentPosition !== false
    && $footerPosition > $contentPosition
    && str_contains($html, 'class="admin-footer__inner"')
    && str_contains($html, 'Powered by')
    && str_contains($html, 'class="powered-by-glyph"')
    && str_contains($html, 'href="https://glyphcms.com"');
