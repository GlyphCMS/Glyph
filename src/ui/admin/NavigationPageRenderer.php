<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\ui\shared\DocumentRenderer;

final class NavigationPageRenderer
{
    /**
     * @param array<string, list<array<string, string>>> $menus
     * @param array<string, string> $pageOptions
     * @param array{display_latest_posts: bool, latest_posts_limit: int} $sidebarSettings
     */
    public function render(array $menus, array $pageOptions, array $sidebarSettings, string $csrfToken, ?string $successMessage, ?string $errorMessage): string
    {
        $document = new DocumentRenderer();

        $primaryRows = $menus['primary'] ?? [];
        $footerRows = $menus['footer'] ?? [];
        $sidebarRows = $menus['sidebar'] ?? [];
        $widgetSummary = $this->sidebarWidgetSummary($sidebarSettings);

        $content = '<main class="page-shell stack">';
        $content .= '<section class="hero"><div class="toolbar"><div><p class="hero__eyebrow">Glyph Navigation</p><h1 class="hero__title">Manage menus and sidebar</h1><p class="hero__text">Switch between focused tabs for the primary menu, footer menu, and content sidebar instead of wading through one long page. Each tab still keeps a ready-to-fill blank row for the next link you want to add.</p></div><a class="button button-secondary" href="/admin">Back to dashboard</a></div></section>';

        if ($successMessage !== null && $successMessage !== '') {
            $content .= '<div class="notice notice--success"><p><strong>' . $document->escape($successMessage) . '</strong></p></div>';
        }

        if ($errorMessage !== null && $errorMessage !== '') {
            $content .= '<div class="notice notice--error"><p><strong>' . $document->escape($errorMessage) . '</strong></p></div>';
        }

        $content .= '<form method="post" action="/admin/navigation" class="navigation-layout">';
        $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($csrfToken) . '">';
        $content .= '<div class="navigation-main stack">';
        $content .= '<section class="navigation-tabs" role="tablist" aria-label="Navigation sections">';
        $content .= $this->tabButton('primary', 'Primary menu', $this->countConfiguredRows($primaryRows), $this->menuDetailSummary($primaryRows), true, $document);
        $content .= $this->tabButton('footer', 'Footer menu', $this->countConfiguredRows($footerRows), $this->menuDetailSummary($footerRows), false, $document);
        $content .= $this->tabButton('sidebar', 'Content sidebar', $this->countConfiguredRows($sidebarRows), $this->menuDetailSummary($sidebarRows), false, $document);
        $content .= '</section>';
        $content .= $this->tabPanel(
            'primary',
            $this->menuSection(
                name: 'primary',
                title: 'Primary Menu',
                description: 'Header links shown across the site. Use page links when you want URLs to follow a published page automatically.',
                rows: $primaryRows,
                pageOptions: $pageOptions,
                document: $document,
            ),
            true,
            $document,
        );
        $content .= $this->tabPanel(
            'footer',
            $this->menuSection(
                name: 'footer',
                title: 'Footer Menu',
                description: 'A quieter place for support, legal, and secondary navigation links.',
                rows: $footerRows,
                pageOptions: $pageOptions,
                document: $document,
            ),
            false,
            $document,
        );
        $content .= $this->tabPanel(
            'sidebar',
            $this->sidebarSettingsSection($sidebarSettings, $document)
                . $this->menuSection(
                    name: 'sidebar',
                    title: 'Content Sidebar Links',
                    description: 'Optional links that appear beside posts and pages whenever the content sidebar has something to show.',
                    rows: $sidebarRows,
                    pageOptions: $pageOptions,
                    document: $document,
                ),
            false,
            $document,
        );
        $content .= '</div>';
        $content .= '<aside class="navigation-sidebar">';
        $content .= '<div class="navigation-sidebar__sticky">';
        $content .= '<section class="sidebar-section">';
        $content .= '<h2 class="sidebar-section__title">Save Changes</h2>';
        $content .= '<button type="submit" class="button button-primary navigation-save-button">Save navigation</button>';
        $content .= '<p class="muted">Everything on this page still saves together. Your active tab is remembered so you can save and land back where you were working.</p>';
        $content .= '</section>';
        $content .= '<section class="sidebar-section">';
        $content .= '<h2 class="sidebar-section__title">At A Glance</h2>';
        $content .= '<ul class="navigation-summary-list">';
        $content .= $this->summaryItem('Primary menu', 'primary', $this->countConfiguredRows($primaryRows), $this->menuDetailSummary($primaryRows), $document);
        $content .= $this->summaryItem('Footer menu', 'footer', $this->countConfiguredRows($footerRows), $this->menuDetailSummary($footerRows), $document);
        $content .= $this->summaryItem('Sidebar links', 'sidebar', $this->countConfiguredRows($sidebarRows), $this->menuDetailSummary($sidebarRows), $document);
        $content .= '<li class="navigation-summary-item"><span class="navigation-summary-item__label">Latest posts widget</span><strong class="navigation-summary-item__value" data-sidebar-widget-label>' . $document->escape($widgetSummary['label']) . '</strong><span class="navigation-summary-item__meta" data-sidebar-widget-detail>' . $document->escape($widgetSummary['detail']) . '</span></li>';
        $content .= '</ul>';
        $content .= '</section>';
        $content .= '<section class="sidebar-section">';
        $content .= '<h2 class="sidebar-section__title">Editor Tips</h2>';
        $content .= '<ul class="navigation-tip-list">';
        $content .= '<li>Pick <strong>Page Link</strong> when you want the menu item to follow a published page path automatically.</li>';
        $content .= '<li>Use <strong>Parent Item</strong> to create nested frontend menus without losing track of the relationship in the editor.</li>';
        $content .= '<li>The content sidebar tab combines the latest posts widget settings and sidebar links in one place.</li>';
        $content .= '</ul>';
        $content .= '</section>';
        $content .= '</div>';
        $content .= '</aside>';
        $content .= '</form>';
        $content .= $this->script($pageOptions, $document);
        $content .= '</main>';

        return $document->render('Navigation', $content, 'Manage Glyph navigation menus.', 'theme-admin');
    }
    private function tabButton(string $tabName, string $title, int $count, string $detail, bool $isActive, DocumentRenderer $document): string
    {
        $tabId = 'nav-tab-' . $tabName;
        $panelId = 'nav-tab-panel-' . $tabName;
        $html = '<button type="button" id="' . $document->escape($tabId) . '" class="navigation-tab js-navigation-tab' . ($isActive ? ' is-active' : '') . '" data-tab="' . $document->escape($tabName) . '" role="tab" aria-selected="' . ($isActive ? 'true' : 'false') . '" aria-controls="' . $document->escape($panelId) . '" tabindex="' . ($isActive ? '0' : '-1') . '">';
        $html .= '<span class="navigation-tab__eyebrow">Navigation tab</span>';
        $html .= '<strong class="navigation-tab__title">' . $document->escape($title) . '</strong>';
        $html .= '<span class="navigation-tab__count" data-menu-count-display="' . $document->escape($tabName) . '">' . $document->escape($this->countLabel($count)) . '</span>';
        $html .= '<span class="navigation-tab__detail" data-menu-detail-display="' . $document->escape($tabName) . '">' . $document->escape($detail) . '</span>';
        $html .= '</button>';

        return $html;
    }

    private function tabPanel(string $tabName, string $content, bool $isActive, DocumentRenderer $document): string
    {
        $panelId = 'nav-tab-panel-' . $tabName;
        $tabId = 'nav-tab-' . $tabName;

        return '<section id="' . $document->escape($panelId) . '" class="navigation-tab-panel stack' . ($isActive ? ' is-active' : '') . '" data-tab-panel="' . $document->escape($tabName) . '" role="tabpanel" aria-labelledby="' . $document->escape($tabId) . '"' . ($isActive ? '' : ' hidden') . '>' . $content . '</section>';
    }
    private function summaryItem(string $label, string $menuName, int $count, string $detail, DocumentRenderer $document): string
    {
        $html = '<li class="navigation-summary-item">';
        $html .= '<span class="navigation-summary-item__label">' . $document->escape($label) . '</span>';
        $html .= '<strong class="navigation-summary-item__value" data-menu-count-display="' . $document->escape($menuName) . '">' . $document->escape($this->countLabel($count)) . '</strong>';
        $html .= '<span class="navigation-summary-item__meta" data-menu-detail-display="' . $document->escape($menuName) . '">' . $document->escape($detail) . '</span>';
        $html .= '</li>';

        return $html;
    }

    /**
     * @param list<array<string, string>> $rows
     * @param array<string, string> $pageOptions
     */
    private function menuSection(string $name, string $title, string $description, array $rows, array $pageOptions, DocumentRenderer $document): string
    {
        $savedRows = $this->configuredRows($rows);
        $renderRows = $savedRows === [] ? [$this->emptyRow()] : [...$savedRows, $this->emptyRow()];
        $count = $this->countConfiguredRows($savedRows);

        $section = '<section id="nav-section-' . $document->escape($name) . '" class="panel stack navigation-menu-section">';
        $section .= '<div class="navigation-menu-section__header">';
        $section .= '<div><p class="kicker">Menu</p><h2 class="page-title">' . $document->escape($title) . '</h2><p class="page-subtitle">' . $document->escape($description) . '</p></div>';
        $section .= '<div class="navigation-menu-section__actions">';
        $section .= '<div class="navigation-menu-section__meta">';
        $section .= '<span class="badge" data-menu-count-display="' . $document->escape($name) . '">' . $document->escape($this->countLabel($count)) . '</span>';
        $section .= '<span class="badge" data-menu-detail-display="' . $document->escape($name) . '">' . $document->escape($this->menuDetailSummary($savedRows)) . '</span>';
        $section .= '</div>';
        $section .= '<button type="button" class="button button-secondary js-add-nav-item" data-menu="' . $document->escape($name) . '">Add item</button>';
        $section .= '</div>';
        $section .= '</div>';
        $section .= '<div class="navigation-empty-state' . ($count > 0 ? ' is-hidden' : '') . '" data-menu-empty-display="' . $document->escape($name) . '"><strong>No links saved here yet.</strong><p>Use the quick add row below to start with a page link or custom URL. The row stays ready until you need it.</p></div>';
        $section .= '<div class="navigation-item-list js-nav-menu" data-menu="' . $document->escape($name) . '">';

        foreach ($renderRows as $index => $row) {
            $section .= $this->row($name, (string) $index, $row, $savedRows, $pageOptions, $document);
        }

        $section .= '</div></section>';

        return $section;
    }
    /**
     * @param array{display_latest_posts: bool, latest_posts_limit: int} $sidebarSettings
     */
    private function sidebarSettingsSection(array $sidebarSettings, DocumentRenderer $document): string
    {
        $checked = $sidebarSettings['display_latest_posts'] ? ' checked' : '';
        $limit = (string) $sidebarSettings['latest_posts_limit'];
        $widgetSummary = $this->sidebarWidgetSummary($sidebarSettings);
        $limitHelp = $sidebarSettings['display_latest_posts']
            ? 'Choose how many recent posts to show when the latest posts widget is enabled.'
            : 'Turn on latest posts to use this limit.';

        $section = '<section id="nav-section-sidebar-settings" class="panel stack navigation-menu-section">';
        $section .= '<div class="navigation-menu-section__header">';
        $section .= '<div><p class="kicker">Sidebar</p><h2 class="page-title">Content Sidebar Behavior</h2><p class="page-subtitle">The frontend sidebar appears automatically whenever sidebar links exist or the latest posts widget is enabled.</p></div>';
        $section .= '<div class="navigation-menu-section__meta">';
        $section .= '<span class="badge" data-sidebar-widget-label>' . $document->escape($widgetSummary['label']) . '</span>';
        $section .= '<span class="badge" data-sidebar-widget-detail>' . $document->escape($widgetSummary['detail']) . '</span>';
        $section .= '</div>';
        $section .= '</div>';
        $section .= '<div class="toggle-row">';
        $section .= '<div class="toggle-row__body"><label class="toggle-row__label" for="sidebar_display_latest_posts">Display latest posts</label><p class="toggle-row__help">Show recent published posts in the content sidebar on blog posts and pages.</p></div>';
        $section .= '<label class="toggle-switch" for="sidebar_display_latest_posts"><input id="sidebar_display_latest_posts" name="sidebar[display_latest_posts]" type="checkbox" value="1"' . $checked . '><span class="toggle-switch__track"><span class="toggle-switch__thumb"></span></span></label>';
        $section .= '</div>';
        $section .= '<div class="form-grid form-grid--two">';
        $section .= '<div class="field' . ($sidebarSettings['display_latest_posts'] ? '' : ' is-disabled') . '">';
        $section .= '<label for="sidebar_latest_posts_limit">Latest posts count</label>';
        $section .= '<input id="sidebar_latest_posts_limit" data-role="sidebar-limit" name="sidebar[latest_posts_limit]" type="number" min="1" max="20" value="' . $document->escape($limit) . '"' . ($sidebarSettings['display_latest_posts'] ? '' : ' disabled') . '>';
        $section .= '<small class="field-help" data-role="sidebar-limit-help">' . $document->escape($limitHelp) . '</small>';
        $section .= '</div>';
        $section .= '<div class="navigation-sidebar-settings__panel">';
        $section .= '<h3 class="navigation-sidebar-settings__title">When the sidebar shows</h3>';
        $section .= '<p class="muted">Sidebar links and latest posts work together. If both are off or empty, the frontend keeps the layout clean and hides the sidebar completely.</p>';
        $section .= '</div>';
        $section .= '</div></section>';

        return $section;
    }

    /**
     * @param array<string, string> $row
     * @param list<array<string, string>> $rows
     * @param array<string, string> $pageOptions
     */
    private function row(string $menu, string $index, array $row, array $rows, array $pageOptions, DocumentRenderer $document): string
    {
        $id = trim((string) ($row['id'] ?? ''));
        $parentOptions = ['' => 'No parent'];

        foreach ($rows as $candidate) {
            $candidateId = trim((string) ($candidate['id'] ?? ''));
            if ($candidateId === '' || $candidateId === $id) {
                continue;
            }

            $candidateLabel = trim((string) ($candidate['label'] ?? ''));
            $candidateUrl = trim((string) ($candidate['url'] ?? ''));
            $candidatePageLabel = $this->pageLabelForRow($candidate, $pageOptions);
            $parentOptions[$candidateId] = $candidateLabel !== ''
                ? $candidateLabel
                : ($candidatePageLabel !== '' ? $candidatePageLabel : ($candidateUrl !== '' ? $candidateUrl : $candidateId));
        }

        $details = $this->describeRow($row, $pageOptions);
        $cardClass = 'nav-item-card' . ($details['is_blank'] ? ' nav-item-card--blank' : '');

        $html = '<article class="' . $document->escape($cardClass) . '">';
        $html .= '<input type="hidden" name="' . $document->escape($this->inputName($menu, $index, 'id')) . '" value="' . $document->escape($id) . '">';
        $html .= '<div class="nav-item-card__header">';
        $html .= '<div class="nav-item-card__identity">';
        $html .= '<p class="nav-item-card__eyebrow">' . $document->escape($details['is_blank'] ? 'Quick add row' : 'Configured item') . '</p>';
        $html .= '<h3 class="nav-item-card__title" data-role="item-title">' . $document->escape($details['title']) . '</h3>';
        $html .= '<p class="nav-item-card__preview" data-role="item-preview">' . $document->escape($details['preview']) . '</p>';
        $html .= '</div>';
        $html .= '<div class="nav-item-card__actions">';
        $html .= '<div class="nav-item-card__badges" data-role="item-badges">' . $this->renderBadges($details['badges'], $document) . '</div>';
        $html .= '<button type="button" class="button button-secondary nav-item-card__remove js-remove-nav-item' . ($details['is_blank'] ? ' is-hidden' : '') . '"' . ($details['is_blank'] ? ' disabled' : '') . '>Remove</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="form-grid form-grid--two nav-item-card__grid">';
        $html .= $this->textField(
            menu: $menu,
            index: $index,
            name: 'label',
            label: 'Label',
            value: $row['label'] ?? '',
            help: 'The text visitors will click in the menu.',
            document: $document,
            attributes: [
                'placeholder' => 'About, Docs, Contact',
            ],
        );
        $html .= $this->selectField(
            menu: $menu,
            index: $index,
            name: 'content_id',
            label: 'Page Link',
            current: $row['content_id'] ?? '',
            options: ['' => 'Custom URL / none'] + $pageOptions,
            help: 'Pick a published page to reuse its menu label fallback and frontend path.',
            document: $document,
        );
        $html .= $this->textField(
            menu: $menu,
            index: $index,
            name: 'url',
            label: 'URL',
            value: $row['url'] ?? '',
            help: 'Leave blank to follow the selected page path, or enter a custom path or full URL.',
            document: $document,
            attributes: [
                'placeholder' => '/about or https://example.com',
            ],
        );
        $html .= $this->selectField(
            menu: $menu,
            index: $index,
            name: 'parent_id',
            label: 'Parent Item',
            current: $row['parent_id'] ?? '',
            options: $parentOptions,
            help: 'Choose another item to nest this one beneath it on the frontend.',
            document: $document,
        );
        $html .= $this->selectField(
            menu: $menu,
            index: $index,
            name: 'target',
            label: 'Target',
            current: $row['target'] ?? '_self',
            options: ['_self' => 'Same tab', '_blank' => 'New tab'],
            help: 'Use a new tab only when that browsing jump is intentional.',
            document: $document,
        );
        $html .= $this->textField(
            menu: $menu,
            index: $index,
            name: 'sort_order',
            label: 'Sort Order',
            value: $row['sort_order'] ?? '0',
            help: 'Lower numbers float higher in the menu.',
            document: $document,
            attributes: [
                'type' => 'number',
                'step' => '1',
                'inputmode' => 'numeric',
            ],
            wrapperClass: 'field field--compact',
        );
        $html .= '</div>';
        $html .= '<p class="nav-item-card__hint" data-role="item-hint">' . $document->escape($details['hint']) . '</p>';
        $html .= '</article>';

        return $html;
    }

    /**
     * @param list<string> $labels
     */
    private function renderBadges(array $labels, DocumentRenderer $document): string
    {
        $html = '';

        foreach ($labels as $label) {
            $html .= '<span class="badge">' . $document->escape($label) . '</span>';
        }

        return $html;
    }

    /**
     * @param array<string, string|int|bool> $attributes
     */
    private function textField(string $menu, string $index, string $name, string $label, string $value, string $help, DocumentRenderer $document, array $attributes = [], string $wrapperClass = 'field'): string
    {
        $inputId = $this->inputId($menu, $index, $name);
        $resolvedAttributes = ['type' => 'text'] + $attributes;

        $html = '<div class="' . $document->escape($wrapperClass) . '">';
        $html .= '<label for="' . $document->escape($inputId) . '">' . $document->escape($label) . '</label>';
        $html .= '<input id="' . $document->escape($inputId) . '" name="' . $document->escape($this->inputName($menu, $index, $name)) . '" value="' . $document->escape($value) . '"' . $this->attributes($resolvedAttributes, $document) . '>';
        $html .= '<small class="field-help">' . $document->escape($help) . '</small>';
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string, string> $options
     */
    private function selectField(string $menu, string $index, string $name, string $label, string $current, array $options, string $help, DocumentRenderer $document, string $wrapperClass = 'field'): string
    {
        $inputId = $this->inputId($menu, $index, $name);

        $html = '<div class="' . $document->escape($wrapperClass) . '">';
        $html .= '<label for="' . $document->escape($inputId) . '">' . $document->escape($label) . '</label>';
        $html .= '<select id="' . $document->escape($inputId) . '" name="' . $document->escape($this->inputName($menu, $index, $name)) . '">';

        foreach ($options as $value => $optionLabel) {
            $selected = $value === $current ? ' selected' : '';
            $html .= '<option value="' . $document->escape($value) . '"' . $selected . '>' . $document->escape($optionLabel) . '</option>';
        }

        $html .= '</select>';
        $html .= '<small class="field-help">' . $document->escape($help) . '</small>';
        $html .= '</div>';

        return $html;
    }

    private function inputName(string $menu, string $index, string $name): string
    {
        return 'menus[' . $menu . '][' . $index . '][' . $name . ']';
    }

    private function inputId(string $menu, string $index, string $name): string
    {
        return 'navigation_' . $menu . '_' . $index . '_' . $name;
    }

    /**
     * @param array<string, string|int|bool> $attributes
     */
    private function attributes(array $attributes, DocumentRenderer $document): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            if ($value === false) {
                continue;
            }

            if ($value === true) {
                $html .= ' ' . $document->escape($name);
                continue;
            }

            $html .= ' ' . $document->escape($name) . '="' . $document->escape((string) $value) . '"';
        }

        return $html;
    }
    /**
     * @param array<string, string> $pageOptions
     */
    private function script(array $pageOptions, DocumentRenderer $document): string
    {
        $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $pageOptionsJson = json_encode($pageOptions, $jsonFlags);
        $rowTemplatesJson = json_encode([
            'primary' => $this->prototypeRowMarkup('primary', $pageOptions, $document),
            'footer' => $this->prototypeRowMarkup('footer', $pageOptions, $document),
            'sidebar' => $this->prototypeRowMarkup('sidebar', $pageOptions, $document),
        ], $jsonFlags);

        if (!is_string($pageOptionsJson) || !is_string($rowTemplatesJson)) {
            throw new \RuntimeException('Failed to encode navigation page options.');
        }

        return '<script>
(() => {
    const pageOptions = ' . $pageOptionsJson . ';
    const rowTemplates = ' . $rowTemplatesJson . ';
    const tabStorageKey = "glyph-navigation-active-tab";

    const menus = [...document.querySelectorAll(".js-nav-menu")].filter((menu) => menu instanceof HTMLElement);
    const tabs = [...document.querySelectorAll(".js-navigation-tab")].filter((tab) => tab instanceof HTMLButtonElement);
    const panels = [...document.querySelectorAll("[data-tab-panel]")].filter((panel) => panel instanceof HTMLElement);
    let activeTab = tabs[0]?.dataset.tab || "primary";

    const hasTab = (tabName) => tabs.some((tab) => tab.dataset.tab === tabName);

    const activateTab = (tabName, persist = true) => {
        const resolvedTab = hasTab(tabName) ? tabName : (tabs[0]?.dataset.tab || "primary");
        activeTab = resolvedTab;

        tabs.forEach((tab) => {
            const isActive = tab.dataset.tab === resolvedTab;
            tab.classList.toggle("is-active", isActive);
            tab.setAttribute("aria-selected", isActive ? "true" : "false");
            tab.tabIndex = isActive ? 0 : -1;
        });

        panels.forEach((panel) => {
            const isActive = panel.dataset.tabPanel === resolvedTab;
            panel.hidden = !isActive;
            panel.classList.toggle("is-active", isActive);
        });

        if (!persist) {
            return;
        }

        try {
            window.localStorage.setItem(tabStorageKey, resolvedTab);
        } catch (_error) {
        }
    };

    const restoreActiveTab = () => {
        const hash = window.location.hash.replace(/^#/, "");
        const hashTab = hash.replace(/^nav-tab-panel-/, "").replace(/^nav-tab-/, "");
        let storedTab = "";

        try {
            storedTab = window.localStorage.getItem(tabStorageKey) || "";
        } catch (_error) {
        }

        if (hasTab(hashTab)) {
            activateTab(hashTab, false);
            return;
        }

        if (hasTab(storedTab)) {
            activateTab(storedTab, false);
            return;
        }

        activateTab(activeTab, false);
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener("click", () => {
            activateTab(tab.dataset.tab || "primary");
        });

        tab.addEventListener("keydown", (event) => {
            if (!["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key)) {
                return;
            }

            event.preventDefault();

            if (event.key === "Home") {
                tabs[0]?.focus();
                activateTab(tabs[0]?.dataset.tab || "primary");
                return;
            }

            if (event.key === "End") {
                const lastTab = tabs[tabs.length - 1];
                lastTab?.focus();
                activateTab(lastTab?.dataset.tab || "primary");
                return;
            }

            const direction = event.key === "ArrowRight" ? 1 : -1;
            const nextTab = tabs[(index + direction + tabs.length) % tabs.length];
            nextTab?.focus();
            activateTab(nextTab?.dataset.tab || "primary");
        });
    });

    const form = document.querySelector("form.navigation-layout");
    if (form instanceof HTMLFormElement) {
        form.addEventListener("submit", () => {
            try {
                window.localStorage.setItem(tabStorageKey, activeTab);
            } catch (_error) {
            }
        });
    }

    const rowIdentifier = (row) => row.querySelector("input[name$=\"[id]\"]")?.value || "";

    const selectedPageLabel = (row) => {
        const select = row.querySelector("select[name$=\"[content_id]\"]");
        if (!(select instanceof HTMLSelectElement)) {
            return "";
        }

        const contentId = select.value || "";
        if (contentId === "") {
            return "";
        }

        return pageOptions[contentId] || select.selectedOptions[0]?.textContent?.trim() || "";
    };

    const rowState = (row) => {
        const label = row.querySelector("input[name$=\"[label]\"]")?.value.trim() || "";
        const url = row.querySelector("input[name$=\"[url]\"]")?.value.trim() || "";
        const contentId = row.querySelector("select[name$=\"[content_id]\"]")?.value || "";
        const target = row.querySelector("select[name$=\"[target]\"]")?.value || "_self";
        const parentId = row.querySelector("select[name$=\"[parent_id]\"]")?.value || "";
        const pageLabel = selectedPageLabel(row);
        const isBlank = label === "" && url === "" && contentId === "";
        const hasDestination = pageLabel !== "" || url !== "";

        let title = "Quick add item";
        if (label !== "") {
            title = label;
        } else if (pageLabel !== "") {
            title = pageLabel;
        } else if (url !== "") {
            title = url;
        }

        let preview = "Start typing or choose a page to create the next menu item.";
        let hint = "Blank rows are ignored on save. Another fresh row stays ready underneath your configured items.";
        const badges = [];

        if (!isBlank && !hasDestination) {
            preview = "Add a page link or URL to finish this item.";
            hint = "This row stays in the editor, but it will not render on the frontend until it has a destination.";
            badges.push("Needs destination");
        } else if (!isBlank && pageLabel !== "" && url !== "") {
            preview = `Uses ${pageLabel} with a custom URL override.`;
            hint = "Leave the URL blank if you want this item to follow the selected page path automatically.";
            badges.push("Page link", "Custom path");
        } else if (!isBlank && pageLabel !== "") {
            preview = `Uses the published page path for ${pageLabel}.`;
            hint = "Add a label override if you want shorter menu text than the page title.";
            badges.push("Page link");
        } else if (!isBlank && url !== "") {
            preview = `Custom link to ${url}.`;
            hint = "Use relative paths for internal links or full URLs for external destinations.";
            badges.push("Custom URL");
        }

        if (!isBlank && parentId !== "") {
            badges.push("Nested");
        }

        if (!isBlank && target === "_blank") {
            badges.push("New tab");
        }

        return {
            badges,
            hasDestination,
            isBlank,
            parentId,
            preview,
            hint,
            title,
            url,
            pageLabel,
        };
    };

    const isBlankRow = (row) => rowState(row).isBlank;

    const configuredRows = (menuElement) => [...menuElement.querySelectorAll(".nav-item-card")].filter((row) => row instanceof HTMLElement && !isBlankRow(row));

    const nextRowIndex = (menuElement) => {
        const indexes = [...menuElement.querySelectorAll("input[name$=\"[id]\"]")]
            .map((field) => field.getAttribute("name") || "")
            .map((name) => {
                const match = name.match(/\[(\d+)\]\[id\]$/);
                return match ? Number.parseInt(match[1], 10) : -1;
            })
            .filter((value) => Number.isInteger(value) && value >= 0);

        if (indexes.length === 0) {
            return 0;
        }

        return Math.max(...indexes) + 1;
    };

    const renderBadges = (container, labels) => {
        if (!(container instanceof HTMLElement)) {
            return;
        }

        container.innerHTML = "";
        labels.forEach((label) => {
            const badge = document.createElement("span");
            badge.className = "badge";
            badge.textContent = label;
            container.appendChild(badge);
        });
    };

    const syncRowPresentation = (row) => {
        const state = rowState(row);
        row.classList.toggle("nav-item-card--blank", state.isBlank);

        const title = row.querySelector("[data-role=\"item-title\"]");
        if (title instanceof HTMLElement) {
            title.textContent = state.title;
        }

        const preview = row.querySelector("[data-role=\"item-preview\"]");
        if (preview instanceof HTMLElement) {
            preview.textContent = state.preview;
        }

        const hint = row.querySelector("[data-role=\"item-hint\"]");
        if (hint instanceof HTMLElement) {
            hint.textContent = state.hint;
        }

        renderBadges(row.querySelector("[data-role=\"item-badges\"]"), state.badges);

        const removeButton = row.querySelector(".js-remove-nav-item");
        if (removeButton instanceof HTMLButtonElement) {
            removeButton.classList.toggle("is-hidden", state.isBlank);
            removeButton.disabled = state.isBlank;
        }
    };

    const createRow = (menuName, index) => {
        const template = rowTemplates[menuName] || "";
        const id = `nav_${menuName}_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
        const wrapper = document.createElement("div");
        wrapper.innerHTML = template.split("__INDEX__").join(String(index)).split("__ROW_ID__").join(id).trim();
        const row = wrapper.firstElementChild;
        if (!(row instanceof HTMLElement)) {
            throw new Error("Failed to create navigation row.");
        }
        syncRowPresentation(row);
        return row;
    };

    const rebuildParentOptions = (menuElement) => {
        const items = configuredRows(menuElement).map((row) => {
            const state = rowState(row);
            return {
                id: rowIdentifier(row),
                label: state.title,
            };
        });

        [...menuElement.querySelectorAll(".nav-item-card")].forEach((row) => {
            if (!(row instanceof HTMLElement)) {
                return;
            }

            const currentId = rowIdentifier(row);
            const select = row.querySelector("select[name$=\"[parent_id]\"]");
            if (!(select instanceof HTMLSelectElement)) {
                return;
            }

            const selected = select.value;
            let html = "<option value=\"\">No parent</option>";
            items.forEach((item) => {
                if (!item.id || item.id === currentId) {
                    return;
                }

                const safeValue = item.id
                    .replaceAll("&", "&amp;")
                    .replaceAll("<", "&lt;")
                    .replaceAll(">", "&gt;")
                    .replaceAll("\"", "&quot;");
                const safeLabel = item.label
                    .replaceAll("&", "&amp;")
                    .replaceAll("<", "&lt;")
                    .replaceAll(">", "&gt;")
                    .replaceAll("\"", "&quot;");
                html += `<option value="${safeValue}"${item.id === selected ? " selected" : ""}>${safeLabel}</option>`;
            });
            select.innerHTML = html;
        });
    };

    const countLabel = (count) => count === 1 ? "1 saved item" : `${count} saved items`;

    const detailLabel = (stats) => {
        if (stats.count === 0) {
            return "Start with a page link or custom URL.";
        }

        const parts = [];
        if (stats.linked > 0) {
            parts.push(stats.linked === 1 ? "1 page link" : `${stats.linked} page links`);
        }
        if (stats.nested > 0) {
            parts.push(stats.nested === 1 ? "1 nested item" : `${stats.nested} nested items`);
        }
        if (stats.incomplete > 0) {
            parts.push(stats.incomplete === 1 ? "1 needs a destination" : `${stats.incomplete} need destinations`);
        }

        return parts.length > 0 ? parts.join(" | ") : "Custom URLs ready to go.";
    };

    const menuStats = (menuElement) => {
        const rows = configuredRows(menuElement);
        return rows.reduce((stats, row) => {
            const state = rowState(row);
            if (state.pageLabel !== "") {
                stats.linked += 1;
            }
            if (state.parentId !== "") {
                stats.nested += 1;
            }
            if (!state.hasDestination) {
                stats.incomplete += 1;
            }
            return stats;
        }, {
            count: rows.length,
            linked: 0,
            nested: 0,
            incomplete: 0,
        });
    };

    const updateMenuSummary = (menuElement) => {
        const menuName = menuElement.dataset.menu || "primary";
        const stats = menuStats(menuElement);
        document.querySelectorAll(`[data-menu-count-display="${menuName}"]`).forEach((element) => {
            element.textContent = countLabel(stats.count);
        });
        document.querySelectorAll(`[data-menu-detail-display="${menuName}"]`).forEach((element) => {
            element.textContent = detailLabel(stats);
        });
        document.querySelectorAll(`[data-menu-empty-display="${menuName}"]`).forEach((element) => {
            element.classList.toggle("is-hidden", stats.count > 0);
        });
    };

    const ensureTrailingBlankRow = (menuElement) => {
        const menuName = menuElement.dataset.menu || "primary";
        const rows = [...menuElement.querySelectorAll(".nav-item-card")].filter((row) => row instanceof HTMLElement);

        if (rows.length === 0) {
            menuElement.appendChild(createRow(menuName, 0));
            return;
        }

        const blankRows = rows.filter((row) => isBlankRow(row));
        blankRows.slice(0, -1).forEach((row) => row.remove());

        const currentRows = [...menuElement.querySelectorAll(".nav-item-card")].filter((row) => row instanceof HTMLElement);
        const lastRow = currentRows.length > 0 ? currentRows[currentRows.length - 1] : null;
        if (!(lastRow instanceof HTMLElement) || !isBlankRow(lastRow)) {
            menuElement.appendChild(createRow(menuName, nextRowIndex(menuElement)));
        }
    };

    const syncMenu = (menuElement) => {
        ensureTrailingBlankRow(menuElement);
        [...menuElement.querySelectorAll(".nav-item-card")].forEach((row) => {
            if (row instanceof HTMLElement) {
                syncRowPresentation(row);
            }
        });
        rebuildParentOptions(menuElement);
        updateMenuSummary(menuElement);
    };

    const syncSidebarControls = () => {
        const checkbox = document.querySelector("input[name=\"sidebar[display_latest_posts]\"]");
        const limitField = document.querySelector("[data-role=\"sidebar-limit\"]");
        const help = document.querySelector("[data-role=\"sidebar-limit-help\"]");

        if (!(checkbox instanceof HTMLInputElement)) {
            return;
        }

        const enabled = checkbox.checked;
        const limitValue = limitField instanceof HTMLInputElement && limitField.value.trim() !== "" ? limitField.value.trim() : "5";
        const label = enabled ? "Latest posts on" : "Latest posts off";
        const detail = enabled
            ? `${limitValue} post${limitValue === "1" ? "" : "s"} will show when the sidebar appears.`
            : "Sidebar links alone can still make the sidebar appear.";

        if (limitField instanceof HTMLInputElement) {
            limitField.disabled = !enabled;
            limitField.closest(".field")?.classList.toggle("is-disabled", !enabled);
        }

        if (help instanceof HTMLElement) {
            help.textContent = enabled
                ? "Choose how many recent posts to show when the latest posts widget is enabled."
                : "Turn on latest posts to use this limit.";
        }

        document.querySelectorAll("[data-sidebar-widget-label]").forEach((element) => {
            element.textContent = label;
        });
        document.querySelectorAll("[data-sidebar-widget-detail]").forEach((element) => {
            element.textContent = detail;
        });
    };

    document.querySelectorAll(".js-add-nav-item").forEach((button) => {
        button.addEventListener("click", () => {
            const menuName = button.getAttribute("data-menu") || "primary";
            const menu = document.querySelector(`.js-nav-menu[data-menu="${menuName}"]`);
            if (!(menu instanceof HTMLElement)) {
                return;
            }

            syncMenu(menu);
            activateTab(menuName);

            const rows = [...menu.querySelectorAll(".nav-item-card")].filter((row) => row instanceof HTMLElement);
            const lastRow = rows.length > 0 ? rows[rows.length - 1] : null;
            const labelField = lastRow?.querySelector("input[name$=\"[label]\"]");
            if (labelField instanceof HTMLElement) {
                labelField.focus();
            }
        });
    });

    document.addEventListener("click", (event) => {
        const target = event.target;
        if (!(target instanceof Element) || !target.classList.contains("js-remove-nav-item")) {
            return;
        }

        const row = target.closest(".nav-item-card");
        const menu = target.closest(".js-nav-menu");
        if (row instanceof HTMLElement) {
            row.remove();
        }
        if (menu instanceof HTMLElement) {
            syncMenu(menu);
        }
    });

    document.addEventListener("input", (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const menu = target.closest(".js-nav-menu");
        if (menu instanceof HTMLElement) {
            syncMenu(menu);
        }

        if (target.matches("input[name=\"sidebar[latest_posts_limit]\"]")) {
            syncSidebarControls();
        }
    });

    document.addEventListener("change", (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        if (target.matches("select[name$=\"[content_id]\"]")) {
            const row = target.closest(".nav-item-card");
            if (row instanceof HTMLElement) {
                const labelField = row.querySelector("input[name$=\"[label]\"]");
                const pageLabel = selectedPageLabel(row);
                if (labelField instanceof HTMLInputElement && labelField.value.trim() === "" && pageLabel !== "") {
                    labelField.value = pageLabel;
                }
            }
        }

        const menu = target.closest(".js-nav-menu");
        if (menu instanceof HTMLElement) {
            syncMenu(menu);
        }

        if (target.matches("input[name=\"sidebar[display_latest_posts]\"], input[name=\"sidebar[latest_posts_limit]\"]")) {
            syncSidebarControls();
        }
    });

    menus.forEach((menu) => {
        syncMenu(menu);
    });
    syncSidebarControls();
    restoreActiveTab();
})();
</script>';
    }
    /**
     * @param array<string, string> $pageOptions
     */
    private function prototypeRowMarkup(string $menu, array $pageOptions, DocumentRenderer $document): string
    {
        $templateRow = $this->emptyRow();
        $templateRow['id'] = '__ROW_ID__';

        return $this->row($menu, '__INDEX__', $templateRow, [], $pageOptions, $document);
    }
    /**
     * @param list<array<string, string>> $rows
     * @return list<array<string, string>>
     */
    private function configuredRows(array $rows): array
    {
        $configured = [];

        foreach ($rows as $row) {
            if ($this->isBlankRowData($row)) {
                continue;
            }

            $configured[] = $row;
        }

        return $configured;
    }

    /**
     * @param list<array<string, string>> $rows
     */
    private function countConfiguredRows(array $rows): int
    {
        return count($this->configuredRows($rows));
    }

    /**
     * @param list<array<string, string>> $rows
     */
    private function countLinkedRows(array $rows): int
    {
        $count = 0;

        foreach ($this->configuredRows($rows) as $row) {
            if (trim((string) ($row['content_id'] ?? '')) !== '') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, string>> $rows
     */
    private function countNestedRows(array $rows): int
    {
        $count = 0;

        foreach ($this->configuredRows($rows) as $row) {
            if (trim((string) ($row['parent_id'] ?? '')) !== '') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, string>> $rows
     */
    private function countIncompleteRows(array $rows): int
    {
        $count = 0;

        foreach ($this->configuredRows($rows) as $row) {
            $hasPageLink = trim((string) ($row['content_id'] ?? '')) !== '';
            $hasUrl = trim((string) ($row['url'] ?? '')) !== '';
            if (!$hasPageLink && !$hasUrl) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, string>> $rows
     */
    private function menuDetailSummary(array $rows): string
    {
        $count = $this->countConfiguredRows($rows);
        if ($count === 0) {
            return 'Start with a page link or custom URL.';
        }

        $parts = [];
        $linked = $this->countLinkedRows($rows);
        $nested = $this->countNestedRows($rows);
        $incomplete = $this->countIncompleteRows($rows);

        if ($linked > 0) {
            $parts[] = $linked === 1 ? '1 page link' : $linked . ' page links';
        }

        if ($nested > 0) {
            $parts[] = $nested === 1 ? '1 nested item' : $nested . ' nested items';
        }

        if ($incomplete > 0) {
            $parts[] = $incomplete === 1 ? '1 needs a destination' : $incomplete . ' need destinations';
        }

        return $parts === [] ? 'Custom URLs ready to go.' : implode(' | ', $parts);
    }

    /**
     * @param array<string, string> $row
     * @param array<string, string> $pageOptions
     * @return array{badges: list<string>, hint: string, is_blank: bool, preview: string, title: string}
     */
    private function describeRow(array $row, array $pageOptions): array
    {
        $label = trim((string) ($row['label'] ?? ''));
        $url = trim((string) ($row['url'] ?? ''));
        $contentId = trim((string) ($row['content_id'] ?? ''));
        $pageLabel = $contentId !== '' ? trim((string) ($pageOptions[$contentId] ?? '')) : '';
        $target = trim((string) ($row['target'] ?? '_self')) === '_blank' ? '_blank' : '_self';
        $parentId = trim((string) ($row['parent_id'] ?? ''));
        $isBlank = $label === '' && $url === '' && $contentId === '';
        $hasDestination = $pageLabel !== '' || $url !== '';

        $title = 'Quick add item';
        if ($label !== '') {
            $title = $label;
        } elseif ($pageLabel !== '') {
            $title = $pageLabel;
        } elseif ($url !== '') {
            $title = $url;
        }

        $preview = 'Start typing or choose a page to create the next menu item.';
        $hint = 'Blank rows are ignored on save. Another fresh row stays ready underneath your configured items.';
        $badges = [];

        if (!$isBlank && !$hasDestination) {
            $preview = 'Add a page link or URL to finish this item.';
            $hint = 'This row stays in the editor, but it will not render on the frontend until it has a destination.';
            $badges[] = 'Needs destination';
        } elseif (!$isBlank && $pageLabel !== '' && $url !== '') {
            $preview = 'Uses ' . $pageLabel . ' with a custom URL override.';
            $hint = 'Leave the URL blank if you want this item to follow the selected page path automatically.';
            $badges[] = 'Page link';
            $badges[] = 'Custom path';
        } elseif (!$isBlank && $pageLabel !== '') {
            $preview = 'Uses the published page path for ' . $pageLabel . '.';
            $hint = 'Add a label override if you want shorter menu text than the page title.';
            $badges[] = 'Page link';
        } elseif (!$isBlank && $url !== '') {
            $preview = 'Custom link to ' . $url . '.';
            $hint = 'Use relative paths for internal links or full URLs for external destinations.';
            $badges[] = 'Custom URL';
        }

        if (!$isBlank && $parentId !== '') {
            $badges[] = 'Nested';
        }

        if (!$isBlank && $target === '_blank') {
            $badges[] = 'New tab';
        }

        return [
            'badges' => $badges,
            'hint' => $hint,
            'is_blank' => $isBlank,
            'preview' => $preview,
            'title' => $title,
        ];
    }

    /**
     * @param array<string, string> $row
     * @param array<string, string> $pageOptions
     */
    private function pageLabelForRow(array $row, array $pageOptions): string
    {
        $contentId = trim((string) ($row['content_id'] ?? ''));
        if ($contentId === '') {
            return '';
        }

        return trim((string) ($pageOptions[$contentId] ?? ''));
    }

    private function countLabel(int $count): string
    {
        return $count === 1 ? '1 saved item' : $count . ' saved items';
    }

    /**
     * @param array{display_latest_posts: bool, latest_posts_limit: int} $sidebarSettings
     * @return array{detail: string, label: string}
     */
    private function sidebarWidgetSummary(array $sidebarSettings): array
    {
        if (!$sidebarSettings['display_latest_posts']) {
            return [
                'detail' => 'Sidebar links alone can still make the sidebar appear.',
                'label' => 'Latest posts off',
            ];
        }

        $limit = max(1, min(20, $sidebarSettings['latest_posts_limit']));

        return [
            'detail' => $limit . ' post' . ($limit === 1 ? '' : 's') . ' will show when the sidebar appears.',
            'label' => 'Latest posts on',
        ];
    }

    /**
     * @param array<string, string> $row
     */
    private function isBlankRowData(array $row): bool
    {
        return trim((string) ($row['label'] ?? '')) === ''
            && trim((string) ($row['url'] ?? '')) === ''
            && trim((string) ($row['content_id'] ?? '')) === '';
    }

    /**
     * @return array<string, string>
     */
    private function emptyRow(): array
    {
        return [
            'id' => '',
            'label' => '',
            'url' => '',
            'target' => '_self',
            'parent_id' => '',
            'sort_order' => '0',
            'content_id' => '',
        ];
    }
}