<?php

declare(strict_types=1);

use Glyph\services\settings\SettingsInput;
use Glyph\services\settings\SettingsValidator;

$validator = new SettingsValidator();
$availableThemes = ['default', 'midnight'];
$availableHomepagePages = ['page_home'];

$validInput = new SettingsInput(
    siteName: 'Glyph Demo',
    tagline: 'A flat-file CMS demo',
    siteUrl: 'https://example.com',
    siteMetaDescription: 'Demo site description',
    siteSocialImage: '/uploads/social.webp',
    siteLogo: '/uploads/brand/logo.svg',
    siteLogoShowName: false,
    activeTheme: 'default',
    homepageMode: 'page',
    homepagePageId: 'page_home',
    postsPerPage: '12',
    sanitizeContentHtml: true,
    timezone: 'UTC',
    dateFormat: 'F j, Y',
    timeFormat: 'g:i A',
    cacheDriver: 'apcu',
    mailTransport: 'smtp',
    mailFromName: 'Glyph Demo',
    mailFromEmail: 'demo@example.com',
    smtpHost: 'smtp.example.com',
    smtpPort: '587',
    smtpEncryption: 'tls',
    smtpUsername: 'user',
    smtpPassword: 'secret',
    smtpTimeoutSeconds: '15',
    testEmailRecipient: 'check@example.com',
);

if (!$validator->validate($validInput, true, $availableThemes, $availableHomepagePages)->isValid()) {
    return false;
}

$invalidInput = new SettingsInput(
    siteName: '',
    tagline: str_repeat('x', 300),
    siteUrl: 'notaurl',
    siteMetaDescription: str_repeat('x', 400),
    siteSocialImage: '../bad.png',
    siteLogo: '../bad-logo.svg',
    activeTheme: 'missing-theme',
    homepageMode: 'page',
    homepagePageId: 'missing-page',
    postsPerPage: '0',
    sanitizeContentHtml: false,
    timezone: 'Mars/Base',
    dateFormat: '',
    timeFormat: '',
    cacheDriver: 'apcu',
    mailTransport: 'smtp',
    mailFromName: '',
    mailFromEmail: 'bad-email',
    smtpHost: '',
    smtpPort: 'abc',
    smtpEncryption: 'broken',
    smtpUsername: str_repeat('u', 300),
    smtpPassword: str_repeat('p', 300),
    smtpTimeoutSeconds: '0',
    testEmailRecipient: 'not-an-email',
);

$result = $validator->validate($invalidInput, false, $availableThemes, $availableHomepagePages);

if ($result->isValid()) {
    return false;
}

$expectedFields = [
    'site_name',
    'tagline',
    'site_url',
    'site_meta_description',
    'site_social_image',
    'site_logo',
    'active_theme',
    'homepage_page_id',
    'posts_per_page',
    'timezone',
    'date_format',
    'time_format',
    'cache_driver',
    'mail_from_name',
    'mail_from_email',
    'smtp_host',
    'smtp_port',
    'smtp_encryption',
    'smtp_username',
    'smtp_password',
    'smtp_timeout_seconds',
    'test_email_recipient',
];

foreach ($expectedFields as $field) {
    if ($result->firstError($field) === null) {
        return false;
    }
}

return true;
