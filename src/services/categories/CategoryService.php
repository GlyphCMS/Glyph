<?php

declare(strict_types=1);

namespace Glyph\services\categories;

use Glyph\adapters\security\SecretGenerator;
use Glyph\adapters\storage\CategoryFileRepository;
use Glyph\adapters\storage\ContentFileRepository;
use Glyph\adapters\storage\RedirectFileRepository;
use Glyph\domain\categories\CategoryRecord;
use Glyph\services\content\SlugManager;

final class CategoryService
{
    private const MAX_NAME_LENGTH = 120;
    private const MAX_DESCRIPTION_LENGTH = 500;

    public function __construct(
        private readonly CategoryFileRepository $categoryRepository,
        private readonly SlugManager $slugManager,
        private readonly SecretGenerator $secretGenerator,
        private readonly ContentFileRepository $contentRepository,
        private readonly RedirectFileRepository $redirectRepository,
    ) {
    }

    /**
     * @return list<CategoryRecord>
     */
    public function listAll(): array
    {
        $categories = $this->categoryRepository->findAll();

        usort(
            $categories,
            static fn (CategoryRecord $left, CategoryRecord $right): int => [$left->name, $left->createdAt] <=> [$right->name, $right->createdAt],
        );

        return $categories;
    }

    public function findById(?string $id): ?CategoryRecord
    {
        if ($id === null || $id === '') {
            return null;
        }

        foreach ($this->categoryRepository->findAll() as $category) {
            if ($category->id === $id) {
                return $category;
            }
        }

        return null;
    }

    public function findByArchivePath(string $path): ?CategoryRecord
    {
        $trimmedPath = trim($path, '/');

        if ($trimmedPath === '') {
            return null;
        }

        foreach ($this->categoryRepository->findAll() as $category) {
            if (trim($this->pathFor($category), '/') === $trimmedPath) {
                return $category;
            }
        }

        return null;
    }

    /**
     * @return list<array{category: CategoryRecord, depth: int, path: string, archive_path: string}>
     */
    public function orderedForDisplay(): array
    {
        $categories = $this->categoryRepository->findAll();
        $byParent = [];

        foreach ($categories as $category) {
            $byParent[$category->parentId ?? ''][] = $category;
        }

        foreach ($byParent as &$items) {
            usort($items, static fn (CategoryRecord $left, CategoryRecord $right): int => [$left->name, $left->createdAt] <=> [$right->name, $right->createdAt]);
        }
        unset($items);

        $ordered = [];
        $walk = function (?string $parentId, int $depth) use (&$walk, &$ordered, $byParent): void {
            foreach ($byParent[$parentId ?? ''] ?? [] as $category) {
                $ordered[] = [
                    'category' => $category,
                    'depth' => $depth,
                    'path' => $this->pathFor($category),
                    'archive_path' => $this->archivePathFor($category),
                ];
                $walk($category->id, $depth + 1);
            }
        };

        $walk(null, 0);

        return $ordered;
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = ['' => 'No category'];

        foreach ($this->orderedForDisplay() as $row) {
            $category = $row['category'];
            $options[$category->id] = str_repeat('-- ', $row['depth']) . $category->name;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public function categoryPathsById(): array
    {
        $paths = [];

        foreach ($this->categoryRepository->findAll() as $category) {
            $paths[$category->id] = $this->pathFor($category);
        }

        return $paths;
    }

    /**
     * @return list<CategoryRecord>
     */
    public function topLevelCategories(): array
    {
        return array_values(array_filter(
            $this->listAll(),
            static fn (CategoryRecord $category): bool => $category->parentId === null,
        ));
    }

    /**
     * @return list<CategoryRecord>
     */
    public function childCategories(string $parentId): array
    {
        return array_values(array_filter(
            $this->listAll(),
            static fn (CategoryRecord $category): bool => $category->parentId === $parentId,
        ));
    }

    /**
     * @return list<string>
     */
    public function descendantIdsFor(string $categoryId): array
    {
        return $this->descendantIds($categoryId, $this->categoryRepository->findAll());
    }

    /**
     * @return array<string, string>
     */
    public function validate(CategoryInput $input, ?string $ignoreCategoryId = null): array
    {
        $errors = [];
        $categories = $this->categoryRepository->findAll();
        $normalizedSlug = $this->slugManager->normalizeSegment($input->slug);

        if ($input->name === '') {
            $errors['name'] = 'Name is required.';
        } elseif (mb_strlen($input->name) > self::MAX_NAME_LENGTH) {
            $errors['name'] = 'Name is too long.';
        }

        if ($input->slug === '') {
            $errors['slug'] = 'Slug is required.';
        } elseif ($normalizedSlug === '') {
            $errors['slug'] = 'Slug must include letters or numbers.';
        } elseif (!$this->slugManager->isValidSegment($normalizedSlug)) {
            $errors['slug'] = 'Slug must contain only lowercase letters, numbers, hyphens, and underscores.';
        }

        if (mb_strlen($input->description) > self::MAX_DESCRIPTION_LENGTH) {
            $errors['description'] = 'Description is too long.';
        }

        if ($input->parentId !== null && $this->findById($input->parentId) === null) {
            $errors['parent_id'] = 'Selected parent category was not found.';
        }

        if ($ignoreCategoryId !== null && $input->parentId === $ignoreCategoryId) {
            $errors['parent_id'] = 'A category cannot be its own parent.';
        }

        if (
            $ignoreCategoryId !== null
            && $input->parentId !== null
            && in_array($input->parentId, $this->descendantIds($ignoreCategoryId, $categories), true)
        ) {
            $errors['parent_id'] = 'A category cannot move into its own descendants.';
        }

        if (($errors['slug'] ?? null) === null) {
            $candidatePath = $this->buildPathFromParts($input->parentId, $normalizedSlug, $categories);

            if ($this->slugManager->isReserved($candidatePath)) {
                $errors['slug'] = 'That category path conflicts with a reserved site path.';
            }

            foreach ($categories as $category) {
                if ($ignoreCategoryId !== null && $category->id === $ignoreCategoryId) {
                    continue;
                }

                if ($this->pathFor($category, $categories) === $candidatePath) {
                    $errors['slug'] = 'That category path is already in use.';
                    break;
                }
            }

            if (($errors['slug'] ?? null) === null) {
                foreach ($this->contentRepository->findAll() as $content) {
                    if ($content->slug === $candidatePath) {
                        $errors['slug'] = 'That category path is already in use by content.';
                        break;
                    }
                }
            }
        }

        return $errors;
    }

    public function create(CategoryInput $input): CategoryRecord
    {
        $normalized = $input->withSlug($this->slugManager->normalizeSegment($input->slug));
        $errors = $this->validate($normalized);

        if ($errors !== []) {
            throw new \RuntimeException('Cannot create category with invalid input.');
        }

        $timestamp = $this->now();
        $category = new CategoryRecord(
            id: $this->secretGenerator->generateId(),
            name: $normalized->name,
            slug: $normalized->slug,
            description: $normalized->description,
            parentId: $normalized->parentId,
            createdAt: $timestamp,
            updatedAt: $timestamp,
        );

        $categories = $this->categoryRepository->findAll();
        $categories[] = $category;
        $this->categoryRepository->saveAll($categories);

        return $category;
    }

    public function update(CategoryRecord $existing, CategoryInput $input): CategoryRecord
    {
        $normalized = $input->withSlug($this->slugManager->normalizeSegment($input->slug));
        $errors = $this->validate($normalized, $existing->id);

        if ($errors !== []) {
            throw new \RuntimeException('Cannot update category with invalid input.');
        }

        $updated = $existing->withChanges(
            name: $normalized->name,
            slug: $normalized->slug,
            description: $normalized->description,
            parentId: $normalized->parentId,
            updatedAt: $this->now(),
        );

        $categories = array_map(
            static fn (CategoryRecord $category): CategoryRecord => $category->id === $existing->id ? $updated : $category,
            $this->categoryRepository->findAll(),
        );

        $this->categoryRepository->saveAll($categories);
        $this->syncAssignedContentPaths($this->descendantIds($existing->id, $categories));

        return $updated;
    }

    public function delete(CategoryRecord $category): void
    {
        if ($this->childCategories($category->id) !== []) {
            throw new \RuntimeException('Delete child categories first.');
        }

        foreach ($this->contentRepository->findAll() as $content) {
            if ($content->categoryId === $category->id) {
                throw new \RuntimeException('Move content out of this category before deleting it.');
            }
        }

        $remaining = array_values(array_filter(
            $this->categoryRepository->findAll(),
            static fn (CategoryRecord $candidate): bool => $candidate->id !== $category->id,
        ));

        $this->categoryRepository->saveAll($remaining);
    }

    public function pathForId(?string $categoryId): string
    {
        $category = $this->findById($categoryId);

        return $category !== null ? $this->pathFor($category) : '';
    }

    public function archivePathFor(CategoryRecord $category): string
    {
        return $this->pathFor($category);
    }

    public function contentPath(?string $categoryId, string $baseSlug): string
    {
        $normalizedBaseSlug = $this->slugManager->normalizeSegment($baseSlug);

        if ($normalizedBaseSlug === '') {
            return '/';
        }

        $prefix = $this->pathForId($categoryId);

        return $prefix !== ''
            ? $prefix . '/' . $normalizedBaseSlug
            : '/' . $normalizedBaseSlug;
    }

    public function hasCategories(): bool
    {
        return $this->categoryRepository->findAll() !== [];
    }

    /**
     * @param list<CategoryRecord> $categories
     * @return list<string>
     */
    private function descendantIds(string $categoryId, array $categories): array
    {
        $ids = [$categoryId];
        $queue = [$categoryId];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($categories as $category) {
                if ($category->parentId !== $current) {
                    continue;
                }

                $ids[] = $category->id;
                $queue[] = $category->id;
            }
        }

        return $ids;
    }

    /**
     * @param list<string> $affectedCategoryIds
     */
    private function syncAssignedContentPaths(array $affectedCategoryIds): void
    {
        foreach ($this->contentRepository->findAll() as $content) {
            if ($content->categoryId === null || !in_array($content->categoryId, $affectedCategoryIds, true)) {
                continue;
            }

            $resolvedSlug = $this->contentPath($content->categoryId, $content->baseSlug);

            if ($resolvedSlug === $content->slug) {
                continue;
            }

            $redirects = $content->redirects;
            if (!in_array($content->slug, $redirects, true)) {
                $redirects[] = $content->slug;
            }

            $this->redirectRepository->replace($content->slug, $resolvedSlug);
            $this->contentRepository->save($content->withResolvedSlug($resolvedSlug, $redirects, $this->now()));
        }
    }

    /**
     * @param list<CategoryRecord>|null $categories
     */
    private function pathFor(CategoryRecord $category, ?array $categories = null): string
    {
        $allCategories = $categories ?? $this->categoryRepository->findAll();
        $byId = [];

        foreach ($allCategories as $item) {
            $byId[$item->id] = $item;
        }

        $segments = [$category->slug];
        $parentId = $category->parentId;

        while ($parentId !== null && isset($byId[$parentId])) {
            $parent = $byId[$parentId];
            array_unshift($segments, $parent->slug);
            $parentId = $parent->parentId;
        }

        return '/' . implode('/', $segments);
    }

    /**
     * @param list<CategoryRecord> $categories
     */
    private function buildPathFromParts(?string $parentId, string $slug, array $categories): string
    {
        if ($parentId === null || $parentId === '') {
            return '/' . $slug;
        }

        foreach ($categories as $category) {
            if ($category->id === $parentId) {
                return $this->pathFor($category, $categories) . '/' . $slug;
            }
        }

        return '/' . $slug;
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
