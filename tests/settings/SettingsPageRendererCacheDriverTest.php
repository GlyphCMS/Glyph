<?php

declare(strict_types=1);

use Glyph\services\settings\SettingsInput;
use Glyph\services\settings\SettingsValidationResult;
use Glyph\ui\admin\SettingsPageRenderer;

$renderer = new SettingsPageRenderer();
$input = new SettingsInput(
    siteName: 'Glyph Demo',
    tagline: 'Demo tagline',
    siteUrl: 'https://example.com',
    siteMetaDescription: 'Demo description',
    siteSocialImage: '/uploads/social.webp',
    siteLogo: '/uploads/brand/logo.svg',
    siteLogoShowName: false,
    activeTheme: 'default',
    homepageMode: 'posts',
    homepagePageId: '',
    postsPerPage: '10',
    sanitizeContentHtml: true,
    timezone: 'UTC',
    dateFormat: 'F j, Y',
    timeFormat: 'g:i A',
    cacheDriver: 'apcu',
    mailTransport: 'php_mail',
    mailFromName: 'Glyph',
    mailFromEmail: 'hello@example.com',
    smtpHost: '',
    smtpPort: '587',
    smtpEncryption: 'tls',
    smtpUsername: '',
    smtpPassword: '',
    smtpTimeoutSeconds: '15',
    testEmailRecipient: '',
);

$htmlAvailable = $renderer->render(
    $input,
    new SettingsValidationResult([]),
    true,
    [],
    'save-token',
    'test-token',
    'media-token',
    null,
    null,
);

$htmlUnavailable = $renderer->render(
    $input,
    new SettingsValidationResult(['cache_driver' => 'APCu is not available on this server.']),
    false,
    [],
    'save-token',
    'test-token',
    'media-token',
    null,
    null,
);

return str_contains($htmlAvailable, 'class="cache-driver-grid"')
    && str_contains($htmlAvailable, 'name="cache_driver" value="file"')
    && str_contains($htmlAvailable, 'name="cache_driver" value="apcu" checked')
    && str_contains($htmlAvailable, 'Recommended on this server because the APCu extension is available.')
    && str_contains($htmlAvailable, 'id="site_logo" name="site_logo"')
    && str_contains($htmlAvailable, 'id="site-logo-upload-token" type="hidden" value="media-token"')
    && str_contains($htmlAvailable, 'name="site_logo_show_name" value="1"')
    && str_contains($htmlAvailable, 'src="/uploads/brand/logo.svg" alt="Site logo preview"')
    && str_contains($htmlAvailable, 'Homepage</h2>')
    && !str_contains($htmlAvailable, 'Active Theme')
    && str_contains($htmlAvailable, 'class="js-homepage-posts"')
    && str_contains($htmlAvailable, 'class="js-homepage-page is-hidden"')
    && str_contains($htmlUnavailable, 'class="cache-driver-card is-disabled"')
    && str_contains($htmlUnavailable, 'Unavailable right now because the APCu PHP extension is not loaded.')
    && str_contains($htmlUnavailable, 'APCu is not available on this server.');
