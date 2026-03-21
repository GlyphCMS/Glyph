<?php

declare(strict_types=1);

use Glyph\ui\admin\NavigationPageRenderer;

$renderer = new NavigationPageRenderer();
$html = $renderer->render(
    menus: [
        'primary' => [
            ['id' => 'docs', 'label' => 'Docs', 'url' => '/docs', 'target' => '_self', 'parent_id' => '', 'sort_order' => '0', 'content_id' => ''],
        ],
        'footer' => [],
        'sidebar' => [],
    ],
    pageOptions: [],
    sidebarSettings: [
        'display_latest_posts' => true,
        'latest_posts_limit' => 5,
    ],
    csrfToken: 'token',
    successMessage: 'Navigation saved successfully.',
    errorMessage: null,
);

if (!str_contains($html, 'Navigation saved successfully.')) {
    return false;
}

if (!str_contains($html, 'class="navigation-layout"')) {
    return false;
}

if (!str_contains($html, 'class="navigation-tabs" role="tablist"')) {
    return false;
}

if (!str_contains($html, 'class="navigation-tab js-navigation-tab is-active" data-tab="primary"')) {
    return false;
}

if (!str_contains($html, 'data-tab-panel="sidebar" role="tabpanel"')) {
    return false;
}

if (!str_contains($html, 'Your active tab is remembered so you can save and land back where you were working.')) {
    return false;
}

if (!str_contains($html, 'name="menus[primary][0][label]" value="Docs"')) {
    return false;
}

if (!str_contains($html, 'name="menus[primary][1][label]" value=""')) {
    return false;
}

if (!str_contains($html, 'Quick add row')) {
    return false;
}

if (!str_contains($html, 'name="menus[sidebar][0][label]" value=""')) {
    return false;
}

if (!str_contains($html, 'name="sidebar[latest_posts_limit]" type="number" min="1" max="20" value="5"')) {
    return false;
}

return str_contains($html, 'name="sidebar[display_latest_posts]" type="checkbox" value="1" checked');