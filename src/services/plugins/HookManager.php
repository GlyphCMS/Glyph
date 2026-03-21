<?php

declare(strict_types=1);

namespace Glyph\services\plugins;

final class HookManager
{
    /**
     * @var array<string, list<callable>>
     */
    private array $actions = [];

    /**
     * @var array<string, list<callable>>
     */
    private array $filters = [];

    /**
     * @var array<string, list<callable>>
     */
    private array $slots = [];

    /**
     * @var array<string, PluginAdminPage>
     */
    private array $adminPages = [];

    public function addAction(string $hookName, callable $listener): void
    {
        $this->actions[$hookName] ??= [];
        $this->actions[$hookName][] = $listener;
    }

    public function doAction(string $hookName, mixed ...$arguments): void
    {
        foreach ($this->actions[$hookName] ?? [] as $listener) {
            $listener(...$arguments);
        }
    }

    public function addFilter(string $hookName, callable $listener): void
    {
        $this->filters[$hookName] ??= [];
        $this->filters[$hookName][] = $listener;
    }

    public function applyFilters(string $hookName, mixed $value, mixed ...$arguments): mixed
    {
        $filteredValue = $value;

        foreach ($this->filters[$hookName] ?? [] as $listener) {
            $filteredValue = $listener($filteredValue, ...$arguments);
        }

        return $filteredValue;
    }

    public function addSlot(string $slotName, callable $renderer): void
    {
        $this->slots[$slotName] ??= [];
        $this->slots[$slotName][] = $renderer;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function renderSlot(string $slotName, array $context = []): string
    {
        $output = '';

        foreach ($this->slots[$slotName] ?? [] as $renderer) {
            $result = $renderer($context);

            if (is_string($result) && $result !== '') {
                $output .= $result;
            }
        }

        return $output;
    }

    public function registerAdminPage(PluginAdminPage $page): void
    {
        $this->adminPages[$page->pageKey] = $page;
    }

    public function findAdminPage(string $pageKey): ?PluginAdminPage
    {
        return $this->adminPages[$pageKey] ?? null;
    }

    /**
     * @return list<PluginAdminPage>
     */
    public function adminPages(): array
    {
        return array_values($this->adminPages);
    }

    /**
     * @return list<PluginAdminPage>
     */
    public function adminPagesForPlugin(string $pluginDirectoryName): array
    {
        return array_values(array_filter(
            $this->adminPages(),
            static fn (PluginAdminPage $page): bool => $page->pluginDirectoryName === $pluginDirectoryName
        ));
    }
}
