<?php

declare(strict_types=1);

namespace Glyph\services\content;

use Glyph\adapters\security\HtmlSanitizer;
use Glyph\adapters\security\SecretGenerator;
use Glyph\adapters\storage\ContentFileRepository;
use Glyph\adapters\storage\RedirectFileRepository;
use Glyph\domain\categories\CategoryRecord;
use Glyph\domain\content\ContentRecord;
use Glyph\domain\shared\Text;
use Glyph\services\categories\CategoryService;

final class ContentService
{
    private const DEFAULT_PER_PAGE = 10;

    public function __construct(
        private readonly ContentFileRepository $contentRepository,
        private readonly RedirectFileRepository $redirectRepository,
        private readonly ContentValidator $validator,
        private readonly SlugManager $slugManager,
        private readonly SecretGenerator $secretGenerator,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly ?CategoryService $categoryService = null,
        private readonly bool $sanitizeContentHtml = false,
    ) {
    }

    /**
     * @return list<ContentRecord>
     */
    public function listAll(): array
    {
        return $this->contentRepository->findAll();
    }

    public function findById(string $type, string $id): ?ContentRecord
    {
        return $this->contentRepository->findById($type, $id);
    }

    public function findPublishedById(string $type, string $id): ?ContentRecord
    {
        $record = $this->contentRepository->findById($type, $id);

        if ($record === null || $record->status !== 'published') {
            return null;
        }

        return $record;
    }

    public function validate(ContentInput $input, bool $canBypassHtmlSanitization = false): ContentValidationResult
    {
        $validation = $this->validator->validate($input);
        $errors = $validation->fieldErrors();

        if (($errors['body_html'] ?? null) === null && $this->preparedBodyHtml($input, $canBypassHtmlSanitization) === '') {
            $errors['body_html'] = 'Body content must include text or supported HTML.';
        }

        return new ContentValidationResult($errors);
    }

    public function create(ContentInput $input, string $authorId, bool $canBypassHtmlSanitization = false): ContentRecord
    {
        $validation = $this->validate($input, $canBypassHtmlSanitization);

        if (!$validation->isValid()) {
            throw new \RuntimeException('Cannot create content with invalid input.');
        }

        $normalizedBaseSlug = $this->slugManager->normalizeSegment($input->slug);
        $normalizedSlug = $this->contentPath($input->categoryId, $normalizedBaseSlug);

        if ($this->slugExists($normalizedSlug, null)) {
            throw new \RuntimeException('Slug is already in use.');
        }

        $timestamp = $this->now();
        $publishedAt = $input->status === 'published' ? $timestamp : null;
        $bodyHtml = $this->preparedBodyHtml($input, $canBypassHtmlSanitization);

        $content = new ContentRecord(
            id: $this->secretGenerator->generateId(),
            type: $input->type,
            title: $input->title,
            slug: $normalizedSlug,
            status: $input->status,
            excerpt: $input->excerpt,
            bodyHtml: $bodyHtml,
            featuredImage: $input->featuredImage,
            authorId: $authorId,
            parentId: $input->parentId,
            publishedAt: $publishedAt,
            createdAt: $timestamp,
            updatedAt: $timestamp,
            redirects: [],
            seoTitle: $input->seoTitle,
            seoDescription: $input->seoDescription,
            baseSlug: $normalizedBaseSlug,
            categoryId: $input->categoryId,
            navigationTitle: $input->navigationTitle,
            seoImage: $input->seoImage,
            menuOrder: $this->menuOrderValue($input),
            showInNavigation: $input->showInNavigation,
            bypassHtmlSanitization: $this->shouldBypassHtmlSanitization($input, $canBypassHtmlSanitization),
        );

        $this->contentRepository->save($content);

        return $content;
    }

    public function update(ContentRecord $existing, ContentInput $input, bool $canBypassHtmlSanitization = false): ContentRecord
    {
        $validation = $this->validate($input, $canBypassHtmlSanitization);

        if (!$validation->isValid()) {
            throw new \RuntimeException('Cannot update content with invalid input.');
        }

        $normalizedBaseSlug = $this->slugManager->normalizeSegment($input->slug);
        $normalizedSlug = $this->contentPath($input->categoryId, $normalizedBaseSlug);

        if ($this->slugExists($normalizedSlug, $existing->id)) {
            throw new \RuntimeException('Slug is already in use.');
        }

        $redirects = $existing->redirects;

        if ($existing->slug !== $normalizedSlug) {
            if (!in_array($existing->slug, $redirects, true)) {
                $redirects[] = $existing->slug;
            }

            $this->redirectRepository->replace($existing->slug, $normalizedSlug);
        }

        $publishedAt = $existing->publishedAt;
        if ($input->status === 'published' && $publishedAt === null) {
            $publishedAt = $this->now();
        }

        if ($input->status === 'draft') {
            $publishedAt = null;
        }

        $bodyHtml = $this->preparedBodyHtml($input, $canBypassHtmlSanitization);

        $updated = $existing->withChanges(
            changes: [
                'title' => $input->title,
                'slug' => $normalizedSlug,
                'base_slug' => $normalizedBaseSlug,
                'status' => $input->status,
                'excerpt' => $input->excerpt,
                'body_html' => $bodyHtml,
                'featured_image' => $input->featuredImage,
                'parent_id' => $input->parentId,
                'category_id' => $input->categoryId,
                'seo_title' => $input->seoTitle,
                'seo_description' => $input->seoDescription,
                'seo_image' => $input->seoImage,
                'navigation_title' => $input->navigationTitle,
                'menu_order' => $this->menuOrderValue($input),
                'show_in_navigation' => $input->showInNavigation,
                'bypass_html_sanitization' => $this->shouldBypassHtmlSanitization($input, $canBypassHtmlSanitization),
            ],
            updatedAt: $this->now(),
            publishedAt: $publishedAt,
            redirects: $redirects,
        );

        $this->contentRepository->save($updated);

        return $updated;
    }

    public function delete(ContentRecord $content): void
    {
        $this->contentRepository->delete($content);
        $this->redirectRepository->removeByTarget($content->slug);
    }

    public function findPublishedBySlug(string $path): ?ContentRecord
    {
        $normalizedPath = $this->slugManager->normalize($path);

        foreach ($this->publishedContent() as $record) {
            if ($record->slug === $normalizedPath) {
                return $record;
            }
        }

        return null;
    }

    public function findRedirectTarget(string $path): ?string
    {
        $normalizedPath = $this->slugManager->normalize($path);

        return $this->redirectRepository->findTarget($normalizedPath);
    }

    public function listPublishedPosts(int $page, int $perPage = self::DEFAULT_PER_PAGE): ContentListResult
    {
        $publishedPosts = array_values(
            array_filter(
                $this->publishedContent(),
                static fn (ContentRecord $record): bool => $record->type === 'post'
            )
        );

        usort($publishedPosts, $this->publishedDateSorter());

        return $this->paginate($publishedPosts, $page, $perPage);
    }

    public function listPublishedByCategory(CategoryRecord $category, int $page, int $perPage = self::DEFAULT_PER_PAGE): ContentListResult
    {
        $allowedCategoryIds = $this->categoryService !== null
            ? $this->categoryService->descendantIdsFor($category->id)
            : [$category->id];

        $matching = array_values(array_filter(
            $this->publishedContent(),
            static function (ContentRecord $record) use ($allowedCategoryIds): bool {
                return $record->categoryId !== null
                    && in_array($record->categoryId, $allowedCategoryIds, true);
            },
        ));

        usort($matching, $this->publishedDateSorter());

        return $this->paginate($matching, $page, $perPage);
    }

    public function searchPublished(string $query, int $page, int $perPage = self::DEFAULT_PER_PAGE): ContentListResult
    {
        $needle = Text::lower(trim($query));

        if ($needle === '') {
            return new ContentListResult([], 1, $perPage, 0, 0);
        }

        $matches = array_values(
            array_filter(
                $this->publishedContent(),
                static function (ContentRecord $record) use ($needle): bool {
                    $haystack = Text::lower(
                        implode(
                            "\n",
                            [
                                $record->title,
                                $record->excerpt,
                                $record->seoTitle,
                                $record->seoDescription,
                                strip_tags($record->bodyHtml),
                            ]
                        )
                    );

                    return str_contains($haystack, $needle);
                }
            )
        );

        usort($matches, $this->publishedDateSorter());

        return $this->paginate($matches, $page, $perPage);
    }

    public function sanitizationEnabled(): bool
    {
        return $this->sanitizeContentHtml;
    }

    /**
     * @return list<ContentRecord>
     */
    private function publishedContent(): array
    {
        return array_values(
            array_filter(
                $this->contentRepository->findAll(),
                static fn (ContentRecord $record): bool => $record->status === 'published'
            )
        );
    }

    /**
     * @param list<ContentRecord> $items
     */
    private function paginate(array $items, int $page, int $perPage): ContentListResult
    {
        $safePerPage = max(1, $perPage);
        $safePage = max(1, $page);
        $totalItems = count($items);
        $totalPages = $totalItems === 0 ? 0 : (int) ceil($totalItems / $safePerPage);
        $offset = ($safePage - 1) * $safePerPage;

        /** @var list<ContentRecord> $slice */
        $slice = array_values(array_slice($items, $offset, $safePerPage));

        return new ContentListResult(
            items: $slice,
            currentPage: $safePage,
            perPage: $safePerPage,
            totalItems: $totalItems,
            totalPages: $totalPages,
        );
    }

    private function slugExists(string $slug, ?string $ignoreContentId): bool
    {
        foreach ($this->contentRepository->findAll() as $record) {
            if ($record->slug !== $slug) {
                continue;
            }

            if ($ignoreContentId !== null && $record->id === $ignoreContentId) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function preparedBodyHtml(ContentInput $input, bool $canBypassHtmlSanitization): string
    {
        if (!$this->sanitizeContentHtml || $this->shouldBypassHtmlSanitization($input, $canBypassHtmlSanitization)) {
            return $input->bodyHtml;
        }

        return $this->htmlSanitizer->sanitize($input->bodyHtml);
    }

    private function shouldBypassHtmlSanitization(ContentInput $input, bool $canBypassHtmlSanitization): bool
    {
        return $this->sanitizeContentHtml && $canBypassHtmlSanitization && $input->bypassHtmlSanitization;
    }

    private function menuOrderValue(ContentInput $input): int
    {
        return preg_match('/^-?\d+$/', $input->menuOrder) === 1 ? (int) $input->menuOrder : 0;
    }

    private function contentPath(?string $categoryId, string $baseSlug): string
    {
        if ($this->categoryService !== null) {
            return $this->categoryService->contentPath($categoryId, $baseSlug);
        }

        $normalizedBaseSlug = $this->slugManager->normalizeSegment($baseSlug);

        return $normalizedBaseSlug === '' ? '/' : '/' . $normalizedBaseSlug;
    }

    /**
     * @return callable(ContentRecord, ContentRecord): int
     */
    private function publishedDateSorter(): callable
    {
        return static function (ContentRecord $left, ContentRecord $right): int {
            $leftDate = $left->publishedAt ?? $left->createdAt;
            $rightDate = $right->publishedAt ?? $right->createdAt;

            return strcmp($rightDate, $leftDate);
        };
    }

    private function now(): string
    {
        $timestamp = gmdate('c');

        if (!is_string($timestamp) || $timestamp === '') {
            throw new \RuntimeException('Failed to determine timestamp.');
        }

        return $timestamp;
    }
}

