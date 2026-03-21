<?php

declare(strict_types=1);

namespace Glyph\services\settings;

final class SettingsInput
{
    public function __construct(
        public readonly string $siteName,
        public readonly string $tagline,
        public readonly string $siteUrl,
        public readonly string $siteMetaDescription,
        public readonly string $siteSocialImage,
        public readonly string $siteLogo = '',
        public readonly bool $siteLogoShowName = true,
        public readonly string $activeTheme = 'default',
        public readonly string $homepageMode = 'posts',
        public readonly string $homepagePageId = '',
        public readonly string $postsPerPage = '10',
        public readonly bool $sanitizeContentHtml = false,
        public readonly string $timezone = 'UTC',
        public readonly string $dateFormat = 'F j, Y',
        public readonly string $timeFormat = 'g:i A',
        public readonly string $cacheDriver = 'file',
        public readonly string $mailTransport = 'php_mail',
        public readonly string $mailFromName = '',
        public readonly string $mailFromEmail = '',
        public readonly string $smtpHost = '',
        public readonly string $smtpPort = '',
        public readonly string $smtpEncryption = 'tls',
        public readonly string $smtpUsername = '',
        public readonly string $smtpPassword = '',
        public readonly string $smtpTimeoutSeconds = '',
        public readonly string $testEmailRecipient = '',
    ) {
    }

    /**
     * @param array<string, mixed> $siteConfig
     * @param array<string, mixed> $cacheConfig
     * @param array<string, mixed> $mailConfig
     */
    public static function fromConfig(array $siteConfig, array $cacheConfig, array $mailConfig): self
    {
        $smtp = isset($mailConfig['smtp']) && is_array($mailConfig['smtp']) ? $mailConfig['smtp'] : [];

        return new self(
            siteName: self::stringValue($siteConfig['site_name'] ?? ''),
            tagline: self::stringValue($siteConfig['tagline'] ?? ''),
            siteUrl: self::stringValue($siteConfig['site_url'] ?? ''),
            siteMetaDescription: self::stringValue($siteConfig['site_meta_description'] ?? ''),
            siteSocialImage: self::stringValue($siteConfig['site_social_image'] ?? ''),
            siteLogo: self::stringValue($siteConfig['site_logo'] ?? ''),
            siteLogoShowName: self::boolValue($siteConfig['site_logo_show_name'] ?? true),
            activeTheme: self::stringValue($siteConfig['active_theme'] ?? 'default'),
            homepageMode: self::stringValue($siteConfig['homepage_mode'] ?? 'posts'),
            homepagePageId: self::stringValue($siteConfig['homepage_page_id'] ?? ''),
            postsPerPage: self::stringValue((string) ($siteConfig['posts_per_page'] ?? '10')),
            sanitizeContentHtml: self::boolValue($siteConfig['sanitize_content_html'] ?? false),
            timezone: self::stringValue($siteConfig['timezone'] ?? 'UTC'),
            dateFormat: self::stringValue($siteConfig['date_format'] ?? 'F j, Y'),
            timeFormat: self::stringValue($siteConfig['time_format'] ?? 'g:i A'),
            cacheDriver: self::stringValue($cacheConfig['driver'] ?? 'file'),
            mailTransport: self::stringValue($mailConfig['transport'] ?? 'php_mail'),
            mailFromName: self::stringValue($mailConfig['from_name'] ?? '') !== '' ? self::stringValue($mailConfig['from_name'] ?? '') : self::stringValue($siteConfig['site_name'] ?? ''),
            mailFromEmail: self::defaultFromEmail(self::stringValue($mailConfig['from_email'] ?? ''), self::stringValue($siteConfig['site_url'] ?? '')),
            smtpHost: self::stringValue($smtp['host'] ?? ''),
            smtpPort: self::stringValue((string) ($smtp['port'] ?? '587')),
            smtpEncryption: self::stringValue($smtp['encryption'] ?? 'tls'),
            smtpUsername: self::stringValue($smtp['username'] ?? ''),
            smtpPassword: self::stringValue($smtp['password'] ?? ''),
            smtpTimeoutSeconds: self::stringValue((string) ($smtp['timeout_seconds'] ?? '15')),
            testEmailRecipient: '',
        );
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function fromPost(array $post): self
    {
        return new self(
            siteName: self::stringValue($post['site_name'] ?? ''),
            tagline: self::stringValue($post['tagline'] ?? ''),
            siteUrl: self::stringValue($post['site_url'] ?? ''),
            siteMetaDescription: self::stringValue($post['site_meta_description'] ?? ''),
            siteSocialImage: self::stringValue($post['site_social_image'] ?? ''),
            siteLogo: self::stringValue($post['site_logo'] ?? ''),
            siteLogoShowName: self::boolValue($post['site_logo_show_name'] ?? false),
            activeTheme: self::stringValue($post['active_theme'] ?? 'default'),
            homepageMode: self::stringValue($post['homepage_mode'] ?? 'posts'),
            homepagePageId: self::stringValue($post['homepage_page_id'] ?? ''),
            postsPerPage: self::stringValue($post['posts_per_page'] ?? '10'),
            sanitizeContentHtml: self::boolValue($post['sanitize_content_html'] ?? false),
            timezone: self::stringValue($post['timezone'] ?? 'UTC'),
            dateFormat: self::stringValue($post['date_format'] ?? 'F j, Y'),
            timeFormat: self::stringValue($post['time_format'] ?? 'g:i A'),
            cacheDriver: self::stringValue($post['cache_driver'] ?? 'file'),
            mailTransport: self::stringValue($post['mail_transport'] ?? 'php_mail'),
            mailFromName: self::stringValue($post['mail_from_name'] ?? ''),
            mailFromEmail: self::stringValue($post['mail_from_email'] ?? ''),
            smtpHost: self::stringValue($post['smtp_host'] ?? ''),
            smtpPort: self::stringValue($post['smtp_port'] ?? ''),
            smtpEncryption: self::stringValue($post['smtp_encryption'] ?? 'tls'),
            smtpUsername: self::stringValue($post['smtp_username'] ?? ''),
            smtpPassword: self::stringValue($post['smtp_password'] ?? ''),
            smtpTimeoutSeconds: self::stringValue($post['smtp_timeout_seconds'] ?? ''),
            testEmailRecipient: self::stringValue($post['test_email_recipient'] ?? ''),
        );
    }

    private static function stringValue(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    private static function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (!is_string($value)) {
            return false;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    private static function defaultFromEmail(string $configured, string $siteUrl): string
    {
        if ($configured !== '') {
            return $configured;
        }

        if ($siteUrl === '') {
            return '';
        }

        $host = parse_url($siteUrl, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return '';
        }

        $host = preg_replace('/^www\./i', '', $host);
        return is_string($host) && $host !== '' ? 'hello@' . $host : '';
    }
}
