<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\adapters\http\Request;
use Glyph\adapters\http\Response;
use Glyph\adapters\security\CsrfTokenManager;
use Glyph\domain\auth\RoleCapabilities;
use Glyph\services\auth\AuthenticationManager;
use Glyph\services\content\ContentService;
use Glyph\services\mail\MailManager;
use Glyph\services\settings\SettingsInput;
use Glyph\services\settings\SettingsManager;
use Glyph\services\settings\SettingsValidationResult;
use Glyph\services\settings\SettingsValidator;
use Glyph\services\themes\ThemeResolver;

final class SettingsController
{
    private const MEDIA_UPLOAD_FORM_ID = MediaController::UPLOAD_FORM_ID;

    /**
     * @param array<string, mixed> $siteConfig
     * @param array<string, mixed> $cacheConfig
     * @param array<string, mixed> $mailConfig
     * @param array<string, mixed> $generatedConfig
     */
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly CsrfTokenManager $csrfTokenManager,
        private readonly SettingsManager $settingsManager,
        private readonly SettingsValidator $settingsValidator,
        private readonly ThemeResolver $themeResolver,
        private readonly ContentService $contentService,
        private readonly array $siteConfig,
        private readonly array $cacheConfig,
        private readonly array $mailConfig,
        private readonly array $generatedConfig,
    ) {
    }

    public function show(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $input = $this->currentInput();
        $successMessage = match (true) {
            $request->queryFlag('saved') => 'Settings saved successfully.',
            $request->queryFlag('sent') => 'Test email sent successfully.',
            default => null,
        };

        return $this->render($input, new SettingsValidationResult([]), $successMessage, null);
    }

    public function save(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        if (!$this->csrfTokenManager->validate('settings_save', $request->postTrimmedString('_csrf_token'))) {
            return $this->render($this->preserveActiveTheme(SettingsInput::fromPost($request->post())), new SettingsValidationResult([]), null, 'Your session token is invalid. Please try again.');
        }

        $input = $this->preserveActiveTheme(SettingsInput::fromPost($request->post()));
        $validation = $this->settingsValidator->validate($input, $this->isApcuAvailable(), $this->availableThemeNames(), $this->availableHomepagePageIds());

        if (!$validation->isValid()) {
            return $this->render($input, $validation, null, null);
        }

        $this->settingsManager->save($input, $this->isApcuAvailable());
        return Response::redirect('/admin/settings?saved=1');
    }

    public function sendTestEmail(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        if (!$this->csrfTokenManager->validate('settings_test_email', $request->postTrimmedString('_csrf_token'))) {
            return $this->render($this->currentInput(), new SettingsValidationResult([]), null, 'Your session token is invalid. Please try again.');
        }

        $baseInput = $this->currentInput();
        $recipient = $request->postTrimmedString('test_email_recipient');
        $input = new SettingsInput(
            siteName: $baseInput->siteName,
            tagline: $baseInput->tagline,
            siteUrl: $baseInput->siteUrl,
            siteMetaDescription: $baseInput->siteMetaDescription,
            siteSocialImage: $baseInput->siteSocialImage,
            siteLogo: $baseInput->siteLogo,
            siteLogoShowName: $baseInput->siteLogoShowName,
            activeTheme: $baseInput->activeTheme,
            homepageMode: $baseInput->homepageMode,
            homepagePageId: $baseInput->homepagePageId,
            postsPerPage: $baseInput->postsPerPage,
            sanitizeContentHtml: $baseInput->sanitizeContentHtml,
            timezone: $baseInput->timezone,
            dateFormat: $baseInput->dateFormat,
            timeFormat: $baseInput->timeFormat,
            cacheDriver: $baseInput->cacheDriver,
            mailTransport: $baseInput->mailTransport,
            mailFromName: $baseInput->mailFromName,
            mailFromEmail: $baseInput->mailFromEmail,
            smtpHost: $baseInput->smtpHost,
            smtpPort: $baseInput->smtpPort,
            smtpEncryption: $baseInput->smtpEncryption,
            smtpUsername: $baseInput->smtpUsername,
            smtpPassword: $baseInput->smtpPassword,
            smtpTimeoutSeconds: $baseInput->smtpTimeoutSeconds,
            testEmailRecipient: $recipient,
        );

        $validation = $this->settingsValidator->validate($input, $this->isApcuAvailable(), $this->availableThemeNames(), $this->availableHomepagePageIds());

        if ($validation->firstError('test_email_recipient') !== null) {
            return $this->render($input, $validation, null, null);
        }

        try {
            (new MailManager($this->mailConfig))->send(
                toEmail: $recipient,
                subject: 'Glyph test email',
                htmlBody: '<p>This is a test email from your Glyph installation.</p>',
                textBody: 'This is a test email from your Glyph installation.',
            );
        } catch (\Throwable $throwable) {
            return $this->render($input, $validation, null, 'Failed to send test email: ' . $throwable->getMessage());
        }

        return Response::redirect('/admin/settings?sent=1');
    }

    private function currentInput(): SettingsInput
    {
        $siteConfig = $this->siteConfig;
        if (($siteConfig['site_url'] ?? '') === '' && is_string($this->generatedConfig['site_url'] ?? null) && $this->generatedConfig['site_url'] !== '') {
            $siteConfig['site_url'] = $this->generatedConfig['site_url'];
        }

        return SettingsInput::fromConfig($siteConfig, $this->cacheConfig, $this->mailConfig);
    }

    private function preserveActiveTheme(SettingsInput $input): SettingsInput
    {
        $currentInput = $this->currentInput();

        return new SettingsInput(
            siteName: $input->siteName,
            tagline: $input->tagline,
            siteUrl: $input->siteUrl,
            siteMetaDescription: $input->siteMetaDescription,
            siteSocialImage: $input->siteSocialImage,
            siteLogo: $input->siteLogo,
            siteLogoShowName: $input->siteLogoShowName,
            activeTheme: $currentInput->activeTheme,
            homepageMode: $input->homepageMode,
            homepagePageId: $input->homepagePageId,
            postsPerPage: $input->postsPerPage,
            sanitizeContentHtml: $input->sanitizeContentHtml,
            timezone: $input->timezone,
            dateFormat: $input->dateFormat,
            timeFormat: $input->timeFormat,
            cacheDriver: $input->cacheDriver,
            mailTransport: $input->mailTransport,
            mailFromName: $input->mailFromName,
            mailFromEmail: $input->mailFromEmail,
            smtpHost: $input->smtpHost,
            smtpPort: $input->smtpPort,
            smtpEncryption: $input->smtpEncryption,
            smtpUsername: $input->smtpUsername,
            smtpPassword: $input->smtpPassword,
            smtpTimeoutSeconds: $input->smtpTimeoutSeconds,
            testEmailRecipient: $input->testEmailRecipient,
        );
    }

    private function render(SettingsInput $input, SettingsValidationResult $validationResult, ?string $successMessage, ?string $errorMessage): Response
    {
        return Response::html((new SettingsPageRenderer())->render(
            input: $input,
            validationResult: $validationResult,
            isApcuAvailable: $this->isApcuAvailable(),
            availableHomepagePages: $this->availableHomepagePages(),
            saveCsrfToken: $this->csrfTokenManager->token('settings_save'),
            testEmailCsrfToken: $this->csrfTokenManager->token('settings_test_email'),
            mediaUploadCsrfToken: $this->csrfTokenManager->token(self::MEDIA_UPLOAD_FORM_ID),
            successMessage: $successMessage,
            errorMessage: $errorMessage,
        ));
    }

    private function isApcuAvailable(): bool
    {
        return extension_loaded('apcu') && (($value = ini_get('apc.enabled')) !== false && $value !== '' && $value !== '0');
    }

    /** @return list<string> */
    private function availableThemeNames(): array
    {
        return array_map(static fn ($theme) => $theme->directoryName, $this->themeResolver->listThemes());
    }

    /** @return array<string, string> */
    private function availableHomepagePages(): array
    {
        $pages = ['' => 'Choose a published page'];
        foreach ($this->contentService->listAll() as $content) {
            if ($content->type === 'page' && $content->status === 'published') {
                $pages[$content->id] = $content->title . ' (' . $content->slug . ')';
            }
        }
        return $pages;
    }

    /** @return list<string> */
    private function availableHomepagePageIds(): array
    {
        return array_values(array_filter(array_keys($this->availableHomepagePages()), static fn (string $id): bool => $id !== ''));
    }

    private function guard(): ?Response
    {
        $currentUser = $this->authenticationManager->currentUser();
        if ($currentUser === null) {
            return Response::redirect('/login');
        }

        \Glyph\ui\shared\DocumentRenderer::setAdminUserContext($currentUser->email, $currentUser->role, $currentUser->displayNameOrFallback());

        if (!$this->authenticationManager->hasCapability(RoleCapabilities::SETTINGS_MANAGE)) {
            return Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Forbidden</title></head><body><h1>403</h1><p>You do not have permission to access settings.</p></body></html>', 403);
        }
        return null;
    }
}

