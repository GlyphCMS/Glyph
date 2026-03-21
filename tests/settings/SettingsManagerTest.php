<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\storage\PhpConfigWriter;
use Glyph\services\settings\SettingsInput;
use Glyph\services\settings\SettingsManager;

$root = sys_get_temp_dir() . '/glyph-settings-manager-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root);

    $manager = new SettingsManager(
        configWriter: new PhpConfigWriter($filesystem),
        systemPath: $root,
    );

    $manager->save(
        new SettingsInput(
            siteName: 'Glyph Demo',
            tagline: 'Demo',
            siteUrl: 'https://example.com',
            siteMetaDescription: 'Site description',
            siteSocialImage: '/uploads/social.webp',
            siteLogo: '/uploads/brand/logo.webp',
            siteLogoShowName: false,
            activeTheme: 'default',
            homepageMode: 'posts',
            homepagePageId: '',
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
            smtpPassword: 'pass',
            smtpTimeoutSeconds: '15',
            testEmailRecipient: '',
        ),
        true,
    );

    $site = require $root . '/site.php';
    $cache = require $root . '/cache.php';
    $mail = require $root . '/mail.php';

    if (($site['site_name'] ?? '') !== 'Glyph Demo') {
        return false;
    }

    if (($site['site_url'] ?? '') !== 'https://example.com') {
        return false;
    }

    if (($site['posts_per_page'] ?? 0) !== 12) {
        return false;
    }

    if (($site['site_social_image'] ?? '') !== '/uploads/social.webp') {
        return false;
    }

    if (($site['site_logo'] ?? '') !== '/uploads/brand/logo.webp') {
        return false;
    }

    if (($site['site_logo_show_name'] ?? null) !== false) {
        return false;
    }

    if (($site['sanitize_content_html'] ?? null) !== true) {
        return false;
    }

    if (($cache['driver'] ?? '') !== 'apcu') {
        return false;
    }

    if (($mail['transport'] ?? '') !== 'smtp') {
        return false;
    }

    if ((($mail['smtp'] ?? [])['host'] ?? '') !== 'smtp.example.com') {
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
