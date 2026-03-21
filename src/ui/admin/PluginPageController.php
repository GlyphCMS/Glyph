<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\adapters\http\Request;
use Glyph\adapters\http\Response;
use Glyph\domain\auth\RoleCapabilities;
use Glyph\services\auth\AuthenticationManager;
use Glyph\services\plugins\HookManager;
use Glyph\ui\shared\DocumentRenderer;

final class PluginPageController
{
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly HookManager $hookManager,
    ) {
    }

    public function render(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $pageKey = $request->queryTrimmedString('page');
        $page = $this->hookManager->findAdminPage($pageKey);

        if ($page === null) {
            return Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Not Found</title></head><body><h1>404</h1><p>Plugin page not found.</p></body></html>', 404);
        }

        $renderer = $page->renderer;
        $result = $renderer($request);

        if ($result instanceof Response) {
            return $result;
        }

        if (!is_string($result)) {
            throw new \RuntimeException('Plugin admin page renderer must return a string or Response.');
        }

        $document = new DocumentRenderer();

        $content = '<main class="page-shell stack">';
        $content .= '<section class="hero">';
        $content .= '<p class="hero__eyebrow">Plugin Page</p>';
        $content .= '<div class="toolbar">';
        $content .= '<div><h1 class="hero__title">' . $document->escape($page->title) . '</h1><p class="hero__text">' . $document->escape($page->description) . '</p></div>';
        $content .= '<a class="button button-secondary" href="/admin/plugins">Back to plugins</a>';
        $content .= '</div></section>';
        $content .= $result;
        $content .= '</main>';

        return Response::html($document->render($page->title, $content, $page->description, 'theme-admin'));
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
                '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Forbidden</title></head><body><h1>403</h1><p>You do not have permission to access plugin pages.</p></body></html>',
                403,
            );
        }

        return null;
    }
}

