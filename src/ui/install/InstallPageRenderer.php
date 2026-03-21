<?php

declare(strict_types=1);

namespace Glyph\ui\install;

use Glyph\services\install\EnvironmentCheckResult;
use Glyph\services\install\InstallInput;
use Glyph\services\install\ValidationResult;
use Glyph\ui\shared\DocumentRenderer;

final class InstallPageRenderer
{
    public function render(
        EnvironmentCheckResult $environmentCheck,
        InstallInput $input,
        ValidationResult $validationResult,
        ?string $installErrorMessage,
        string $csrfToken,
        bool $isApcuAvailable,
    ): string {
        $document = new DocumentRenderer();
        $statusHeading = $environmentCheck->isValid()
            ? 'Ready to Install'
            : 'Environment Checks Need Attention';

        $content = '<main class="centered-shell"><div class="install-card stack">';
        $content .= '<section class="hero">';
        $content .= '<p class="hero__eyebrow">Glyph Installer</p>';
        $content .= '<h1 class="hero__title">Set up your site in a few minutes.</h1>';
        $content .= '<p class="hero__text">Glyph is a flat-file CMS built for self-hosted publishing, with themes, plugins, upgrade-safe runtime configuration, and no required database.</p>';
        $content .= '</section>';

        $content .= '<section class="panel stack">';
        $content .= '<div>';
        $content .= '<p class="kicker">Installation Status</p>';
        $content .= '<h2 class="page-title">' . $document->escape($statusHeading) . '</h2>';
        $content .= '<p class="page-subtitle">Glyph checks your environment, creates runtime storage, provisions the owner account, and saves local settings without overwriting them during future updates.</p>';
        $content .= '</div>';

        if ($environmentCheck->errors() !== []) {
            $content .= $this->renderNotice('Errors', $environmentCheck->errors(), 'error', $document);
        }

        if ($environmentCheck->warnings() !== []) {
            $content .= $this->renderNotice('Warnings', $environmentCheck->warnings(), 'warning', $document);
        }

        if ($installErrorMessage !== null && $installErrorMessage !== '') {
            $content .= '<div class="notice notice--error"><p><strong>' . $document->escape($installErrorMessage) . '</strong></p></div>';
        }

        if ($environmentCheck->isValid()) {
            $content .= '<form method="post" action="/install/" class="form-grid form-grid--two">';
            $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($csrfToken) . '">';
            $content .= $this->renderField('Site Name', 'site_name', 'text', $input->siteName, $validationResult->firstError('site_name'), true, $document);
            $content .= $this->renderField('Site URL', 'site_url', 'url', $input->siteUrl, $validationResult->firstError('site_url'), true, $document);
            $content .= $this->renderField('Admin Email', 'admin_email', 'email', $input->adminEmail, $validationResult->firstError('admin_email'), true, $document);
            $content .= $this->renderField('Password', 'password', 'password', '', $validationResult->firstError('password'), true, $document);
            $content .= $this->renderField('Confirm Password', 'password_confirmation', 'password', '', $validationResult->firstError('password_confirmation'), true, $document);
            $content .= $this->renderCacheField($input->cacheDriver, $validationResult->firstError('cache_driver'), $isApcuAvailable, $document);
            $content .= '<div class="field field--full cluster">';
            $content .= '<button type="submit">Install Glyph</button>';
            $content .= '<span class="footer-note">Glyph stores install state and local settings under <code>data/system/</code> so updates do not wipe your site.</span>';
            $content .= '</div>';
            $content .= '</form>';
        }

        $content .= '</section></div></main>';

        return $document->render('Glyph Installer', $content, 'Install and configure Glyph CMS.', 'theme-install');
    }

    /**
     * @param list<string> $items
     */
    private function renderNotice(string $title, array $items, string $variant, DocumentRenderer $document): string
    {
        $body = '<div class="notice notice--' . $document->escape($variant) . '">';
        $body .= '<h3>' . $document->escape($title) . '</h3><ul>';

        foreach ($items as $item) {
            $body .= '<li>' . $document->escape($item) . '</li>';
        }

        $body .= '</ul></div>';

        return $body;
    }

    private function renderField(
        string $label,
        string $name,
        string $type,
        string $value,
        ?string $error,
        bool $required,
        DocumentRenderer $document,
    ): string {
        $field = '<div class="field">';
        $field .= '<label for="' . $document->escape($name) . '">' . $document->escape($label) . '</label>';
        $field .= '<input id="' . $document->escape($name) . '" name="' . $document->escape($name) . '" type="' . $document->escape($type) . '" value="' . $document->escape($value) . '"' . ($required ? ' required' : '') . '>';

        if ($error !== null && $error !== '') {
            $field .= '<small class="field-error">' . $document->escape($error) . '</small>';
        }

        $field .= '</div>';

        return $field;
    }

    private function renderCacheField(
        string $currentValue,
        ?string $error,
        bool $isApcuAvailable,
        DocumentRenderer $document,
    ): string {
        $selectedValue = $currentValue !== '' ? $currentValue : ($isApcuAvailable ? 'apcu' : 'file');

        $field = '<div class="field field--full">';
        $field .= '<label for="cache_driver">Cache Driver</label>';
        $field .= '<select id="cache_driver" name="cache_driver">';
        $field .= '<option value="file"' . ($selectedValue === 'file' ? ' selected' : '') . '>File cache</option>';

        if ($isApcuAvailable) {
            $field .= '<option value="apcu"' . ($selectedValue === 'apcu' ? ' selected' : '') . '>APCu cache</option>';
        }

        $field .= '</select>';

        if ($error !== null && $error !== '') {
            $field .= '<small class="field-error">' . $document->escape($error) . '</small>';
        } elseif ($isApcuAvailable) {
            $field .= '<small class="field-help">APCu is available on this server and is selected by default because it is typically faster than file caching.</small>';
        } else {
            $field .= '<small class="field-help">APCu is not available on this server, so Glyph will use file caching.</small>';
        }

        $field .= '</div>';

        return $field;
    }
}
