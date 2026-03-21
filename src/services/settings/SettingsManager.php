<?php

declare(strict_types=1);

namespace Glyph\services\settings;

use Glyph\adapters\storage\PhpConfigWriter;

final class SettingsManager
{
    public function __construct(
        private readonly PhpConfigWriter $configWriter,
        private readonly string $systemPath,
    ) {
    }

    public function save(SettingsInput $input, bool $isApcuAvailable): void
    {
        $cacheDriver = $input->cacheDriver === 'apcu' && $isApcuAvailable ? 'apcu' : 'file';
        $postsPerPage = ctype_digit($input->postsPerPage) ? (int) $input->postsPerPage : 10;
        $postsPerPage = max(1, min(100, $postsPerPage));

        $this->configWriter->write(
            $this->systemPath . '/site.php',
            [
                'site_name' => $input->siteName,
                'tagline' => $input->tagline,
                'site_url' => $input->siteUrl,
                'site_meta_description' => $input->siteMetaDescription,
                'site_social_image' => $input->siteSocialImage,
                'site_logo' => $input->siteLogo,
                'site_logo_show_name' => $input->siteLogoShowName,
                'active_theme' => $input->activeTheme,
                'homepage_mode' => $input->homepageMode,
                'homepage_page_id' => $input->homepagePageId,
                'posts_per_page' => $postsPerPage,
                'sanitize_content_html' => $input->sanitizeContentHtml,
                'timezone' => $input->timezone,
                'date_format' => $input->dateFormat,
                'time_format' => $input->timeFormat,
            ],
        );

        $this->configWriter->write(
            $this->systemPath . '/cache.php',
            [
                'driver' => $cacheDriver,
                'apcu_enabled' => $cacheDriver === 'apcu',
            ],
        );

        $this->configWriter->write(
            $this->systemPath . '/mail.php',
            [
                'transport' => $input->mailTransport,
                'from_name' => $input->mailFromName,
                'from_email' => $input->mailFromEmail,
                'smtp' => [
                    'host' => $input->smtpHost,
                    'port' => (int) $input->smtpPort,
                    'encryption' => $input->smtpEncryption,
                    'username' => $input->smtpUsername,
                    'password' => $input->smtpPassword,
                    'timeout_seconds' => (int) $input->smtpTimeoutSeconds,
                ],
            ],
        );
    }
}
