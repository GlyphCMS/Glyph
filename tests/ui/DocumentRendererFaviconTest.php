<?php

declare(strict_types=1);

use Glyph\ui\shared\DocumentRenderer;

$renderer = new DocumentRenderer();

$customHtml = $renderer->render(
    'Example Title',
    '<main>Body</main>',
    'Example description',
    'theme-frontend',
    '',
    '',
    [
        'favicon_href' => '/uploads/images/site-logo.png',
    ],
);

if (!str_contains($customHtml, '<link rel="icon" href="/uploads/images/site-logo.png">')) {
    return false;
}

if (str_contains($customHtml, 'glyph-favicon-16.ico') || str_contains($customHtml, 'glyph-favicon-32.ico')) {
    return false;
}

$defaultHtml = $renderer->render(
    'Example Title',
    '<main>Body</main>',
    'Example description',
    'theme-frontend',
);

if (!str_contains($defaultHtml, 'glyph-favicon-16.ico') || !str_contains($defaultHtml, 'glyph-favicon-32.ico')) {
    return false;
}

return true;
