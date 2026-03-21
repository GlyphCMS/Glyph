<?php

declare(strict_types=1);

namespace Glyph\services\install;

use Glyph\domain\shared\Text;

final class InstallValidator
{
    private const MINIMUM_PASSWORD_LENGTH = 12;
    private const MAXIMUM_SITE_NAME_LENGTH = 120;
    private const MAXIMUM_SITE_URL_LENGTH = 255;
    private const MAXIMUM_ADMIN_EMAIL_LENGTH = 255;

    public function validate(InstallInput $input, bool $isApcuAvailable): ValidationResult
    {
        $fieldErrors = [];

        if ($input->siteName === '') {
            $fieldErrors['site_name'] = 'Site name is required.';
        } elseif (Text::length($input->siteName) > self::MAXIMUM_SITE_NAME_LENGTH) {
            $fieldErrors['site_name'] = 'Site name is too long.';
        }

        if ($input->siteUrl === '') {
            $fieldErrors['site_url'] = 'Site URL is required.';
        } elseif (Text::length($input->siteUrl) > self::MAXIMUM_SITE_URL_LENGTH) {
            $fieldErrors['site_url'] = 'Site URL is too long.';
        } elseif (!$this->isValidHttpUrl($input->siteUrl)) {
            $fieldErrors['site_url'] = 'Site URL must be a valid http or https URL.';
        }

        if ($input->adminEmail === '') {
            $fieldErrors['admin_email'] = 'Admin email is required.';
        } elseif (Text::length($input->adminEmail) > self::MAXIMUM_ADMIN_EMAIL_LENGTH) {
            $fieldErrors['admin_email'] = 'Admin email is too long.';
        } elseif (filter_var($input->adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            $fieldErrors['admin_email'] = 'Admin email must be a valid email address.';
        }

        if ($input->password === '') {
            $fieldErrors['password'] = 'Password is required.';
        } elseif (Text::length($input->password) < self::MINIMUM_PASSWORD_LENGTH) {
            $fieldErrors['password'] = sprintf(
                'Password must be at least %d characters.',
                self::MINIMUM_PASSWORD_LENGTH
            );
        }

        if ($input->passwordConfirmation === '') {
            $fieldErrors['password_confirmation'] = 'Password confirmation is required.';
        } elseif ($input->password !== $input->passwordConfirmation) {
            $fieldErrors['password_confirmation'] = 'Password confirmation does not match.';
        }

        if (!in_array($input->cacheDriver, ['file', 'apcu'], true)) {
            $fieldErrors['cache_driver'] = 'Invalid cache driver selected.';
        } elseif ($input->cacheDriver === 'apcu' && !$isApcuAvailable) {
            $fieldErrors['cache_driver'] = 'APCu is not available on this server.';
        }

        return new ValidationResult($fieldErrors);
    }

    private function isValidHttpUrl(string $url): bool
    {
        $validatedUrl = filter_var($url, FILTER_VALIDATE_URL);

        if (!is_string($validatedUrl)) {
            return false;
        }

        $scheme = parse_url($validatedUrl, PHP_URL_SCHEME);

        return $scheme === 'http' || $scheme === 'https';
    }
}
