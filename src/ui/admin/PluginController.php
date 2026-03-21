<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\adapters\http\Request;
use Glyph\adapters\http\Response;
use Glyph\adapters\security\CsrfTokenManager;
use Glyph\domain\auth\RoleCapabilities;
use Glyph\services\auth\AuthenticationManager;
use Glyph\services\plugins\HookManager;
use Glyph\services\plugins\PluginAdminPage;
use Glyph\services\plugins\PluginAdminService;
use Glyph\services\plugins\PluginPackageInstaller;
use Glyph\services\plugins\PluginResolver;

final class PluginController
{
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly CsrfTokenManager $csrfTokenManager,
        private readonly PluginResolver $pluginResolver,
        private readonly PluginAdminService $pluginAdminService,
        private readonly PluginPackageInstaller $pluginPackageInstaller,
        private readonly HookManager $hookManager,
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
                $request->queryFlag('installed') => 'Plugin installed successfully.',
                $request->queryFlag('enabled') => 'Plugin enabled successfully.',
                $request->queryFlag('disabled') => 'Plugin disabled successfully.',
                $request->queryFlag('deleted') => 'Plugin deleted successfully.',
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

        if (!$this->csrfTokenManager->validate('plugin_upload', $request->postTrimmedString('_csrf_token'))) {
            return $this->render(null, 'Your session token is invalid. Please try again.');
        }

        try {
            if (!isset($_FILES['plugin_package']) || !is_array($_FILES['plugin_package'])) {
                throw new \RuntimeException('Please choose a plugin ZIP package to upload.');
            }

            $this->pluginPackageInstaller->install($_FILES['plugin_package']);

            return Response::redirect('/admin/plugins?installed=1');
        } catch (\Throwable $throwable) {
            return $this->render(null, $throwable->getMessage());
        }
    }

    public function toggle(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        if (!$this->csrfTokenManager->validate('plugin_toggle', $request->postTrimmedString('_csrf_token'))) {
            return $this->render(null, 'Your session token is invalid. Please try again.');
        }

        $plugin = $request->postTrimmedString('plugin');
        $state = $request->postTrimmedString('state');

        try {
            if ($state === 'enable') {
                $this->pluginAdminService->enable($plugin);
                return Response::redirect('/admin/plugins?enabled=1');
            }

            if ($state === 'disable') {
                $this->pluginAdminService->disable($plugin);
                return Response::redirect('/admin/plugins?disabled=1');
            }

            throw new \RuntimeException('Invalid plugin toggle action.');
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

        if (!$this->csrfTokenManager->validate('plugin_delete', $request->postTrimmedString('_csrf_token'))) {
            return $this->render(null, 'Your session token is invalid. Please try again.');
        }

        try {
            $this->pluginAdminService->delete($request->postTrimmedString('plugin'));

            return Response::redirect('/admin/plugins?deleted=1');
        } catch (\Throwable $throwable) {
            return $this->render(null, $throwable->getMessage());
        }
    }

    private function render(?string $successMessage, ?string $errorMessage): Response
    {
        $renderer = new PluginPageRenderer();

        return Response::html(
            $renderer->render(
                plugins: $this->pluginResolver->listPlugins(),
                pluginPages: $this->pluginPageMap(),
                uploadCsrfToken: $this->csrfTokenManager->token('plugin_upload'),
                toggleCsrfToken: $this->csrfTokenManager->token('plugin_toggle'),
                deleteCsrfToken: $this->csrfTokenManager->token('plugin_delete'),
                successMessage: $successMessage,
                errorMessage: $errorMessage,
            )
        );
    }

    /**
     * @return array<string, list<PluginAdminPage>>
     */
    private function pluginPageMap(): array
    {
        $map = [];

        foreach ($this->hookManager->adminPages() as $page) {
            $map[$page->pluginDirectoryName] ??= [];
            $map[$page->pluginDirectoryName][] = $page;
        }

        return $map;
    }

    private function guard(): ?Response
    {
        $currentUser = $this->authenticationManager->currentUser();

        if ($currentUser === null) {
            return Response::redirect('/login');
        }

        \Glyph\ui\shared\DocumentRenderer::setAdminUserContext($currentUser->email, $currentUser->role, $currentUser->displayNameOrFallback());

        if (!$this->authenticationManager->hasCapability(RoleCapabilities::PLUGIN_MANAGE)) {
            return Response::html(
                '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Forbidden</title></head><body><h1>403</h1><p>You do not have permission to manage plugins.</p></body></html>',
                403,
            );
        }

        return null;
    }
}

