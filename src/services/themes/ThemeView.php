<?php

declare(strict_types=1);

namespace Glyph\services\themes;

use DateTimeImmutable;
use DateTimeZone;
use Glyph\domain\content\ContentRecord;
use Glyph\domain\navigation\NavigationItem;
use Glyph\services\categories\CategoryService;
use Glyph\services\content\ContentListResult;
use Glyph\adapters\storage\UserFileRepository;
use Glyph\services\navigation\NavigationManager;
use Glyph\services\plugins\HookManager;
use Glyph\ui\shared\DocumentRenderer;

final class ThemeView
{
    /**
     * @param list<ContentRecord> $pages
     */
    public function __construct(
        private readonly DocumentRenderer $documentRenderer,
        private readonly ThemeData $theme,
        private readonly string $siteName,
        private readonly string $siteTagline,
        private readonly string $siteUrl = '',
        private readonly string $siteSocialImage = '',
        private readonly string $siteLogo = '',
        private readonly bool $siteLogoShowName = true,
        private readonly string $dateFormat = 'F j, Y',
        private readonly string $timeFormat = 'g:i A',
        private readonly string $timezone = 'UTC',
        private readonly ?HookManager $hookManager = null,
        private readonly ?NavigationManager $navigationManager = null,
        private readonly ?UserFileRepository $userRepository = null,
        private readonly array $pages = [],
        private readonly ?CategoryService $categoryService = null,
    ) {
    }

    /**
     * @param array<string, string> $meta
     */
    public function document(string $title, string $content, ?string $metaDescription = null, string $bodyClass = 'theme-frontend', array $meta = []): string
    {
        return $this->documentRenderer->render($title, $content, $metaDescription, $bodyClass, '', '', $meta);
    }

    public function escape(string $value): string
    {
        return $this->documentRenderer->escape($value);
    }

    public function assetUrl(string $relativePath): string
    {
        $normalizedPath = ltrim($relativePath, '/');

        return '/themes/' . rawurlencode($this->theme->directoryName) . '/assets/' . str_replace('%2F', '/', rawurlencode($normalizedPath));
    }

    public function absoluteUrl(string $path): string
    {
        if ($path === '') {
            return $this->siteUrl;
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        if ($this->siteUrl === '') {
            return $path;
        }

        return rtrim($this->siteUrl, '/') . '/' . ltrim($path, '/');
    }

    public function siteSocialImage(): string
    {
        return $this->siteSocialImage;
    }

    public function siteLogo(): string
    {
        return $this->siteLogo;
    }

    public function siteLogoShowName(): bool
    {
        return $this->siteLogo === '' ? true : $this->siteLogoShowName;
    }

    public function siteBrandImage(): string
    {
        return $this->siteLogo !== '' ? $this->versionedUrl($this->siteLogo) : '';
    }

    public function socialImageUrl(?string $contentImage = null, ?string $version = null): string
    {
        $candidate = $contentImage !== null && $contentImage !== '' ? $contentImage : $this->siteSocialImage;

        if ($candidate === '') {
            return '';
        }

        return $this->absoluteUrl($this->versionedUrl($candidate, $version));
    }

    public function versionedUrl(string $path, ?string $version = null): string
    {
        $trimmedPath = trim($path);

        if ($trimmedPath === '' || $version === null || $version === '') {
            return $trimmedPath;
        }

        $delimiter = str_contains($trimmedPath, '?') ? '&' : '?';

        return $trimmedPath . $delimiter . 'v=' . rawurlencode(sha1($version));
    }

    public function contentImageUrl(ContentRecord $contentRecord): string
    {
        if ($contentRecord->featuredImage === null || $contentRecord->featuredImage === '') {
            return '';
        }

        return $this->versionedUrl($contentRecord->featuredImage, $contentRecord->updatedAt);
    }

    public function asset(string $key, string $fallbackRelativePath = ''): ?string
    {
        $mappedPath = $this->theme->assets[$key] ?? null;

        if (is_string($mappedPath) && $mappedPath !== '') {
            return '/themes/' . rawurlencode($this->theme->directoryName) . '/assets/' . str_replace('%2F', '/', rawurlencode(ltrim($mappedPath, '/')));
        }

        if ($fallbackRelativePath !== '') {
            return $this->assetUrl($fallbackRelativePath);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function partial(string $partialName, array $data = []): string
    {
        $normalizedName = trim($partialName);

        if ($normalizedName === '' || preg_match('/^[a-zA-Z0-9_\/-]+$/', $normalizedName) !== 1) {
            throw new \RuntimeException('Invalid theme partial name.');
        }

        $partialPath = $this->theme->path . '/partials/' . ltrim($normalizedName, '/') . '.php';

        if (!is_file($partialPath)) {
            throw new \RuntimeException(sprintf('Theme partial not found: %s', $partialPath));
        }

        $render = static function (string $__partialPath, array $__data, ThemeView $__theme): string {
            extract($__data, EXTR_SKIP);

            ob_start();
            require $__partialPath;
            $output = ob_get_clean();

            if (!is_string($output)) {
                throw new \RuntimeException('Failed to render theme partial.');
            }

            return $output;
        };

        return $render($partialPath, $data, $this);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function slot(string $slotName, array $context = []): string
    {
        if ($this->hookManager === null) {
            return '';
        }

        return $this->hookManager->renderSlot($slotName, $context);
    }

    public function filter(string $hookName, mixed $value, mixed ...$arguments): mixed
    {
        if ($this->hookManager === null) {
            return $value;
        }

        return $this->hookManager->applyFilters($hookName, $value, ...$arguments);
    }

    public function siteName(): string
    {
        return $this->siteName;
    }

    public function siteTagline(): string
    {
        return $this->siteTagline;
    }

    public function siteUrl(): string
    {
        return $this->siteUrl;
    }

    public function formatDate(string $isoDateTime): string
    {
        try {
            return (new DateTimeImmutable($isoDateTime))->setTimezone(new DateTimeZone($this->timezone))->format($this->dateFormat);
        } catch (\Throwable) {
            return $isoDateTime;
        }
    }

    public function formatDateTime(string $isoDateTime): string
    {
        try {
            return (new DateTimeImmutable($isoDateTime))->setTimezone(new DateTimeZone($this->timezone))->format($this->dateFormat . ' ' . $this->timeFormat);
        } catch (\Throwable) {
            return $isoDateTime;
        }
    }

    /** @return list<NavigationItem> */
    public function navigation(string $menuName): array
    {
        if ($this->navigationManager === null) {
            return [];
        }

        return $this->appendAutoCategoryNavigation($menuName, $this->navigationManager->menu($menuName, $this->pages));
    }

    public function navigationHtml(string $menuName, string $className = 'site-nav'): string
    {
        $items = $this->navigation($menuName);

        if ($items === []) {
            return '';
        }

        return $this->renderNavigationList($items, $className, 0);
    }

    /** @return list<NavigationItem> */
    public function sidebarLinks(): array
    {
        return $this->navigation('sidebar');
    }

    /** @return list<ContentRecord> */
    public function sidebarLatestPosts(?ContentRecord $currentContent = null): array
    {
        if ($this->navigationManager === null || !$this->navigationManager->sidebarLatestPostsEnabled()) {
            return [];
        }

        $currentId = $currentContent?->id;
        $posts = array_values(array_filter(
            $this->pages,
            static function (ContentRecord $record) use ($currentId): bool {
                if ($record->type !== 'post' || $record->status !== 'published') {
                    return false;
                }

                if ($currentId !== null && $record->id === $currentId) {
                    return false;
                }

                return true;
            }
        ));

        usort(
            $posts,
            static function (ContentRecord $left, ContentRecord $right): int {
                $leftDate = $left->publishedAt ?? $left->createdAt;
                $rightDate = $right->publishedAt ?? $right->createdAt;

                return strcmp($rightDate, $leftDate);
            }
        );

        return array_slice($posts, 0, $this->navigationManager->sidebarLatestPostsLimit());
    }

    public function renderSidebar(?ContentRecord $currentContent = null, string $className = 'content-sidebar'): string
    {
        $links = $this->sidebarLinks();
        $latestPosts = $this->sidebarLatestPosts($currentContent);
        $pluginWidgets = trim($this->slot('theme.sidebar', [
            'content' => $currentContent,
            'links' => $links,
            'latest_posts' => $latestPosts,
        ]));

        if ($links === [] && $latestPosts === [] && $pluginWidgets === '') {
            return '';
        }

        $html = '<aside class="' . $this->escape(trim($className . ' stack')) . '">';

        if ($links !== []) {
            $html .= '<section class="panel stack content-sidebar__section">';
            $html .= '<h2 class="content-sidebar__title">Sidebar Links</h2>';
            $html .= $this->renderNavigationList($links, 'content-sidebar__nav', 0);
            $html .= '</section>';
        }

        if ($latestPosts !== []) {
            $html .= '<section class="panel stack content-sidebar__section">';
            $html .= '<h2 class="content-sidebar__title">Latest Posts</h2>';
            $html .= '<ul class="content-sidebar__posts">';

            foreach ($latestPosts as $post) {
                $date = $post->publishedAt ?? $post->createdAt;
                $html .= '<li>';
                $html .= '<a href="' . $this->escape($post->slug) . '">' . $this->escape($post->title) . '</a>';
                $html .= '<span class="content-sidebar__meta">' . $this->escape($this->formatDate($date)) . '</span>';
                $html .= '</li>';
            }

            $html .= '</ul>';
            $html .= '</section>';
        }

        if ($pluginWidgets !== '') {
            $html .= $pluginWidgets;
        }

        return $html . '</aside>';
    }

    public function authorDisplayName(string $userId): string
    {
        if ($this->userRepository === null || $userId === '') {
            return '';
        }

        $user = $this->userRepository->findById($userId);

        return $user !== null ? $user->displayNameOrFallback() : '';
    }

    public function pagination(string $basePath, array $query, ContentListResult $listResult): string
    {
        if ($listResult->totalPages <= 1) {
            return '';
        }

        $body = '<nav><p>';

        if ($listResult->currentPage > 1) {
            $previousQuery = $query;
            $previousQuery['page'] = (string) ($listResult->currentPage - 1);
            $body .= '<a href="' . $this->escape($this->buildUrl($basePath, $previousQuery)) . '">Previous</a> ';
        }

        $body .= 'Page ' . $listResult->currentPage . ' of ' . $listResult->totalPages;

        if ($listResult->currentPage < $listResult->totalPages) {
            $nextQuery = $query;
            $nextQuery['page'] = (string) ($listResult->currentPage + 1);
            $body .= ' <a href="' . $this->escape($this->buildUrl($basePath, $nextQuery)) . '">Next</a>';
        }

        return $body . '</p></nav>';
    }

    /**
     * @param list<NavigationItem> $items
     */
    private function renderNavigationList(array $items, string $className, int $depth): string
    {
        $html = '<ul class="' . $this->escape(trim($className . ' ' . $className . '--depth-' . $depth)) . '">';

        foreach ($items as $item) {
            $target = $item->target === '_blank' ? ' target="_blank" rel="noreferrer noopener"' : '';
            $itemClasses = ['site-nav__item', 'site-nav__item--depth-' . $depth];

            if ($item->children !== []) {
                $itemClasses[] = 'site-nav__item--has-children';
            }

            $linkState = $item->children !== [] ? ' aria-haspopup="true" aria-expanded="false"' : '';
            $html .= '<li class="' . $this->escape(implode(' ', $itemClasses)) . '">';
            $html .= '<a href="' . $this->escape($item->url) . '"' . $target . $linkState . '>' . $this->escape($item->label) . '</a>';

            if ($item->children !== []) {
                $html .= $this->renderNavigationList($item->children, $className, $depth + 1);
            }

            $html .= '</li>';
        }

        return $html . '</ul>';
    }

    /**
     * @param array<string, string> $query
     */
    private function buildUrl(string $basePath, array $query): string
    {
        $queryString = http_build_query($query);

        return $queryString === '' ? $basePath : $basePath . '?' . $queryString;
    }

    /**
     * @param list<NavigationItem> $items
     * @return list<NavigationItem>
     */
    private function appendAutoCategoryNavigation(string $menuName, array $items): array
    {
        if ($this->categoryService === null || $menuName !== 'primary' || !$this->categoryService->hasCategories()) {
            return $items;
        }

        foreach ($items as $item) {
            if (strtolower($item->label) === 'categories') {
                return $items;
            }
        }

        $children = $this->categoryNavigationItems();
        $parentUrl = '/categories';

        array_unshift($items, new NavigationItem(
            id: 'auto_categories',
            label: 'Categories',
            url: $parentUrl,
            target: '_self',
            parentId: '',
            sortOrder: -1,
            contentId: null,
            children: $children,
        ));

        return $items;
    }

    /** @return list<NavigationItem> */
    private function categoryNavigationItems(): array
    {
        if ($this->categoryService === null) {
            return [];
        }

        $build = function (?string $parentId) use (&$build): array {
            $categories = $parentId === null
                ? $this->categoryService?->topLevelCategories() ?? []
                : $this->categoryService?->childCategories($parentId) ?? [];
            $items = [];

            foreach ($categories as $category) {
                $items[] = new NavigationItem(
                    id: 'category_' . $category->id,
                    label: $category->name,
                    url: $this->categoryService?->archivePathFor($category) ?? '#',
                    target: '_self',
                    parentId: $parentId ?? '',
                    sortOrder: 0,
                    contentId: null,
                    children: $build($category->id),
                );
            }

            return $items;
        };

        return $build(null);
    }
}



