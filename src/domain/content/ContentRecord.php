<?php

declare(strict_types=1);

namespace Glyph\domain\content;

final class ContentRecord
{
    /**
     * @param list<string> $redirects
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $title,
        public readonly string $slug,
        public readonly string $status,
        public readonly string $excerpt,
        public readonly string $bodyHtml,
        public readonly ?string $featuredImage,
        public readonly string $authorId,
        public readonly ?string $parentId,
        public readonly ?string $publishedAt,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly array $redirects,
        public readonly string $seoTitle,
        public readonly string $seoDescription,
        public readonly string $baseSlug = '',
        public readonly ?string $categoryId = null,
        public readonly string $navigationTitle = '',
        public readonly int $menuOrder = 0,
        public readonly bool $showInNavigation = true,
        public readonly bool $bypassHtmlSanitization = false,
        public readonly ?string $seoImage = null,
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function fromStorage(array $meta, string $bodyHtml): self
    {
        $slug = self::requireString($meta, 'slug');

        return new self(
            id: self::requireString($meta, 'id'),
            type: self::requireString($meta, 'type'),
            title: self::requireString($meta, 'title'),
            slug: $slug,
            status: self::requireString($meta, 'status'),
            excerpt: self::requireString($meta, 'excerpt'),
            bodyHtml: $bodyHtml,
            featuredImage: self::optionalString($meta, 'featured_image'),
            authorId: self::requireString($meta, 'author_id'),
            parentId: self::optionalString($meta, 'parent_id'),
            publishedAt: self::optionalString($meta, 'published_at'),
            createdAt: self::requireString($meta, 'created_at'),
            updatedAt: self::requireString($meta, 'updated_at'),
            redirects: self::stringList($meta['redirects'] ?? []),
            seoTitle: self::requireString($meta, 'seo_title'),
            seoDescription: self::requireString($meta, 'seo_description'),
            baseSlug: self::optionalString($meta, 'base_slug') ?? self::finalSlugSegment($slug),
            categoryId: self::optionalString($meta, 'category_id'),
            navigationTitle: self::optionalString($meta, 'navigation_title') ?? '',
            menuOrder: self::intValue($meta['menu_order'] ?? 0),
            showInNavigation: self::boolValue($meta['show_in_navigation'] ?? true),
            bypassHtmlSanitization: self::boolValue($meta['bypass_html_sanitization'] ?? false),
            seoImage: self::optionalString($meta, 'seo_image'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status,
            'excerpt' => $this->excerpt,
            'featured_image' => $this->featuredImage,
            'author_id' => $this->authorId,
            'parent_id' => $this->parentId,
            'published_at' => $this->publishedAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'redirects' => $this->redirects,
            'seo_title' => $this->seoTitle,
            'seo_description' => $this->seoDescription,
            'base_slug' => $this->baseSlug,
            'category_id' => $this->categoryId,
            'navigation_title' => $this->navigationTitle,
            'menu_order' => $this->menuOrder,
            'show_in_navigation' => $this->showInNavigation,
            'bypass_html_sanitization' => $this->bypassHtmlSanitization,
            'seo_image' => $this->seoImage,
        ];
    }

    /**
     * @param array{title:string,slug:string,base_slug:string,status:string,excerpt:string,body_html:string,featured_image:?string,parent_id:?string,category_id:?string,seo_title:string,seo_description:string,seo_image:?string,navigation_title:string,menu_order:int,show_in_navigation:bool,bypass_html_sanitization:bool} $changes
     * @param list<string> $redirects
     */
    public function withChanges(array $changes, string $updatedAt, ?string $publishedAt, array $redirects): self
    {
        return new self(
            id: $this->id,
            type: $this->type,
            title: $changes['title'],
            slug: $changes['slug'],
            status: $changes['status'],
            excerpt: $changes['excerpt'],
            bodyHtml: $changes['body_html'],
            featuredImage: $changes['featured_image'],
            authorId: $this->authorId,
            parentId: $changes['parent_id'],
            publishedAt: $publishedAt,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
            redirects: $redirects,
            seoTitle: $changes['seo_title'],
            seoDescription: $changes['seo_description'],
            baseSlug: $changes['base_slug'],
            categoryId: $changes['category_id'],
            navigationTitle: $changes['navigation_title'],
            menuOrder: $changes['menu_order'],
            showInNavigation: $changes['show_in_navigation'],
            bypassHtmlSanitization: $changes['bypass_html_sanitization'],
            seoImage: $changes['seo_image'],
        );
    }

    /**
     * @param list<string> $redirects
     */
    public function withResolvedSlug(string $slug, array $redirects, string $updatedAt): self
    {
        return new self(
            id: $this->id,
            type: $this->type,
            title: $this->title,
            slug: $slug,
            status: $this->status,
            excerpt: $this->excerpt,
            bodyHtml: $this->bodyHtml,
            featuredImage: $this->featuredImage,
            authorId: $this->authorId,
            parentId: $this->parentId,
            publishedAt: $this->publishedAt,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
            redirects: $redirects,
            seoTitle: $this->seoTitle,
            seoDescription: $this->seoDescription,
            baseSlug: $this->baseSlug,
            categoryId: $this->categoryId,
            navigationTitle: $this->navigationTitle,
            menuOrder: $this->menuOrder,
            showInNavigation: $this->showInNavigation,
            bypassHtmlSanitization: $this->bypassHtmlSanitization,
            seoImage: $this->seoImage,
        );
    }

    private static function requireString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value)) {
            throw new \RuntimeException(sprintf('Invalid content field: %s', $key));
        }
        return $value;
    }

    private static function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new \RuntimeException(sprintf('Invalid optional content field: %s', $key));
        }
        if ($value === '') {
            return null;
        }
        return $value;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $list = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new \RuntimeException('Invalid redirect record.');
            }
            $list[] = $item;
        }
        return $list;
    }

    private static function intValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }
        return 0;
    }

    private static function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }
        return false;
    }

    private static function finalSlugSegment(string $slug): string
    {
        $trimmed = trim($slug, '/');
        if ($trimmed === '') {
            return '';
        }

        $segments = explode('/', $trimmed);
        $last = array_pop($segments);

        return is_string($last) ? $last : '';
    }
}
