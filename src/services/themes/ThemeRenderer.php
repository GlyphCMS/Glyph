<?php

declare(strict_types=1);

namespace Glyph\services\themes;

use Glyph\adapters\storage\UserFileRepository;
use Glyph\domain\categories\CategoryRecord;
use Glyph\domain\content\ContentRecord;
use Glyph\services\categories\CategoryService;
use Glyph\services\content\ContentListResult;
use Glyph\services\content\ContentService;
use Glyph\services\navigation\NavigationManager;
use Glyph\services\plugins\HookManager;
use Glyph\ui\shared\DocumentRenderer;

final class ThemeRenderer
{
    /**
     * @param array<string, mixed> $siteConfig
     */
    public function __construct(
        private readonly array $siteConfig,
        private readonly ThemeResolver $themeResolver,
        private readonly DocumentRenderer $documentRenderer,
        private readonly ContentService $contentService,
        private readonly ?NavigationManager $navigationManager = null,
        private readonly ?UserFileRepository $userRepository = null,
        private readonly ?HookManager $hookManager = null,
        private readonly ?CategoryService $categoryService = null,
    ) {
    }

    public function renderHome(ContentListResult $listResult): string
    {
        return $this->renderListingTemplate('home.php', [
            'listResult' => $listResult,
            'siteName' => $this->siteName(),
            'siteTagline' => $this->siteTagline(),
            'pageTitle' => $this->siteName(),
            'metaDescription' => $this->siteMetaDescription() !== '' ? $this->siteMetaDescription() : 'Home page for ' . $this->siteName() . '.',
            'heroEyebrow' => $this->siteName(),
            'heroTitle' => $this->siteTagline() !== '' ? $this->siteTagline() : $this->siteName(),
            'heroSubtitle' => $this->siteMetaDescription() !== '' ? $this->siteMetaDescription() : 'Browse the latest posts, search the site, and explore content.',
            'emptyStateMessage' => 'No published posts yet.',
            'paginationBasePath' => '/',
            'paginationQuery' => [],
            'documentMeta' => [
                'canonical_url' => $this->absoluteSiteUrl('/'),
                'site_name' => $this->siteName(),
                'og_type' => 'website',
                'og_image' => $this->socialImageUrl(null),
                'favicon_href' => $this->siteLogo(),
            ],
        ]);
    }


    public function renderCategoryArchive(CategoryRecord $category, ContentListResult $listResult): string
    {
        $description = $category->description !== ''
            ? $category->description
            : 'Browse content filed under ' . $category->name . '.';

        return $this->renderListingTemplate('home.php', [
            'listResult' => $listResult,
            'siteName' => $this->siteName(),
            'siteTagline' => $this->siteTagline(),
            'pageTitle' => $category->name . ' - ' . $this->siteName(),
            'metaDescription' => $description,
            'heroEyebrow' => 'Category Archive',
            'heroTitle' => $category->name,
            'heroSubtitle' => $description,
            'emptyStateMessage' => 'No published content in this category yet.',
            'paginationBasePath' => $this->categoryService?->archivePathFor($category) ?? '/',
            'paginationQuery' => [],
            'documentMeta' => [
                'canonical_url' => $this->absoluteSiteUrl($this->categoryService?->archivePathFor($category) ?? '/'),
                'site_name' => $this->siteName(),
                'og_type' => 'website',
                'og_image' => $this->socialImageUrl(null),
                'favicon_href' => $this->siteLogo(),
            ],
        ]);
    }
    /**
     * @param list<array{category: CategoryRecord, depth: int, path: string, archive_path: string}> $orderedCategories
     */
    public function renderCategoriesIndex(array $orderedCategories): string
    {
        return $this->renderListingTemplate('categories.php', [
            'orderedCategories' => $orderedCategories,
            'siteName' => $this->siteName(),
            'siteTagline' => $this->siteTagline(),
            'pageTitle' => 'Categories',
            'metaDescription' => 'Browse all categories on ' . $this->siteName() . '.',
            'documentMeta' => [
                'canonical_url' => $this->absoluteSiteUrl('/categories'),
                'site_name' => $this->siteName(),
                'og_type' => 'website',
                'og_image' => $this->socialImageUrl(null),
                'favicon_href' => $this->siteLogo(),
            ],
        ]);
    }

    public function renderSearch(string $query, ContentListResult $listResult): string
    {
        $theme = $this->themeResolver->resolve($this->activeTheme());

        return $this->renderTemplate($theme, 'search.php', [
            'query' => $query,
            'listResult' => $listResult,
            'siteName' => $this->siteName(),
            'siteTagline' => $this->siteTagline(),
            'pageTitle' => 'Search - ' . $this->siteName(),
            'metaDescription' => trim($query) !== ''
                ? 'Search results for "' . $query . '" on ' . $this->siteName() . '.'
                : 'Search ' . $this->siteName() . '.',
            'documentMeta' => [
                'canonical_url' => $this->absoluteSiteUrl('/search' . ($query !== '' ? '?q=' . rawurlencode($query) : '')),
                'site_name' => $this->siteName(),
                'og_type' => 'website',
                'og_image' => $this->socialImageUrl(null),
                'favicon_href' => $this->siteLogo(),
                'robots' => 'noindex,follow',
            ],
        ]);
    }

    public function renderContent(ContentRecord $contentRecord): string
    {
        $theme = $this->themeResolver->resolve($this->activeTheme());
        $title = $contentRecord->seoTitle !== '' ? $contentRecord->seoTitle : $contentRecord->title;
        $description = $contentRecord->seoDescription !== '' ? $contentRecord->seoDescription : ($contentRecord->excerpt !== '' ? $contentRecord->excerpt : $this->siteMetaDescription());

        return $this->renderTemplate($theme, 'content.php', [
            'contentRecord' => $contentRecord,
            'siteName' => $this->siteName(),
            'siteTagline' => $this->siteTagline(),
            'pageTitle' => $title,
            'metaDescription' => $description,
            'documentMeta' => [
                'canonical_url' => $this->absoluteSiteUrl($contentRecord->slug),
                'site_name' => $this->siteName(),
                'og_type' => $contentRecord->type === 'post' ? 'article' : 'website',
                'og_image' => $this->socialImageUrl($contentRecord->seoImage ?? $contentRecord->featuredImage, $contentRecord->updatedAt),
                'favicon_href' => $this->siteLogo(),
            ],
        ]);
    }

    public function renderNotFound(): string
    {
        $theme = $this->themeResolver->resolve($this->activeTheme());

        return $this->renderTemplate($theme, '404.php', [
            'siteName' => $this->siteName(),
            'siteTagline' => $this->siteTagline(),
            'pageTitle' => 'Not Found - ' . $this->siteName(),
            'metaDescription' => 'Page not found.',
            'documentMeta' => [
                'site_name' => $this->siteName(),
                'og_type' => 'website',
                'favicon_href' => $this->siteLogo(),
                'robots' => 'noindex,follow',
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderListingTemplate(string $templateName, array $data): string
    {
        $theme = $this->themeResolver->resolve($this->activeTheme());

        return $this->renderTemplate($theme, $templateName, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderTemplate(ThemeData $theme, string $templateName, array $data): string
    {
        $templatePath = $theme->path . '/templates/' . $templateName;

        if (!is_file($templatePath)) {
            throw new \RuntimeException(sprintf('Theme template not found: %s', $templatePath));
        }

        $themeApi = new ThemeView(
            $this->documentRenderer,
            $theme,
            $this->siteName(),
            $this->siteTagline(),
            $this->siteUrl(),
            $this->siteSocialImage(),
            $this->siteLogo(),
            $this->siteLogoShowName(),
            $this->dateFormat(),
            $this->timeFormat(),
            $this->timezone(),
            $this->hookManager,
            $this->navigationManager,
            $this->userRepository,
            $this->contentService->listAll(),
            $this->categoryService,
        );

        $render = static function (string $__templatePath, array $__data, ThemeView $__theme): string {
            extract($__data, EXTR_SKIP);

            ob_start();
            require $__templatePath;
            $output = ob_get_clean();

            if (!is_string($output)) {
                throw new \RuntimeException('Failed to render theme template.');
            }

            return $output;
        };

        return $render($templatePath, $data, $themeApi);
    }

    private function activeTheme(): string
    {
        $activeTheme = $this->siteConfig['active_theme'] ?? 'default';
        return is_string($activeTheme) && $activeTheme !== '' ? $activeTheme : 'default';
    }

    private function siteName(): string
    {
        $siteName = $this->siteConfig['site_name'] ?? 'Glyph';
        return is_string($siteName) && $siteName !== '' ? $siteName : 'Glyph';
    }

    private function siteTagline(): string
    {
        $tagline = $this->siteConfig['tagline'] ?? '';
        return is_string($tagline) ? $tagline : '';
    }

    private function siteUrl(): string
    {
        $siteUrl = $this->siteConfig['site_url'] ?? '';
        return is_string($siteUrl) ? $siteUrl : '';
    }

    private function siteMetaDescription(): string
    {
        $value = $this->siteConfig['site_meta_description'] ?? '';
        return is_string($value) ? $value : '';
    }

    private function siteSocialImage(): string
    {
        $value = $this->siteConfig['site_social_image'] ?? '';
        return is_string($value) ? $value : '';
    }

    private function siteLogo(): string
    {
        $value = $this->siteConfig['site_logo'] ?? '';
        return is_string($value) ? $value : '';
    }

    private function siteLogoShowName(): bool
    {
        $value = $this->siteConfig['site_logo_show_name'] ?? true;
        return is_bool($value) ? $value : $value !== 0 && $value !== '0' && $value !== '';
    }

    private function dateFormat(): string
    {
        $value = $this->siteConfig['date_format'] ?? 'F j, Y';
        return is_string($value) && $value !== '' ? $value : 'F j, Y';
    }

    private function timeFormat(): string
    {
        $value = $this->siteConfig['time_format'] ?? 'g:i A';
        return is_string($value) && $value !== '' ? $value : 'g:i A';
    }

    private function timezone(): string
    {
        $value = $this->siteConfig['timezone'] ?? 'UTC';
        return is_string($value) && $value !== '' ? $value : 'UTC';
    }

    private function absoluteSiteUrl(string $path): string
    {
        $siteUrl = $this->siteUrl();
        if ($siteUrl === '') {
            return $path;
        }

        return rtrim($siteUrl, '/') . '/' . ltrim($path, '/');
    }

    private function socialImageUrl(?string $contentImage, ?string $version = null): string
    {
        $candidate = $contentImage !== null && $contentImage !== '' ? $contentImage : $this->siteSocialImage();
        if ($candidate === '') {
            return '';
        }

        return $this->absoluteSiteUrl($this->versionedUrl($candidate, $version));
    }

    private function versionedUrl(string $path, ?string $version = null): string
    {
        $trimmedPath = trim($path);

        if ($trimmedPath === '' || $version === null || $version === '') {
            return $trimmedPath;
        }

        $delimiter = str_contains($trimmedPath, '?') ? '&' : '?';

        return $trimmedPath . $delimiter . 'v=' . rawurlencode(sha1($version));
    }
}
