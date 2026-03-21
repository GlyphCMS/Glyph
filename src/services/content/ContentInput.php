<?php

declare(strict_types=1);

namespace Glyph\services\content;

final class ContentInput
{
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly string $slug,
        public readonly string $status,
        public readonly string $excerpt,
        public readonly string $bodyHtml,
        public readonly ?string $featuredImage,
        public readonly ?string $parentId,
        public readonly string $seoTitle = '',
        public readonly string $seoDescription = '',
        public readonly ?string $categoryId = null,
        public readonly string $navigationTitle = '',
        public readonly string $menuOrder = '0',
        public readonly bool $showInNavigation = true,
        public readonly bool $bypassHtmlSanitization = false,
        public readonly ?string $seoImage = null,
    ) {
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function fromPost(array $post): self
    {
        return new self(
            type: self::stringValue($post['type'] ?? ''),
            title: self::stringValue($post['title'] ?? ''),
            slug: self::stringValue($post['slug'] ?? ''),
            status: self::stringValue($post['status'] ?? ''),
            excerpt: self::stringValue($post['excerpt'] ?? ''),
            bodyHtml: self::rawStringValue($post['body_html'] ?? ''),
            featuredImage: self::nullableStringValue($post['featured_image'] ?? null),
            parentId: self::nullableStringValue($post['parent_id'] ?? null),
            categoryId: self::nullableStringValue($post['category_id'] ?? null),
            seoTitle: self::stringValue($post['seo_title'] ?? ''),
            seoDescription: self::stringValue($post['seo_description'] ?? ''),
            navigationTitle: self::stringValue($post['navigation_title'] ?? ''),
            menuOrder: self::stringValue($post['menu_order'] ?? '0'),
            showInNavigation: self::boolValue($post['show_in_navigation'] ?? '0'),
            bypassHtmlSanitization: self::boolValue($post['bypass_html_sanitization'] ?? '0'),
            seoImage: self::nullableStringValue($post['seo_image'] ?? null),
        );
    }

    public function withBypassPermission(bool $canBypass): self
    {
        return new self(
            type: $this->type,
            title: $this->title,
            slug: $this->slug,
            status: $this->status,
            excerpt: $this->excerpt,
            bodyHtml: $this->bodyHtml,
            featuredImage: $this->featuredImage,
            parentId: $this->parentId,
            categoryId: $this->categoryId,
            seoTitle: $this->seoTitle,
            seoDescription: $this->seoDescription,
            navigationTitle: $this->navigationTitle,
            menuOrder: $this->menuOrder,
            showInNavigation: $this->showInNavigation,
            bypassHtmlSanitization: $canBypass && $this->bypassHtmlSanitization,
            seoImage: $this->seoImage,
        );
    }

    public function withFeaturedImage(?string $featuredImage): self
    {
        return new self(
            type: $this->type,
            title: $this->title,
            slug: $this->slug,
            status: $this->status,
            excerpt: $this->excerpt,
            bodyHtml: $this->bodyHtml,
            featuredImage: $featuredImage,
            parentId: $this->parentId,
            categoryId: $this->categoryId,
            seoTitle: $this->seoTitle,
            seoDescription: $this->seoDescription,
            navigationTitle: $this->navigationTitle,
            menuOrder: $this->menuOrder,
            showInNavigation: $this->showInNavigation,
            bypassHtmlSanitization: $this->bypassHtmlSanitization,
            seoImage: $this->seoImage,
        );
    }

    public function withSeoImage(?string $seoImage): self
    {
        return new self(
            type: $this->type,
            title: $this->title,
            slug: $this->slug,
            status: $this->status,
            excerpt: $this->excerpt,
            bodyHtml: $this->bodyHtml,
            featuredImage: $this->featuredImage,
            parentId: $this->parentId,
            categoryId: $this->categoryId,
            seoTitle: $this->seoTitle,
            seoDescription: $this->seoDescription,
            navigationTitle: $this->navigationTitle,
            menuOrder: $this->menuOrder,
            showInNavigation: $this->showInNavigation,
            bypassHtmlSanitization: $this->bypassHtmlSanitization,
            seoImage: $seoImage,
        );
    }

    public function withSlug(string $slug): self
    {
        return new self(
            type: $this->type,
            title: $this->title,
            slug: $slug,
            status: $this->status,
            excerpt: $this->excerpt,
            bodyHtml: $this->bodyHtml,
            featuredImage: $this->featuredImage,
            parentId: $this->parentId,
            categoryId: $this->categoryId,
            seoTitle: $this->seoTitle,
            seoDescription: $this->seoDescription,
            navigationTitle: $this->navigationTitle,
            menuOrder: $this->menuOrder,
            showInNavigation: $this->showInNavigation,
            bypassHtmlSanitization: $this->bypassHtmlSanitization,
            seoImage: $this->seoImage,
        );
    }

    public function withStatus(string $status): self
    {
        return new self(
            type: $this->type,
            title: $this->title,
            slug: $this->slug,
            status: $status,
            excerpt: $this->excerpt,
            bodyHtml: $this->bodyHtml,
            featuredImage: $this->featuredImage,
            parentId: $this->parentId,
            categoryId: $this->categoryId,
            seoTitle: $this->seoTitle,
            seoDescription: $this->seoDescription,
            navigationTitle: $this->navigationTitle,
            menuOrder: $this->menuOrder,
            showInNavigation: $this->showInNavigation,
            bypassHtmlSanitization: $this->bypassHtmlSanitization,
            seoImage: $this->seoImage,
        );
    }

    public function withCategoryId(?string $categoryId): self
    {
        return new self(
            type: $this->type,
            title: $this->title,
            slug: $this->slug,
            status: $this->status,
            excerpt: $this->excerpt,
            bodyHtml: $this->bodyHtml,
            featuredImage: $this->featuredImage,
            parentId: $this->parentId,
            categoryId: $categoryId,
            seoTitle: $this->seoTitle,
            seoDescription: $this->seoDescription,
            navigationTitle: $this->navigationTitle,
            menuOrder: $this->menuOrder,
            showInNavigation: $this->showInNavigation,
            bypassHtmlSanitization: $this->bypassHtmlSanitization,
            seoImage: $this->seoImage,
        );
    }

    private static function stringValue(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    private static function rawStringValue(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    private static function nullableStringValue(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    private static function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (!is_string($value)) {
            return false;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}
