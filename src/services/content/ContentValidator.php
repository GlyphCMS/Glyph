<?php

declare(strict_types=1);

namespace Glyph\services\content;

use Glyph\domain\shared\Text;
use Glyph\services\categories\CategoryService;

final class ContentValidator
{
    private const MAX_TITLE_LENGTH = 200;
    private const MAX_SLUG_LENGTH = 255;
    private const MAX_EXCERPT_LENGTH = 500;
    private const MAX_SEO_TITLE_LENGTH = 200;
    private const MAX_SEO_DESCRIPTION_LENGTH = 320;
    private const MAX_NAVIGATION_TITLE_LENGTH = 120;

    public function __construct(
        private readonly SlugManager $slugManager,
        private readonly ?CategoryService $categoryService = null,
    ) {
    }

    public function validate(ContentInput $input): ContentValidationResult
    {
        $errors = [];
        $normalizedSlug = $this->slugManager->normalizeSegment($input->slug);

        if (!in_array($input->type, ['post', 'page'], true)) {
            $errors['type'] = 'Content type must be post or page.';
        }
        if ($input->title === '') {
            $errors['title'] = 'Title is required.';
        } elseif (Text::length($input->title) > self::MAX_TITLE_LENGTH) {
            $errors['title'] = 'Title is too long.';
        }
        if ($input->slug === '') {
            $errors['slug'] = 'Slug is required.';
        } elseif ($normalizedSlug === '') {
            $errors['slug'] = 'Slug must include letters or numbers.';
        } elseif (Text::length($normalizedSlug) > self::MAX_SLUG_LENGTH) {
            $errors['slug'] = 'Slug is too long.';
        } elseif (!$this->slugManager->isValidSegment($normalizedSlug)) {
            $errors['slug'] = 'Slug must contain only lowercase letters, numbers, hyphens, and underscores.';
        } elseif ($input->categoryId === null && $this->slugManager->isReserved('/' . $normalizedSlug)) {
            $errors['slug'] = 'Slug conflicts with a reserved site path.';
        }
        if (!in_array($input->status, ['draft', 'published'], true)) {
            $errors['status'] = 'Status must be draft or published.';
        }
        if (Text::length($input->excerpt) > self::MAX_EXCERPT_LENGTH) {
            $errors['excerpt'] = 'Excerpt is too long.';
        }
        if ($input->bodyHtml === '') {
            $errors['body_html'] = 'Body content is required.';
        }
        if ($input->featuredImage !== null && !$this->isSafeRelativeImagePath($input->featuredImage)) {
            $errors['featured_image'] = 'Featured image path is invalid.';
        }
        if ($input->seoImage !== null && !$this->isSafeRelativeImagePath($input->seoImage)) {
            $errors['seo_image'] = 'SEO image path is invalid.';
        }
        if ($input->parentId !== null && $input->parentId === '') {
            $errors['parent_id'] = 'Parent id is invalid.';
        }
        if (
            $this->categoryService !== null
            && $input->categoryId !== null
            && $this->categoryService->findById($input->categoryId) === null
        ) {
            $errors['category_id'] = 'Selected category was not found.';
        }
        if (($errors['slug'] ?? null) === null && $this->categoryService !== null) {
            $resolvedPath = $this->categoryService->contentPath($input->categoryId, $normalizedSlug);

            if ($this->categoryService->findByArchivePath($resolvedPath) !== null) {
                $errors['slug'] = 'Slug conflicts with a category archive path.';
            }
        }
        if (Text::length($input->seoTitle) > self::MAX_SEO_TITLE_LENGTH) {
            $errors['seo_title'] = 'SEO title is too long.';
        }
        if (Text::length($input->seoDescription) > self::MAX_SEO_DESCRIPTION_LENGTH) {
            $errors['seo_description'] = 'SEO description is too long.';
        }
        if (Text::length($input->navigationTitle) > self::MAX_NAVIGATION_TITLE_LENGTH) {
            $errors['navigation_title'] = 'Navigation title is too long.';
        }
        if (preg_match('/^-?\d+$/', $input->menuOrder) !== 1) {
            $errors['menu_order'] = 'Menu order must be a whole number.';
        }

        return new ContentValidationResult($errors);
    }

    private function isSafeRelativeImagePath(string $path): bool
    {
        if (str_contains($path, '..')) {
            return false;
        }

        return preg_match('#^[a-zA-Z0-9/_\.-]+$#', $path) === 1;
    }
}
