<?php

declare(strict_types=1);

use Glyph\ui\shared\DocumentRenderer;

$renderer = new DocumentRenderer();

$html = $renderer->render(
    'Example Title',
    '<main>Body</main>',
    'Example description',
    'theme-frontend',
    '',
    '',
    [
        'canonical_url' => 'https://example.com/test',
        'site_name' => 'Glyph Demo',
        'og_type' => 'article',
        'og_image' => 'https://example.com/uploads/social.webp',
        'robots' => 'index,follow',
    ],
);

$checks = [
    '<link rel="canonical" href="https://example.com/test">',
    '<meta property="og:title" content="Example Title">',
    '<meta property="og:type" content="article">',
    '<meta property="og:image" content="https://example.com/uploads/social.webp">',
    '<meta name="twitter:card" content="summary_large_image">',
    '<meta name="robots" content="index,follow">',
    '<link rel="preconnect" href="https://fonts.googleapis.com">',
];

foreach ($checks as $needle) {
    if (!str_contains($html, $needle)) {
        return false;
    }
}

if (!preg_match('#<link rel="preload" href="/assets/glyph\.css\?v=\d+" as="style">#', $html)) {
    return false;
}

if (!preg_match('#<link rel="stylesheet" href="/assets/glyph\.css\?v=\d+">#', $html)) {
    return false;
}

if (!str_contains($html, '<style>html{background:#0d0f12;color-scheme:dark;}body{margin:0;min-height:100vh;background:#0d0f12;color:#e4e8f0;}</style>')) {
    return false;
}

return true;
