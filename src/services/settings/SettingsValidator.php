<?php

declare(strict_types=1);

namespace Glyph\services\settings;

use Glyph\domain\shared\Text;

final class SettingsValidator
{
    private const MAX_SITE_NAME_LENGTH = 120;
    private const MAX_TAGLINE_LENGTH = 220;
    private const MAX_SITE_URL_LENGTH = 255;
    private const MAX_SITE_META_DESCRIPTION_LENGTH = 320;
    private const MAX_SITE_SOCIAL_IMAGE_LENGTH = 255;
    private const MAX_SITE_LOGO_LENGTH = 255;
    private const MAX_DATE_TIME_FORMAT_LENGTH = 60;
    private const MAX_FROM_NAME_LENGTH = 120;
    private const MAX_SMTP_HOST_LENGTH = 255;
    private const MAX_SMTP_USERNAME_LENGTH = 255;
    private const MAX_SMTP_PASSWORD_LENGTH = 255;

    /**
     * @param list<string> $availableThemes
     * @param list<string> $availableHomepagePageIds
     */
    public function validate(SettingsInput $input, bool $isApcuAvailable, array $availableThemes, array $availableHomepagePageIds): SettingsValidationResult
    {
        $errors = [];

        if ($input->siteName === '') {
            $errors['site_name'] = 'Site name is required.';
        } elseif (Text::length($input->siteName) > self::MAX_SITE_NAME_LENGTH) {
            $errors['site_name'] = 'Site name is too long.';
        }

        if (Text::length($input->tagline) > self::MAX_TAGLINE_LENGTH) {
            $errors['tagline'] = 'Tagline is too long.';
        }

        if (Text::length($input->siteUrl) > self::MAX_SITE_URL_LENGTH) {
            $errors['site_url'] = 'Site URL is too long.';
        } elseif ($input->siteUrl !== '' && filter_var($input->siteUrl, FILTER_VALIDATE_URL) === false) {
            $errors['site_url'] = 'Site URL must be a valid absolute URL.';
        }

        if (Text::length($input->siteMetaDescription) > self::MAX_SITE_META_DESCRIPTION_LENGTH) {
            $errors['site_meta_description'] = 'Site meta description is too long.';
        }

        if (Text::length($input->siteSocialImage) > self::MAX_SITE_SOCIAL_IMAGE_LENGTH) {
            $errors['site_social_image'] = 'Social image path is too long.';
        } elseif ($input->siteSocialImage !== '' && !$this->isSafeRelativeImagePath($input->siteSocialImage)) {
            $errors['site_social_image'] = 'Social image path is invalid.';
        }

        if (Text::length($input->siteLogo) > self::MAX_SITE_LOGO_LENGTH) {
            $errors['site_logo'] = 'Logo path is too long.';
        } elseif ($input->siteLogo !== '' && !$this->isSafeRelativeImagePath($input->siteLogo)) {
            $errors['site_logo'] = 'Logo path is invalid.';
        }

        if ($input->activeTheme === '' || !in_array($input->activeTheme, $availableThemes, true)) {
            $errors['active_theme'] = 'Active theme is invalid.';
        }

        if (!in_array($input->homepageMode, ['posts', 'page'], true)) {
            $errors['homepage_mode'] = 'Homepage mode is invalid.';
        }

        if ($input->homepageMode === 'page') {
            if ($input->homepagePageId === '') {
                $errors['homepage_page_id'] = 'Choose a homepage page.';
            } elseif (!in_array($input->homepagePageId, $availableHomepagePageIds, true)) {
                $errors['homepage_page_id'] = 'Homepage page is invalid.';
            }
        }

        if (!ctype_digit($input->postsPerPage) || (int) $input->postsPerPage < 1 || (int) $input->postsPerPage > 100) {
            $errors['posts_per_page'] = 'Posts per page must be between 1 and 100.';
        }

        if ($input->timezone === '' || !in_array($input->timezone, timezone_identifiers_list(), true)) {
            $errors['timezone'] = 'Timezone is invalid.';
        }

        if ($input->dateFormat === '' || Text::length($input->dateFormat) > self::MAX_DATE_TIME_FORMAT_LENGTH) {
            $errors['date_format'] = 'Date format is invalid.';
        }

        if ($input->timeFormat === '' || Text::length($input->timeFormat) > self::MAX_DATE_TIME_FORMAT_LENGTH) {
            $errors['time_format'] = 'Time format is invalid.';
        }

        if (!in_array($input->cacheDriver, ['file', 'apcu'], true)) {
            $errors['cache_driver'] = 'Cache driver is invalid.';
        } elseif ($input->cacheDriver === 'apcu' && !$isApcuAvailable) {
            $errors['cache_driver'] = 'APCu is not available on this server.';
        }

        if (!in_array($input->mailTransport, ['php_mail', 'smtp'], true)) {
            $errors['mail_transport'] = 'Mail transport is invalid.';
        }

        if ($input->mailFromName === '') {
            $errors['mail_from_name'] = 'From name is required.';
        } elseif (Text::length($input->mailFromName) > self::MAX_FROM_NAME_LENGTH) {
            $errors['mail_from_name'] = 'From name is too long.';
        }

        if ($input->mailFromEmail === '') {
            $errors['mail_from_email'] = 'From email is required.';
        } elseif (filter_var($input->mailFromEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors['mail_from_email'] = 'From email must be a valid email address.';
        }

        if ($input->mailTransport === 'smtp') {
            if ($input->smtpHost === '' || Text::length($input->smtpHost) > self::MAX_SMTP_HOST_LENGTH) {
                $errors['smtp_host'] = 'SMTP host is required.';
            }

            if (!$this->isPositiveInteger($input->smtpPort)) {
                $errors['smtp_port'] = 'SMTP port must be a positive integer.';
            }

            if (!in_array($input->smtpEncryption, ['none', 'tls', 'ssl'], true)) {
                $errors['smtp_encryption'] = 'SMTP encryption is invalid.';
            }

            if (Text::length($input->smtpUsername) > self::MAX_SMTP_USERNAME_LENGTH) {
                $errors['smtp_username'] = 'SMTP username is too long.';
            }

            if (Text::length($input->smtpPassword) > self::MAX_SMTP_PASSWORD_LENGTH) {
                $errors['smtp_password'] = 'SMTP password is too long.';
            }

            if (!$this->isPositiveInteger($input->smtpTimeoutSeconds)) {
                $errors['smtp_timeout_seconds'] = 'SMTP timeout must be a positive integer.';
            }
        }

        if ($input->testEmailRecipient !== '' && filter_var($input->testEmailRecipient, FILTER_VALIDATE_EMAIL) === false) {
            $errors['test_email_recipient'] = 'Test email recipient must be a valid email address.';
        }

        return new SettingsValidationResult($errors);
    }

    private function isPositiveInteger(string $value): bool
    {
        return ctype_digit($value) && (int) $value > 0;
    }

    private function isSafeRelativeImagePath(string $path): bool
    {
        if (str_contains($path, '..')) {
            return false;
        }

        return preg_match('#^[a-zA-Z0-9/_\.-]+$#', $path) === 1;
    }
}
