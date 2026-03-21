<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\adapters\http\Request;
use Glyph\adapters\http\Response;
use Glyph\adapters\security\CsrfTokenManager;
use Glyph\domain\auth\RoleCapabilities;
use Glyph\services\auth\AuthenticationManager;
use Glyph\services\themes\ThemeAdminService;
use Glyph\services\themes\ThemePackageInstaller;
use Glyph\services\themes\ThemeResolver;

final class ThemeController
{
    /**
     * @param array<string, mixed> $siteConfig
     */
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly CsrfTokenManager $csrfTokenManager,
        private readonly ThemeResolver $themeResolver,
        private readonly ThemePackageInstaller $themePackageInstaller,
        private readonly ThemeAdminService $themeAdminService,
        private readonly array $siteConfig,
    ) {
    }

    public function index(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        return $this->render(
            match (true) {
                $request->queryFlag('installed') => 'Theme installed successfully.',
                $request->queryFlag('activated') => 'Theme activated successfully.',
                $request->queryFlag('deleted') => 'Theme deleted successfully.',
                default => null,
            },
            null,
        );
    }

    public function install(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        if (!$this->csrfTokenManager->validate('theme_upload', $request->postTrimmedString('_csrf_token'))) {
            return $this->render(null, 'Your session token is invalid. Please try again.');
        }

        try {
            if (!isset($_FILES['theme_package']) || !is_array($_FILES['theme_package'])) {
                throw new \RuntimeException('Please choose a theme ZIP package to upload.');
            }

            $this->themePackageInstaller->install($_FILES['theme_package']);

            return Response::redirect('/admin/themes?installed=1');
        } catch (\Throwable $throwable) {
            return $this->render(null, $throwable->getMessage());
        }
    }

    public function activate(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        if (!$this->csrfTokenManager->validate('theme_activate', $request->postTrimmedString('_csrf_token'))) {
            return $this->render(null, 'Your session token is invalid. Please try again.');
        }

        try {
            $this->themeAdminService->activate($request->postTrimmedString('theme'));

            return Response::redirect('/admin/themes?activated=1');
        } catch (\Throwable $throwable) {
            return $this->render(null, $throwable->getMessage());
        }
    }

    public function delete(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        if (!$this->csrfTokenManager->validate('theme_delete', $request->postTrimmedString('_csrf_token'))) {
            return $this->render(null, 'Your session token is invalid. Please try again.');
        }

        try {
            $this->themeAdminService->delete($request->postTrimmedString('theme'));

            return Response::redirect('/admin/themes?deleted=1');
        } catch (\Throwable $throwable) {
            return $this->render(null, $throwable->getMessage());
        }
    }

    private function render(?string $successMessage, ?string $errorMessage): Response
    {
        $renderer = new ThemePageRenderer();
        $activeTheme = $this->siteConfig['active_theme'] ?? 'default';

        return Response::html(
            $renderer->render(
                themes: $this->themeResolver->listThemes(),
                activeTheme: is_string($activeTheme) && $activeTheme !== '' ? $activeTheme : 'default',
                uploadCsrfToken: $this->csrfTokenManager->token('theme_upload'),
                activateCsrfToken: $this->csrfTokenManager->token('theme_activate'),
                deleteCsrfToken: $this->csrfTokenManager->token('theme_delete'),
                successMessage: $successMessage,
                errorMessage: $errorMessage,
            )
        );
    }

    private function guard(): ?Response
    {
        $currentUser = $this->authenticationManager->currentUser();

        if ($currentUser === null) {
            return Response::redirect('/login');
        }

        \Glyph\ui\shared\DocumentRenderer::setAdminUserContext($currentUser->email, $currentUser->role, $currentUser->displayNameOrFallback());

        if (!$this->authenticationManager->hasCapability(RoleCapabilities::THEME_MANAGE)) {
            return Response::html(
                '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Forbidden</title></head><body><h1>403</h1><p>You do not have permission to manage themes.</p></body></html>',
                403,
            );
        }

        return null;
    }
}

