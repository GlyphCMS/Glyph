<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\adapters\http\Request;
use Glyph\adapters\http\Response;
use Glyph\adapters\security\CsrfTokenManager;
use Glyph\domain\auth\RoleCapabilities;
use Glyph\services\auth\AuthenticationManager;
use Glyph\services\content\ContentService;
use Glyph\services\navigation\NavigationManager;

final class NavigationController
{
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly CsrfTokenManager $csrfTokenManager,
        private readonly NavigationManager $navigationManager,
        private readonly ContentService $contentService,
    ) {
    }

    public function show(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $renderer = new NavigationPageRenderer();

        return Response::html($renderer->render(
            menus: $this->navigationManager->rawMenus(),
            pageOptions: $this->pageOptions(),
            sidebarSettings: $this->navigationManager->rawSidebarSettings(),
            csrfToken: $this->csrfTokenManager->token('navigation_save'),
            successMessage: $request->queryFlag('saved') ? 'Navigation saved successfully.' : null,
            errorMessage: null,
        ));
    }

    public function save(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        if (!$this->csrfTokenManager->validate('navigation_save', $request->postString('_csrf_token'))) {
            return $this->renderError('Your session token is invalid. Please try again.');
        }

        $post = $request->post();
        $menus = $post['menus'] ?? [];

        if (!is_array($menus)) {
            $menus = [];
        }

        $this->navigationManager->save(
            [
                'primary' => $this->normalizeMenuRows($menus['primary'] ?? []),
                'footer' => $this->normalizeMenuRows($menus['footer'] ?? []),
                'sidebar' => $this->normalizeMenuRows($menus['sidebar'] ?? []),
            ],
            $this->normalizeSidebarSettings($post['sidebar'] ?? []),
        );

        return Response::redirect('/admin/navigation?saved=1');
    }

    /**
     * @param mixed $rows
     * @return list<array<string, string>>
     */
    private function normalizeMenuRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $item = [];
            foreach (['id', 'label', 'url', 'target', 'parent_id', 'sort_order', 'content_id'] as $key) {
                $value = $row[$key] ?? '';
                $item[$key] = is_string($value) ? trim($value) : '';
            }
            $normalized[] = $item;
        }

        return $normalized;
    }

    /**
     * @param mixed $sidebar
     * @return array<string, string>
     */
    private function normalizeSidebarSettings(mixed $sidebar): array
    {
        if (!is_array($sidebar)) {
            return [
                'display_latest_posts' => '0',
                'latest_posts_limit' => '5',
            ];
        }

        $displayLatestPosts = $sidebar['display_latest_posts'] ?? '0';
        $latestPostsLimit = $sidebar['latest_posts_limit'] ?? '5';

        return [
            'display_latest_posts' => is_string($displayLatestPosts) ? trim($displayLatestPosts) : '0',
            'latest_posts_limit' => is_string($latestPostsLimit) ? trim($latestPostsLimit) : '5',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function pageOptions(): array
    {
        $options = [];

        foreach ($this->contentService->listAll() as $content) {
            if ($content->type !== 'page' || $content->status !== 'published') {
                continue;
            }

            $options[$content->id] = $content->navigationTitle !== '' ? $content->navigationTitle : $content->title;
        }

        return $options;
    }

    private function renderError(string $message): Response
    {
        $renderer = new NavigationPageRenderer();

        return Response::html($renderer->render(
            menus: $this->navigationManager->rawMenus(),
            pageOptions: $this->pageOptions(),
            sidebarSettings: $this->navigationManager->rawSidebarSettings(),
            csrfToken: $this->csrfTokenManager->token('navigation_save'),
            successMessage: null,
            errorMessage: $message,
        ), 400);
    }

    private function guard(): ?Response
    {
        $currentUser = $this->authenticationManager->currentUser();

        if ($currentUser === null) {
            return Response::redirect('/login');
        }

        \Glyph\ui\shared\DocumentRenderer::setAdminUserContext($currentUser->email, $currentUser->role, $currentUser->displayNameOrFallback());

        if (!$this->authenticationManager->hasCapability(RoleCapabilities::SETTINGS_MANAGE)) {
            return Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Forbidden</title></head><body><h1>403</h1><p>You do not have permission to manage navigation.</p></body></html>', 403);
        }

        return null;
    }
}

