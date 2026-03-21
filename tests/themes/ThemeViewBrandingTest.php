<?php

declare(strict_types=1);

use Glyph\services\themes\ThemeData;
use Glyph\services\themes\ThemeView;
use Glyph\ui\shared\DocumentRenderer;

$view = new ThemeView(
    documentRenderer: new DocumentRenderer(),
    theme: new ThemeData('Default', 'default', '/tmp/default', '1.0.0', 'Glyph', 'Default theme', null, [], []),
    siteName: 'Glyph Demo',
    siteTagline: 'Demo',
    siteUrl: 'https://example.com',
    siteSocialImage: '/uploads/social.webp',
    siteLogo: '/uploads/brand/logo.svg',
    siteLogoShowName: false,
);

$fallbackView = new ThemeView(
    documentRenderer: new DocumentRenderer(),
    theme: new ThemeData('Default', 'default', '/tmp/default', '1.0.0', 'Glyph', 'Default theme', null, [], []),
    siteName: 'Glyph Demo',
    siteTagline: 'Demo',
    siteUrl: 'https://example.com',
    siteSocialImage: '/uploads/social.webp',
    siteLogo: '',
    siteLogoShowName: false,
);

return $view->siteLogo() === '/uploads/brand/logo.svg'
    && $view->siteLogoShowName() === false
    && $view->siteBrandImage() === '/uploads/brand/logo.svg'
    && $fallbackView->siteLogoShowName() === true
    && $fallbackView->siteBrandImage() === '/assets/branding/glyph-app-icon-256.png';
