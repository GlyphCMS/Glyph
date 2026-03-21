<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\services\themes\ThemeData;
use Glyph\ui\shared\DocumentRenderer;

final class ThemePageRenderer
{
    /**
     * @param list<ThemeData> $themes
     */
    public function render(
        array $themes,
        string $activeTheme,
        string $uploadCsrfToken,
        string $activateCsrfToken,
        string $deleteCsrfToken,
        ?string $successMessage,
        ?string $errorMessage,
    ): string {
        $document = new DocumentRenderer();

        $content = '<main class="page-shell stack">';
        $content .= '<section class="hero">';
        $content .= '<p class="hero__eyebrow">Glyph Themes</p>';
        $content .= '<div class="toolbar">';
        $content .= '<div><h1 class="hero__title">Manage themes</h1><p class="hero__text">Install, preview, activate, and remove themes while keeping runtime settings upgrade-safe.</p></div>';
        $content .= '<a class="button button-secondary" href="/admin">Back to dashboard</a>';
        $content .= '</div></section>';

        if ($successMessage !== null && $successMessage !== '') {
            $content .= '<div class="notice notice--success"><p><strong>' . $document->escape($successMessage) . '</strong></p></div>';
        }

        if ($errorMessage !== null && $errorMessage !== '') {
            $content .= '<div class="notice notice--error"><p><strong>' . $document->escape($errorMessage) . '</strong></p></div>';
        }

        $content .= '<section class="panel stack">';
        $content .= '<div><p class="kicker">Install</p><h2 class="page-title">Upload a theme package</h2><p class="page-subtitle">Upload a ZIP package that contains a theme.json manifest at the root or inside a single top-level directory.</p></div>';
        $content .= '<form method="post" action="/admin/themes/install" enctype="multipart/form-data" class="form-grid">';
        $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($uploadCsrfToken) . '">';
        $content .= '<div class="field"><label for="theme_package">Theme ZIP</label><input id="theme_package" name="theme_package" type="file" accept=".zip,application/zip"></div>';
        $content .= '<div class="field cluster"><button type="submit">Install Theme</button></div>';
        $content .= '</form>';
        $content .= '</section>';

        $content .= '<section class="panel stack">';
        $content .= '<div><p class="kicker">Installed Themes</p><h2 class="page-title">Available themes</h2></div>';

        if ($themes === []) {
            $content .= '<div class="notice notice--warning"><p class="empty-state">No themes found.</p></div>';
        } else {
            $content .= '<div class="theme-list">';
            foreach ($themes as $theme) {
                $isActive = $theme->directoryName === $activeTheme;

                $content .= '<article class="theme-card">';
                $content .= '<div class="theme-card__media">';
                if ($theme->screenshotUrl !== null) {
                    $content .= '<img src="' . $document->escape($theme->screenshotUrl) . '" alt="' . $document->escape($theme->name) . ' screenshot">';
                } else {
                    $content .= '<div class="theme-card__placeholder">No preview</div>';
                }
                $content .= '</div><div class="theme-card__body">';
                $content .= '<div class="cluster">';
                $content .= '<p class="theme-card__title">' . $document->escape($theme->name) . '</p>';
                if ($isActive) {
                    $content .= '<span class="badge">Active</span>';
                }
                $content .= '</div>';
                $content .= '<p class="theme-card__meta">' . $document->escape($theme->directoryName) . ' &middot; v' . $document->escape($theme->version) . ' &middot; ' . $document->escape($theme->author) . '</p>';
                if ($theme->description !== '') {
                    $content .= '<p class="muted">' . $document->escape($theme->description) . '</p>';
                }

                $content .= '<div class="cluster theme-card__actions">';
                if ($isActive) {
                    $content .= '<span class="button button-secondary theme-card__status" aria-disabled="true">Active Theme</span>';
                } else {
                    $content .= '<form method="post" action="/admin/themes/activate">';
                    $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($activateCsrfToken) . '">';
                    $content .= '<input type="hidden" name="theme" value="' . $document->escape($theme->directoryName) . '">';
                    $content .= '<button type="submit">Set Active Theme</button>';
                    $content .= '</form>';
                }

                if ($theme->directoryName !== 'default' && !$isActive) {
                    $content .= '<form method="post" action="/admin/themes/delete">';
                    $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($deleteCsrfToken) . '">';
                    $content .= '<input type="hidden" name="theme" value="' . $document->escape($theme->directoryName) . '">';
                    $content .= '<button type="submit" class="button button-danger">Delete</button>';
                    $content .= '</form>';
                }
                $content .= '</div>';

                $content .= '</div></article>';
            }
            $content .= '</div>';
        }

        $content .= '</section></main>';

        return $document->render('Glyph Themes', $content, 'Install and manage Glyph themes.', 'theme-admin');
    }
}

