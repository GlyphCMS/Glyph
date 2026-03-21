<?php

declare(strict_types=1);

use Glyph\services\themes\ThemeData;
use Glyph\ui\admin\ThemePageRenderer;

$renderer = new ThemePageRenderer();
$themes = [
    new ThemeData(
        name: 'Default',
        directoryName: 'default',
        path: '/themes/default',
        version: '1.0.0',
        author: 'Glyph',
        description: 'Default theme.',
        screenshotUrl: null,
        assets: [],
        manifest: [],
    ),
    new ThemeData(
        name: 'Midnight',
        directoryName: 'midnight',
        path: '/themes/midnight',
        version: '2.1.0',
        author: 'Ryan',
        description: 'Dark theme.',
        screenshotUrl: '/themes/midnight/screenshot.png',
        assets: [],
        manifest: [],
    ),
];

$html = $renderer->render(
    themes: $themes,
    activeTheme: 'default',
    uploadCsrfToken: 'upload-token',
    activateCsrfToken: 'activate-token',
    deleteCsrfToken: 'delete-token',
    successMessage: null,
    errorMessage: null,
);

return str_contains($html, 'Set Active Theme')
    && str_contains($html, 'Active Theme</span>')
    && str_contains($html, 'action="/admin/themes/activate"')
    && str_contains($html, 'name="theme" value="midnight"')
    && str_contains($html, '&middot; v2.1.0 &middot; Ryan');
