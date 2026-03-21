<?php

declare(strict_types=1);

namespace Glyph\ui\frontend;

use Glyph\adapters\http\Request;
use Glyph\adapters\http\Response;
use Glyph\services\categories\CategoryService;
use Glyph\services\content\ContentService;
use Glyph\services\themes\ThemeRenderer;

final class FrontendController
{
    /**
     * @param array<string, mixed> $siteConfig
     */
    public function __construct(
        private readonly ContentService $contentService,
        private readonly ThemeRenderer $themeRenderer,
        private readonly array $siteConfig,
        private readonly ?CategoryService $categoryService = null,
    ) {
    }

    public function health(Request $request): Response
    {
        return Response::html(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Glyph Health</title></head><body><h1>OK</h1><p>Glyph bootstrap completed successfully.</p></body></html>'
        );
    }

    public function home(Request $request): Response
    {
        $homepageMode = $this->siteConfig['homepage_mode'] ?? 'posts';

        if ($homepageMode === 'page') {
            $homepagePageId = $this->siteConfig['homepage_page_id'] ?? '';

            if (is_string($homepagePageId) && $homepagePageId !== '') {
                $page = $this->contentService->findPublishedById('page', $homepagePageId);

                if ($page !== null) {
                    return Response::html($this->themeRenderer->renderContent($page));
                }
            }
        }

        $page = $this->positiveInt($request->queryString('page') ?? 1);
        $perPage = $this->postsPerPage();
        $listResult = $this->contentService->listPublishedPosts($page, $perPage);

        return Response::html($this->themeRenderer->renderHome($listResult));
    }


    public function categories(Request $request): Response
    {
        $orderedCategories = $this->categoryService?->orderedForDisplay() ?? [];

        return Response::html($this->themeRenderer->renderCategoriesIndex($orderedCategories));
    }

    public function search(Request $request): Response
    {
        $query = $request->queryTrimmedString('q');
        $page = $this->positiveInt($request->queryString('page') ?? 1);
        $perPage = $this->postsPerPage();
        $listResult = $this->contentService->searchPublished($query, $page, $perPage);

        return Response::html($this->themeRenderer->renderSearch($query, $listResult));
    }

    public function fallback(Request $request): Response
    {
        $path = $request->path();

        if ($this->categoryService !== null) {
            $category = $this->categoryService->findByArchivePath($path);
            if ($category !== null) {
                $page = $this->positiveInt($request->queryString('page') ?? 1);
                $perPage = $this->postsPerPage();
                $listResult = $this->contentService->listPublishedByCategory($category, $page, $perPage);

                return Response::html($this->themeRenderer->renderCategoryArchive($category, $listResult));
            }
        }

        $redirectTarget = $this->contentService->findRedirectTarget($path);
        if ($redirectTarget !== null) {
            return Response::redirect($redirectTarget, 301);
        }

        $content = $this->contentService->findPublishedBySlug($path);
        if ($content !== null) {
            return Response::html($this->themeRenderer->renderContent($content));
        }

        return Response::html($this->themeRenderer->renderNotFound(), 404);
    }

    private function postsPerPage(): int
    {
        $value = $this->siteConfig['posts_per_page'] ?? 10;

        if (is_int($value) && $value >= 1 && $value <= 100) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value >= 1 && (int) $value <= 100) {
            return (int) $value;
        }

        return 10;
    }

    private function positiveInt(mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        return 1;
    }
}
