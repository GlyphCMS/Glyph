<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\services\plugins\PluginAdminPage;
use Glyph\services\plugins\PluginData;
use Glyph\ui\shared\DocumentRenderer;

final class PluginPageRenderer
{
    /**
     * @param list<PluginData> $plugins
     * @param array<string, list<PluginAdminPage>> $pluginPages
     */
    public function render(
        array $plugins,
        array $pluginPages,
        string $uploadCsrfToken,
        string $toggleCsrfToken,
        string $deleteCsrfToken,
        ?string $successMessage,
        ?string $errorMessage,
    ): string {
        $document = new DocumentRenderer();

        $content = '<main class="page-shell stack">';
        $content .= '<section class="hero">';
        $content .= '<p class="hero__eyebrow">Glyph Plugins</p>';
        $content .= '<div class="toolbar">';
        $content .= '<div><h1 class="hero__title">Manage plugins</h1><p class="hero__text">Install, enable, disable, and remove plugins discovered in the <span class="code">plugins/</span> directory. Enabled plugins can register hooks, filters, slots, and admin pages.</p></div>';
        $content .= '<a class="button button-secondary" href="/admin">Back to dashboard</a>';
        $content .= '</div></section>';

        if ($successMessage !== null && $successMessage !== '') {
            $content .= '<div class="notice notice--success"><p><strong>' . $document->escape($successMessage) . '</strong></p></div>';
        }

        if ($errorMessage !== null && $errorMessage !== '') {
            $content .= '<div class="notice notice--error"><p><strong>' . $document->escape($errorMessage) . '</strong></p></div>';
        }

        $content .= '<section class="panel stack">';
        $content .= '<div><p class="kicker">Install</p><h2 class="page-title">Upload a plugin package</h2><p class="page-subtitle">Upload a ZIP package that contains a plugin.json manifest and bootstrap.php file at the root or inside a single top-level directory.</p></div>';
        $content .= '<form method="post" action="/admin/plugins/install" enctype="multipart/form-data" class="form-grid">';
        $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($uploadCsrfToken) . '">';
        $content .= '<div class="field"><label for="plugin_package">Plugin ZIP</label><input id="plugin_package" name="plugin_package" type="file" accept=".zip,application/zip"></div>';
        $content .= '<div class="field cluster"><button type="submit">Install Plugin</button></div>';
        $content .= '</form></section>';

        $content .= '<section class="panel stack">';
        $content .= '<div><p class="kicker">Installed Plugins</p><h2 class="page-title">Available plugins</h2><p class="page-subtitle">Plugin uploads are supported, and enabled plugins can expose their own admin pages.</p></div>';

        if ($plugins === []) {
            $content .= '<div class="notice notice--warning"><p class="empty-state">No plugins found.</p></div>';
        } else {
            $content .= '<div class="grid grid--three">';
            foreach ($plugins as $plugin) {
                $content .= '<article class="panel stack">';
                $content .= '<div class="cluster"><p class="theme-card__title">' . $document->escape($plugin->name) . '</p>';
                if ($plugin->isEnabled) {
                    $content .= '<span class="badge">Enabled</span>';
                }
                $content .= '</div>';
                $content .= '<p class="theme-card__meta">' . $document->escape($plugin->directoryName) . ' · v' . $document->escape($plugin->version) . ' · ' . $document->escape($plugin->author) . '</p>';

                if ($plugin->description !== '') {
                    $content .= '<p class="muted">' . $document->escape($plugin->description) . '</p>';
                }

                if ($plugin->requiredPlugins !== []) {
                    $content .= '<p class="muted"><strong>Requires:</strong> ' . $document->escape(implode(', ', $plugin->requiredPlugins)) . '</p>';
                }

                if ($plugin->homepageUrl !== null) {
                    $content .= '<p><a href="' . $document->escape($plugin->homepageUrl) . '" target="_blank" rel="noreferrer noopener">Plugin homepage</a></p>';
                }

                $pages = $pluginPages[$plugin->directoryName] ?? [];
                if ($pages !== []) {
                    $content .= '<div class="stack">';
                    foreach ($pages as $page) {
                        $content .= '<a class="card-link" href="/admin/plugin-page?page=' . rawurlencode($page->pageKey) . '"><span class="card-link__title">' . $document->escape($page->title) . '</span><span class="card-link__text">' . $document->escape($page->description !== '' ? $page->description : 'Plugin admin page') . '</span></a>';
                    }
                    $content .= '</div>';
                }

                $content .= '<div class="cluster">';
                $content .= '<form method="post" action="/admin/plugins/toggle">';
                $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($toggleCsrfToken) . '">';
                $content .= '<input type="hidden" name="plugin" value="' . $document->escape($plugin->directoryName) . '">';
                $content .= '<input type="hidden" name="state" value="' . ($plugin->isEnabled ? 'disable' : 'enable') . '">';
                $content .= '<button type="submit">' . ($plugin->isEnabled ? 'Disable' : 'Enable') . '</button>';
                $content .= '</form>';

                if (!$plugin->isEnabled) {
                    $content .= '<form method="post" action="/admin/plugins/delete">';
                    $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($deleteCsrfToken) . '">';
                    $content .= '<input type="hidden" name="plugin" value="' . $document->escape($plugin->directoryName) . '">';
                    $content .= '<button type="submit" class="button button-danger">Delete</button>';
                    $content .= '</form>';
                }
                $content .= '</div>';
                $content .= '</article>';
            }
            $content .= '</div>';
        }

        $content .= '</section></main>';

        return $document->render('Glyph Plugins', $content, 'Install, enable, disable, and remove Glyph plugins.', 'theme-admin');
    }
}
