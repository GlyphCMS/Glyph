<?php

declare(strict_types=1);

namespace Glyph\services\navigation;

use Glyph\adapters\storage\PhpConfigWriter;
use Glyph\domain\content\ContentRecord;
use Glyph\domain\navigation\NavigationItem;

final class NavigationManager
{
    /**
     * @param array<string, mixed> $navigationConfig
     */
    public function __construct(
        private readonly PhpConfigWriter $configWriter,
        private readonly string $systemPath,
        private readonly array $navigationConfig,
    ) {
    }

    /**
     * @param list<ContentRecord> $pages
     * @return list<NavigationItem>
     */
    public function menu(string $menuName, array $pages): array
    {
        $items = $this->configuredMenuItems($menuName, $pages);

        if ($items !== []) {
            return $this->buildTree($items);
        }

        if ($menuName === 'primary') {
            return $this->buildTree($this->defaultPageItems($pages));
        }

        return [];
    }

    /**
     * @return array<string, list<array<string, string>>>
     */
    public function rawMenus(): array
    {
        $menus = $this->currentConfig()['menus'] ?? [];

        return [
            'primary' => is_array($menus['primary'] ?? null) ? $menus['primary'] : [],
            'footer' => is_array($menus['footer'] ?? null) ? $menus['footer'] : [],
            'sidebar' => is_array($menus['sidebar'] ?? null) ? $menus['sidebar'] : [],
        ];
    }

    /**
     * @return array{display_latest_posts: bool, latest_posts_limit: int}
     */
    public function rawSidebarSettings(): array
    {
        $settings = $this->currentConfig()['sidebar_settings'] ?? [];

        return [
            'display_latest_posts' => $this->boolValue($settings['display_latest_posts'] ?? false),
            'latest_posts_limit' => $this->latestPostsLimit($settings['latest_posts_limit'] ?? 5),
        ];
    }

    public function sidebarLatestPostsEnabled(): bool
    {
        return $this->rawSidebarSettings()['display_latest_posts'];
    }

    public function sidebarLatestPostsLimit(): int
    {
        return $this->rawSidebarSettings()['latest_posts_limit'];
    }

    /**
     * @param array<string, list<array<string, string>>> $menus
     * @param array<string, mixed> $sidebarSettings
     */
    public function save(array $menus, array $sidebarSettings = []): void
    {
        $normalized = [
            'menus' => [
                'primary' => $this->normalizeMenuRows($menus['primary'] ?? []),
                'footer' => $this->normalizeMenuRows($menus['footer'] ?? []),
                'sidebar' => $this->normalizeMenuRows($menus['sidebar'] ?? []),
            ],
            'sidebar_settings' => [
                'display_latest_posts' => $this->boolValue($sidebarSettings['display_latest_posts'] ?? false),
                'latest_posts_limit' => $this->latestPostsLimit($sidebarSettings['latest_posts_limit'] ?? 5),
            ],
        ];

        $this->configWriter->write($this->systemPath . '/navigation.php', $normalized);
    }

    /**
     * @param list<ContentRecord> $pages
     * @return list<NavigationItem>
     */
    private function configuredMenuItems(string $menuName, array $pages): array
    {
        $menus = $this->rawMenus();
        $pageMap = [];

        foreach ($pages as $page) {
            $pageMap[$page->id] = $page;
        }

        $items = [];

        foreach ($menus[$menuName] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = $this->string($row['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $contentId = $this->nullableString($row['content_id'] ?? '');
            $page = $contentId !== null ? ($pageMap[$contentId] ?? null) : null;
            $label = $this->string($row['label'] ?? '');
            $url = $this->string($row['url'] ?? '');

            if ($page !== null) {
                $label = $label !== '' ? $label : ($page->navigationTitle !== '' ? $page->navigationTitle : $page->title);
                $url = $url !== '' ? $url : $page->slug;
            }

            if ($label === '' || $url === '') {
                continue;
            }

            $items[] = new NavigationItem(
                id: $id,
                label: $label,
                url: $url,
                target: in_array($this->string($row['target'] ?? '_self'), ['_self', '_blank'], true) ? $this->string($row['target'] ?? '_self') : '_self',
                parentId: $this->string($row['parent_id'] ?? ''),
                sortOrder: $this->intValue($row['sort_order'] ?? '0'),
                contentId: $contentId,
            );
        }

        return $items;
    }

    /**
     * @param list<ContentRecord> $pages
     * @return list<NavigationItem>
     */
    private function defaultPageItems(array $pages): array
    {
        $items = [];

        foreach ($pages as $page) {
            if ($page->type !== 'page' || $page->status !== 'published' || !$page->showInNavigation) {
                continue;
            }

            $items[] = new NavigationItem(
                id: $page->id,
                label: $page->navigationTitle !== '' ? $page->navigationTitle : $page->title,
                url: $page->slug,
                target: '_self',
                parentId: $page->parentId ?? '',
                sortOrder: $page->menuOrder,
                contentId: $page->id,
            );
        }

        return $items;
    }

    /**
     * @param list<NavigationItem> $items
     * @return list<NavigationItem>
     */
    private function buildTree(array $items): array
    {
        usort($items, static fn (NavigationItem $a, NavigationItem $b): int => [$a->sortOrder, $a->label] <=> [$b->sortOrder, $b->label]);

        $byParent = [];
        foreach ($items as $item) {
            $byParent[$item->parentId][] = $item;
        }

        $build = function (string $parentId) use (&$build, $byParent): array {
            $children = [];

            foreach ($byParent[$parentId] ?? [] as $item) {
                $children[] = new NavigationItem(
                    id: $item->id,
                    label: $item->label,
                    url: $item->url,
                    target: $item->target,
                    parentId: $item->parentId,
                    sortOrder: $item->sortOrder,
                    contentId: $item->contentId,
                    children: $build($item->id),
                );
            }

            return $children;
        };

        return $build('');
    }

    /**
     * @param list<array<string, string>> $rows
     * @return list<array<string, string>>
     */
    private function normalizeMenuRows(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = $this->string($row['id'] ?? '');
            if ($id === '') {
                $id = 'nav_' . bin2hex(random_bytes(6));
            }
            $label = $this->string($row['label'] ?? '');
            $url = $this->string($row['url'] ?? '');
            $contentId = $this->string($row['content_id'] ?? '');

            if ($id === '' || ($label === '' && $url === '' && $contentId === '')) {
                continue;
            }

            $normalized[] = [
                'id' => $id,
                'label' => $label,
                'url' => $url,
                'target' => in_array($this->string($row['target'] ?? '_self'), ['_self', '_blank'], true) ? $this->string($row['target'] ?? '_self') : '_self',
                'parent_id' => $this->string($row['parent_id'] ?? ''),
                'sort_order' => (string) $this->intValue($row['sort_order'] ?? '0'),
                'content_id' => $contentId,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function currentConfig(): array
    {
        $runtimePath = $this->systemPath . '/navigation.php';

        if (!is_file($runtimePath)) {
            return $this->navigationConfig;
        }

        $runtimeConfig = require $runtimePath;

        if (!is_array($runtimeConfig)) {
            return $this->navigationConfig;
        }

        return array_replace($this->navigationConfig, $runtimeConfig);
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = $this->string($value);
        return $value !== '' ? $value : null;
    }

    private function intValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return 0;
    }

    private function boolValue(mixed $value): bool
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

    private function latestPostsLimit(mixed $value): int
    {
        $limit = $this->intValue($value);

        if ($limit < 1) {
            $limit = 5;
        }

        return min(20, $limit);
    }
}
