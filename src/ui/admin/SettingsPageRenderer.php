<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\services\settings\SettingsInput;
use Glyph\services\settings\SettingsValidationResult;
use Glyph\ui\shared\DocumentRenderer;

final class SettingsPageRenderer
{
    /**
     * @param array<string, string> $availableHomepagePages
     */
    public function render(
        SettingsInput $input,
        SettingsValidationResult $validationResult,
        bool $isApcuAvailable,
        array $availableHomepagePages,
        string $saveCsrfToken,
        string $testEmailCsrfToken,
        string $mediaUploadCsrfToken,
        ?string $successMessage,
        ?string $errorMessage,
    ): string {
        $document = new DocumentRenderer();
        $showSmtp = $input->mailTransport === 'smtp';
        $showHomepagePage = $input->homepageMode === 'page';
        $canSendTest = $input->mailFromName !== '' && $input->mailFromEmail !== '';

        $content = '<main class="page-shell">';
        $content .= '<section class="hero" style="margin-bottom:1.25rem"><div class="toolbar"><h1 class="hero__title">Site Settings</h1><a class="button button-secondary" href="/admin">Dashboard</a></div></section>';

        $content .= '<form method="post" action="/admin/settings">';
        $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($saveCsrfToken) . '">';

        $content .= '<div class="settings-layout">';
        $content .= '<div class="stack">';

        if ($successMessage) {
            $content .= '<div class="notice notice--success"><p><strong>' . $document->escape($successMessage) . '</strong></p></div>';
        }
        if ($errorMessage) {
            $content .= '<div class="notice notice--error"><p><strong>' . $document->escape($errorMessage) . '</strong></p></div>';
        }

        $content .= '<section class="panel stack">';
        $content .= '<div><h2 class="page-title">Site Identity</h2><p class="page-subtitle">Your site name, tagline, URL, and default SEO metadata.</p></div>';
        $content .= '<div class="form-grid form-grid--two">';
        $content .= $this->renderField('Site Name', 'site_name', 'text', $input->siteName, $validationResult->firstError('site_name'), $document);
        $content .= $this->renderField('Tagline', 'tagline', 'text', $input->tagline, $validationResult->firstError('tagline'), $document);
        $content .= $this->renderField('Site URL', 'site_url', 'text', $input->siteUrl, $validationResult->firstError('site_url'), $document);
        $content .= $this->renderField('Site Meta Description', 'site_meta_description', 'text', $input->siteMetaDescription, $validationResult->firstError('site_meta_description'), $document);
        $content .= $this->renderField('Site Social Image', 'site_social_image', 'text', $input->siteSocialImage, $validationResult->firstError('site_social_image'), $document);
        $content .= $this->renderSiteLogoField($input, $validationResult->firstError('site_logo'), $mediaUploadCsrfToken, $document);
        $content .= '</div>';
        $content .= '</section>';

        $content .= '<section class="panel stack">';
        $content .= '<div><h2 class="page-title">Homepage</h2><p class="page-subtitle">Choose whether your homepage shows the latest posts or a single published page.</p></div>';
        $content .= '<div class="form-grid form-grid--two">';
        $content .= $this->renderSelect('Homepage Mode', 'homepage_mode', $input->homepageMode, ['posts' => 'Latest posts', 'page' => 'Static page'], $validationResult->firstError('homepage_mode'), $document, 'js-homepage-mode');
        $homepagePostsClass = $showHomepagePage ? 'js-homepage-posts is-hidden' : 'js-homepage-posts';
        $content .= '<div class="' . $homepagePostsClass . '">';
        $content .= $this->renderField('Posts Per Page', 'posts_per_page', 'text', $input->postsPerPage, $validationResult->firstError('posts_per_page'), $document);
        $content .= '</div>';
        $homepagePageClass = $showHomepagePage ? 'js-homepage-page' : 'js-homepage-page is-hidden';
        $content .= '<div class="' . $homepagePageClass . '">';
        $content .= $this->renderSelect('Homepage Page', 'homepage_page_id', $input->homepagePageId, $availableHomepagePages, $validationResult->firstError('homepage_page_id'), $document);
        $content .= '</div>';
        $content .= '</div>';
        $content .= '</section>';

        $content .= '<section class="panel stack">';
        $content .= '<div><h2 class="page-title">Date, Time &amp; Content</h2><p class="page-subtitle">Timezone, display format, and HTML sanitization policy.</p></div>';
        $content .= '<div class="form-grid form-grid--two">';
        $content .= $this->renderField('Date Format', 'date_format', 'text', $input->dateFormat, $validationResult->firstError('date_format'), $document);
        $content .= $this->renderField('Time Format', 'time_format', 'text', $input->timeFormat, $validationResult->firstError('time_format'), $document);
        $content .= $this->renderTimezoneSelect($input->timezone, $validationResult->firstError('timezone'), $document);
        $content .= '<div class="field"><div class="helper-card"><p class="helper-card__title">Date/time preview</p><p class="muted">' . $document->escape($this->previewDateTime($input->dateFormat, $input->timeFormat, $input->timezone)) . '</p></div></div>';
        $content .= '</div>';
        $content .= $this->renderToggleRow(
            'Sanitize body HTML before saving',
            'sanitize_content_html',
            $input->sanitizeContentHtml,
            'When enabled, page and post body HTML is sanitized on save. Administrators and owners can still bypass this per-entry.',
            $validationResult->firstError('sanitize_content_html'),
            $document,
        );
        $content .= '</section>';

        $content .= '<section class="panel stack">';
        $content .= '<div><h2 class="page-title">Cache</h2><p class="page-subtitle">Choose how Glyph stores cache now, with room for extra drivers from plugins later.</p></div>';
        $content .= $this->renderCacheDriverField($input->cacheDriver, $isApcuAvailable, $validationResult->firstError('cache_driver'), $document);
        $content .= '</section>';

        $content .= '<section class="panel stack">';
        $content .= '<div><h2 class="page-title">Email</h2><p class="page-subtitle">Configure outbound mail transport and verify delivery with a test send.</p></div>';
        $content .= '<div class="form-grid form-grid--two">';
        $content .= $this->renderSelect('Transport', 'mail_transport', $input->mailTransport, ['php_mail' => 'PHP mail()', 'smtp' => 'SMTP'], $validationResult->firstError('mail_transport'), $document, 'js-mail-transport');
        $content .= $this->renderField('From Name', 'mail_from_name', 'text', $input->mailFromName, $validationResult->firstError('mail_from_name'), $document);
        $content .= $this->renderField('From Email', 'mail_from_email', 'email', $input->mailFromEmail, $validationResult->firstError('mail_from_email'), $document);
        $smtpClass = $showSmtp ? 'js-smtp-fields field--full' : 'js-smtp-fields field--full is-hidden';
        $content .= '<div class="field ' . $smtpClass . '"><div class="form-grid form-grid--two">';
        $content .= $this->renderField('SMTP Host', 'smtp_host', 'text', $input->smtpHost, $validationResult->firstError('smtp_host'), $document);
        $content .= $this->renderField('SMTP Port', 'smtp_port', 'text', $input->smtpPort, $validationResult->firstError('smtp_port'), $document);
        $content .= $this->renderSelect('Encryption', 'smtp_encryption', $input->smtpEncryption, ['none' => 'None', 'tls' => 'TLS', 'ssl' => 'SSL'], $validationResult->firstError('smtp_encryption'), $document);
        $content .= $this->renderField('Username', 'smtp_username', 'text', $input->smtpUsername, $validationResult->firstError('smtp_username'), $document);
        $content .= $this->renderField('Password', 'smtp_password', 'password', $input->smtpPassword, $validationResult->firstError('smtp_password'), $document);
        $content .= $this->renderField('Timeout (seconds)', 'smtp_timeout_seconds', 'text', $input->smtpTimeoutSeconds, $validationResult->firstError('smtp_timeout_seconds'), $document);
        $content .= '</div></div>';
        $content .= '</div>';

        if ($canSendTest) {
            $content .= '<div class="settings-test-email">';
            $content .= '<p class="kicker">Send a test</p>';
            $content .= '<div class="form-grid form-grid--two">';
            $content .= '<div class="field">';
            $content .= '<label for="test_email_recipient">Recipient</label>';
            $content .= '<input id="test_email_recipient" name="test_email_recipient" type="email" value="' . $document->escape($input->testEmailRecipient) . '" placeholder="you@example.com" form="test-email-form">';
            if ($validationResult->firstError('test_email_recipient')) {
                $content .= '<small class="field-error">' . $document->escape($validationResult->firstError('test_email_recipient')) . '</small>';
            }
            $content .= '</div>';
            $content .= '<div class="field" style="align-self:end"><button type="submit" form="test-email-form" class="button button-secondary" style="width:100%">Send Test Email</button></div>';
            $content .= '</div>';
            $content .= '</div>';
        }

        $content .= '</section>';

        $content .= '</div>';

        $content .= '<aside class="settings-sidebar">';
        $content .= '<div class="settings-sidebar__sticky">';
        $content .= '<div class="sidebar-section">';
        $content .= '<h3 class="sidebar-section__title">Save</h3>';
        $content .= '<button type="submit" class="button-publish">Save Settings</button>';
        $content .= '<p class="footer-note" style="margin:0.6rem 0 0;font-size:0.78rem">Written to <code>data/system/</code> - safe across upgrades.</p>';
        $content .= '</div>';
        $content .= '</div>';
        $content .= '</aside>';

        $content .= '</div>';
        $content .= '</form>';

        if ($canSendTest) {
            $content .= '<form id="test-email-form" method="post" action="/admin/settings/test-email" style="display:none">';
            $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($testEmailCsrfToken) . '">';
            $content .= '</form>';
        }

        $content .= $this->script();
        $content .= '</main>';

        return $document->render('Glyph Settings', $content, 'Manage site, cache, and mail settings for Glyph.', 'theme-admin');
    }

    private function renderSiteLogoField(SettingsInput $input, ?string $error, string $uploadCsrfToken, DocumentRenderer $document): string
    {
        $field = '<div class="field field--full site-logo-field">';
        $field .= '<div class="field-help-row"><label for="site_logo">Site Logo</label><small class="field-help">Upload a logo mark or full logo for the frontend header and footer.</small></div>';
        $field .= '<div class="editor-action-row"><input id="site_logo" name="site_logo" type="text" value="' . $document->escape($input->siteLogo) . '" placeholder="/uploads/images/..." autocomplete="off"><input id="site-logo-upload-input" type="file" accept="image/jpeg,image/png,image/gif,image/webp"></div>';
        $field .= '<input id="site-logo-upload-token" type="hidden" value="' . $document->escape($uploadCsrfToken) . '">';
        $field .= '<div id="site-logo-upload-status" class="featured-image-upload-status muted" data-tone="neutral" aria-live="polite">Upload a PNG, JPG, GIF, or WebP logo.</div>';
        $field .= '<div class="site-logo-identity-row">';
        $field .= '<div class="toggle-row site-logo-field__toggle">';
        $field .= '<div class="toggle-row__body">';
        $field .= '<label for="site_logo_show_name" class="toggle-row__label">Show site name text next to the logo</label>';
        $field .= '<p class="toggle-row__help">Turn this off if your uploaded logo already includes your full site name.</p>';
        $field .= '</div>';
        $field .= '<label class="toggle-switch">';
        $field .= '<input type="checkbox" id="site_logo_show_name" name="site_logo_show_name" value="1"' . ($input->siteLogoShowName ? ' checked' : '') . '>';
        $field .= '<span class="toggle-switch__track"><span class="toggle-switch__thumb"></span></span>';
        $field .= '</label>';
        $field .= '</div>';
        $field .= '<div class="site-logo-preview' . ($input->siteLogo === '' ? ' site-logo-preview--empty' : '') . '" id="site-logo-preview-shell"><img id="site-logo-preview" src="' . $document->escape($input->siteLogo) . '" alt="Site logo preview"><span class="site-logo-preview__empty">No site logo uploaded yet.</span></div>';
        $field .= '</div>';
        if ($error) {
            $field .= '<small class="field-error">' . $document->escape($error) . '</small>';
        }
        return $field . '</div>';
    }

    private function renderCacheDriverField(string $currentValue, bool $isApcuAvailable, ?string $error, DocumentRenderer $document): string
    {
        $definitions = $this->cacheDriverDefinitions($isApcuAvailable);
        $selectedValue = $this->resolveSelectedCacheDriver($currentValue, $definitions);

        $field = '<div class="field field--full">';
        $field .= '<div class="cache-driver-grid" role="radiogroup" aria-label="Cache Driver">';

        foreach ($definitions as $definition) {
            $value = $definition['value'];
            $isSelected = $value === $selectedValue;
            $isDisabled = $definition['available'] !== true;
            $classes = 'cache-driver-card';
            if ($isDisabled) {
                $classes .= ' is-disabled';
            }

            $field .= '<label class="' . $classes . '">';
            $field .= '<input class="cache-driver-card__input" type="radio" name="cache_driver" value="' . $document->escape($value) . '"' . ($isSelected ? ' checked' : '') . ($isDisabled ? ' disabled' : '') . '>';
            $field .= '<span class="cache-driver-card__frame">';
            $field .= '<span class="cache-driver-card__topline">';
            $field .= '<span class="cache-driver-card__title-group">';
            $field .= '<span class="cache-driver-card__title">' . $document->escape($definition['label']) . '</span>';
            $field .= '<span class="cache-driver-card__summary">' . $document->escape($definition['summary']) . '</span>';
            $field .= '</span>';
            if ($definition['badges'] !== []) {
                $field .= '<span class="cache-driver-card__badges">';
                foreach ($definition['badges'] as $badge) {
                    $badgeClass = 'cache-driver-card__badge';
                    if (($badge['tone'] ?? '') !== '') {
                        $badgeClass .= ' cache-driver-card__badge--' . $badge['tone'];
                    }
                    $field .= '<span class="' . $badgeClass . '">' . $document->escape($badge['label']) . '</span>';
                }
                $field .= '</span>';
            }
            $field .= '</span>';
            $field .= '<span class="cache-driver-card__meta">' . $document->escape($definition['meta']) . '</span>';
            $field .= '</span>';
            $field .= '</label>';
        }

        $field .= '</div>';
        if ($error) {
            $field .= '<small class="field-error">' . $document->escape($error) . '</small>';
        }
        $field .= '</div>';

        return $field;
    }

    /**
     * @return list<array{value:string,label:string,summary:string,meta:string,available:bool,badges:list<array{label:string,tone:string}>}>
     */
    private function cacheDriverDefinitions(bool $isApcuAvailable): array
    {
        return [
            [
                'value' => 'file',
                'label' => 'File Cache',
                'summary' => 'Safe caching that works on every install.',
                'meta' => 'Stores cache on disk and needs no extra PHP extension.',
                'available' => true,
                'badges' => [],
            ],
            [
                'value' => 'apcu',
                'label' => 'APCu',
                'summary' => 'Fast in-memory cache for single-server setups.',
                'meta' => $isApcuAvailable
                    ? 'Recommended on this server because the APCu extension is available.'
                    : 'Unavailable right now because the APCu PHP extension is not loaded.',
                'available' => $isApcuAvailable,
                'badges' => $isApcuAvailable
                    ? [
                        ['label' => 'Recommended', 'tone' => 'success'],
                    ]
                    : [
                        ['label' => 'Unavailable', 'tone' => 'warning'],
                    ],
            ],
        ];
    }

    /**
     * @param list<array{value:string,available:bool}> $definitions
     */
    private function resolveSelectedCacheDriver(string $currentValue, array $definitions): string
    {
        foreach ($definitions as $definition) {
            if ($definition['value'] === $currentValue && $definition['available'] === true) {
                return $currentValue;
            }
        }

        foreach ($definitions as $definition) {
            if ($definition['available'] === true) {
                return $definition['value'];
            }
        }

        return 'file';
    }


    private function renderField(string $label, string $name, string $type, string $value, ?string $error, DocumentRenderer $document): string
    {
        $field = '<div class="field"><label for="' . $document->escape($name) . '">' . $document->escape($label) . '</label><input id="' . $document->escape($name) . '" name="' . $document->escape($name) . '" type="' . $document->escape($type) . '" value="' . $document->escape($value) . '">';
        if ($error) {
            $field .= '<small class="field-error">' . $document->escape($error) . '</small>';
        }
        return $field . '</div>';
    }

    private function renderToggleRow(string $label, string $name, bool $checked, string $helpText, ?string $error, DocumentRenderer $document): string
    {
        $field  = '<div class="toggle-row">';
        $field .= '<div class="toggle-row__body">';
        $field .= '<label for="' . $document->escape($name) . '" class="toggle-row__label">' . $document->escape($label) . '</label>';
        $field .= '<p class="toggle-row__help">' . $document->escape($helpText) . '</p>';
        if ($error) {
            $field .= '<small class="field-error">' . $document->escape($error) . '</small>';
        }
        $field .= '</div>';
        $field .= '<label class="toggle-switch">';
        $field .= '<input type="checkbox" id="' . $document->escape($name) . '" name="' . $document->escape($name) . '" value="1"' . ($checked ? ' checked' : '') . '>';
        $field .= '<span class="toggle-switch__track"><span class="toggle-switch__thumb"></span></span>';
        $field .= '</label>';
        $field .= '</div>';
        return $field;
    }

    /** @param array<string, string> $options */
    private function renderSelect(string $label, string $name, string $currentValue, array $options, ?string $error, DocumentRenderer $document, string $className = ''): string
    {
        $field = '<div class="field"><label for="' . $document->escape($name) . '">' . $document->escape($label) . '</label><select class="' . $document->escape($className) . '" id="' . $document->escape($name) . '" name="' . $document->escape($name) . '">';
        foreach ($options as $value => $optionLabel) {
            $field .= '<option value="' . $document->escape($value) . '"' . ($value === $currentValue ? ' selected' : '') . '>' . $document->escape($optionLabel) . '</option>';
        }
        $field .= '</select>';
        if ($error) {
            $field .= '<small class="field-error">' . $document->escape($error) . '</small>';
        }
        return $field . '</div>';
    }

    private function renderTimezoneSelect(string $currentValue, ?string $error, DocumentRenderer $document): string
    {
        $preferred = ['UTC','America/New_York','America/Chicago','America/Denver','America/Los_Angeles','Europe/London','Europe/Berlin','Australia/Sydney'];
        $all = timezone_identifiers_list();

        $field = '<div class="field"><label for="timezone">Timezone</label><select id="timezone" name="timezone">';
        foreach ($preferred as $zone) {
            $field .= '<option value="' . $document->escape($zone) . '"' . ($zone === $currentValue ? ' selected' : '') . '>' . $document->escape($zone) . '</option>';
        }
        $field .= '<option value="" disabled>----------</option>';
        foreach ($all as $zone) {
            if (in_array($zone, $preferred, true)) {
                continue;
            }
            $field .= '<option value="' . $document->escape($zone) . '"' . ($zone === $currentValue ? ' selected' : '') . '>' . $document->escape($zone) . '</option>';
        }
        $field .= '</select>';
        if ($error) {
            $field .= '<small class="field-error">' . $document->escape($error) . '</small>';
        }
        return $field . '</div>';
    }

    private function previewDateTime(string $dateFormat, string $timeFormat, string $timezone): string
    {
        try {
            return (new \DateTimeImmutable('2026-03-08T16:00:00Z'))->setTimezone(new \DateTimeZone($timezone))->format($dateFormat . ' ' . $timeFormat);
        } catch (\Throwable) {
            return 'Invalid date/time format preview.';
        }
    }

    private function script(): string
    {
        return '<script>
(() => {
    const transport = document.querySelector(".js-mail-transport");
    const smtpFields = document.querySelector(".js-smtp-fields");
    const homepageMode = document.querySelector(".js-homepage-mode");
    const homepagePosts = document.querySelector(".js-homepage-posts");
    const homepagePage = document.querySelector(".js-homepage-page");
    const logoField = document.getElementById("site_logo");
    const logoPreview = document.getElementById("site-logo-preview");
    const logoPreviewShell = document.getElementById("site-logo-preview-shell");
    const logoUploadInput = document.getElementById("site-logo-upload-input");
    const logoUploadToken = document.getElementById("site-logo-upload-token");
    const logoUploadStatus = document.getElementById("site-logo-upload-status");

    const toggleSmtp = () => {
        if (!transport || !smtpFields) return;
        smtpFields.classList.toggle("is-hidden", transport.value !== "smtp");
    };

    const toggleHomepagePage = () => {
        if (!homepageMode) return;
        if (homepagePosts) {
            homepagePosts.classList.toggle("is-hidden", homepageMode.value !== "posts");
        }
        if (homepagePage) {
            homepagePage.classList.toggle("is-hidden", homepageMode.value !== "page");
        }
    };

    const syncLogoPreview = () => {
        if (!logoField || !logoPreview || !logoPreviewShell) return;
        const value = logoField.value.trim();
        logoPreview.src = value;
        logoPreviewShell.classList.toggle("site-logo-preview--empty", value === "");
    };

    const setLogoStatus = (message, tone = "neutral") => {
        if (!logoUploadStatus) return;
        logoUploadStatus.textContent = message;
        logoUploadStatus.dataset.tone = tone;
    };

    const uploadLogo = async () => {
        if (!logoUploadInput || !logoUploadToken || !logoField) return;
        const files = logoUploadInput.files;
        if (!files || files.length === 0) {
            setLogoStatus("Choose a logo to upload.", "error");
            return;
        }

        logoUploadInput.disabled = true;
        setLogoStatus("Uploading logo...", "neutral");

        try {
            const body = new FormData();
            body.append("_csrf_token", logoUploadToken.value || "");
            body.append("media_file", files[0]);

            const response = await fetch("/admin/media/upload/browser", {
                method: "POST",
                body,
            });
            const payload = await response.json();
            if (!response.ok || !payload.ok || !payload.item) {
                throw new Error(payload.message || "The upload failed.");
            }

            const uploadedPath = payload.item.public_path || payload.item.path || payload.public_path || payload.path || "";
            if (uploadedPath === "") {
                throw new Error("The logo uploaded, but no usable image path was returned.");
            }

            logoField.value = uploadedPath;
            logoField.dispatchEvent(new Event("input", { bubbles: true }));
            logoUploadInput.value = "";
            setLogoStatus("Logo uploaded.", "success");
        } catch (error) {
            const message = error instanceof Error ? error.message : "The upload failed.";
            setLogoStatus(message, "error");
        } finally {
            logoUploadInput.disabled = false;
        }
    };

    if (transport) {
        transport.addEventListener("change", toggleSmtp);
        toggleSmtp();
    }
    if (homepageMode) {
        homepageMode.addEventListener("change", toggleHomepagePage);
        toggleHomepagePage();
    }
    if (logoField) {
        logoField.addEventListener("input", syncLogoPreview);
        syncLogoPreview();
    }
    if (logoUploadInput) {
        logoUploadInput.addEventListener("change", uploadLogo);
    }
})();
</script>';
    }
}








