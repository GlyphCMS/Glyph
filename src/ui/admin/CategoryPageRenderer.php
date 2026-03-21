<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\domain\categories\CategoryRecord;
use Glyph\services\categories\CategoryInput;
use Glyph\ui\shared\DocumentRenderer;

final class CategoryPageRenderer
{
    /**
     * @param list<array{category: CategoryRecord, depth: int, path: string, archive_path: string}> $orderedCategories
     */
    public function renderList(
        array $orderedCategories,
        string $deleteCsrfToken,
        ?string $successMessage,
        ?string $errorMessage,
    ): string {
        $document = new DocumentRenderer();

        $content = '<main class="page-shell stack">';
        $content .= '<section class="hero"><div class="toolbar"><div><p class="hero__eyebrow">Glyph Categories</p><h1 class="hero__title">Manage categories</h1><p class="hero__text">Build one shared category tree for posts and pages, then use those categories to organize content and generate category-prefixed URLs.</p></div><div class="cluster"><a class="button button-secondary" href="/admin">Dashboard</a><a class="button" href="/admin/categories/create">New Category</a></div></div></section>';

        if ($successMessage !== null && $successMessage !== '') {
            $content .= '<div class="notice notice--success"><p><strong>' . $document->escape($successMessage) . '</strong></p></div>';
        }

        if ($errorMessage !== null && $errorMessage !== '') {
            $content .= '<div class="notice notice--error"><p><strong>' . $document->escape($errorMessage) . '</strong></p></div>';
        }

        $content .= '<section class="panel stack">';
        $content .= '<div class="toolbar"><div><p class="kicker">Category Tree</p><h2 class="page-title">All categories</h2><p class="page-subtitle">Top-level categories shape both their own archive URLs and content URLs like <code>/category-slug/article-slug</code>.</p></div><a class="button button-secondary" href="/admin/categories/create">Create Category</a></div>';

        if ($orderedCategories === []) {
            $content .= '<div class="notice notice--warning"><p class="empty-state">No categories yet. Create your first category to start organizing posts and pages.</p></div>';
        } else {
            $content .= '<div class="stack">';
            foreach ($orderedCategories as $row) {
                $category = $row['category'];
                $content .= '<article class="content-directory-card">';
                $content .= '<div class="content-directory-main">';
                $content .= '<div class="content-directory-topline">';
                $content .= '<span class="content-directory-type">Category</span>';
                if ($row['depth'] > 0) {
                    $content .= '<span class="badge">Level ' . $document->escape((string) ($row['depth'] + 1)) . '</span>';
                }
                $content .= '<span class="badge">' . $document->escape($row['path']) . '</span>';
                $content .= '</div>';
                $content .= '<h3 class="content-directory-title">' . $document->escape(str_repeat('-- ', $row['depth']) . $category->name) . '</h3>';
                if ($category->description !== '') {
                    $content .= '<p class="content-directory-excerpt">' . $document->escape($category->description) . '</p>';
                }
                $content .= '<p class="muted">Archive: <code>' . $document->escape($row['archive_path']) . '</code></p>';
                $content .= '</div>';
                $content .= '<div class="content-directory-side">';
                $content .= '<div class="content-directory-actions">';
                $content .= '<a class="button button-secondary" href="/admin/categories/edit?id=' . rawurlencode($category->id) . '">Edit</a>';
                $content .= '<a class="button button-secondary" href="' . $document->escape($row['archive_path']) . '" target="_blank" rel="noreferrer noopener">View Archive</a>';
                $content .= '<form class="inline-form" method="post" action="/admin/categories/delete">';
                $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($deleteCsrfToken) . '">';
                $content .= '<input type="hidden" name="id" value="' . $document->escape($category->id) . '">';
                $content .= '<button class="button-danger" type="submit">Delete</button>';
                $content .= '</form>';
                $content .= '</div></div></article>';
            }
            $content .= '</div>';
        }

        $content .= '<p class="muted">Posts and pages can each be assigned to one category. A category path like <code>/guides/getting-started</code> creates an archive at <code>/guides/getting-started</code> and content URLs like <code>/guides/getting-started/post-slug</code>.</p>';
        $content .= '</section></main>';

        return $document->render('Categories', $content, 'Manage Glyph categories.', 'theme-admin');
    }

    /**
     * @param array<string, string> $parentOptions
     * @param array<string, string> $fieldErrors
     */
    public function renderForm(
        array $parentOptions,
        CategoryInput $input,
        array $fieldErrors,
        ?CategoryRecord $editingCategory,
        string $saveCsrfToken,
        string $deleteCsrfToken,
        ?string $successMessage,
        ?string $errorMessage,
    ): string {
        $document = new DocumentRenderer();
        $isEditing = $editingCategory !== null;
        $heading = $isEditing ? 'Edit Category' : 'Create Category';
        $subtitle = $isEditing
            ? 'Update the category name, slug, hierarchy, and archive description.'
            : 'Create a shared category for posts and pages.';

        $content = '<main class="page-shell stack">';
        $content .= '<section class="hero"><div class="toolbar"><div><p class="hero__eyebrow">Glyph Categories</p><h1 class="hero__title">' . $document->escape($heading) . '</h1><p class="hero__text">' . $document->escape($subtitle) . '</p></div><div class="cluster"><a class="button button-secondary" href="/admin/categories">Back to Categories</a></div></div></section>';

        if ($successMessage !== null && $successMessage !== '') {
            $content .= '<div class="notice notice--success"><p><strong>' . $document->escape($successMessage) . '</strong></p></div>';
        }

        if ($errorMessage !== null && $errorMessage !== '') {
            $content .= '<div class="notice notice--error"><p><strong>' . $document->escape($errorMessage) . '</strong></p></div>';
        }

        $content .= '<section class="panel stack">';
        $content .= '<div><p class="kicker">Details</p><h2 class="page-title">' . $document->escape($heading) . '</h2><p class="page-subtitle">Category slugs become part of public URLs, so keep them short and stable.</p></div>';
        $content .= '<form method="post" action="/admin/categories/save" class="stack">';
        $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($saveCsrfToken) . '">';
        if ($editingCategory !== null) {
            $content .= '<input type="hidden" name="id" value="' . $document->escape($editingCategory->id) . '">';
        }
        $content .= $this->textField('Name', 'name', $input->name, $fieldErrors['name'] ?? null, 'Shown in admin and in category navigation.', $document);
        $content .= $this->textField('Slug', 'slug', $input->slug, $fieldErrors['slug'] ?? null, 'Used in URLs. Example: news, guides, company.', $document);
        $content .= $this->selectField('Parent Category', 'parent_id', $input->parentId ?? '', $parentOptions, $fieldErrors['parent_id'] ?? null, 'Leave empty to create a top-level category.', $document);
        $content .= $this->textareaField('Description', 'description', $input->description, $fieldErrors['description'] ?? null, 'Optional intro text for the category archive page.', $document);
        $content .= '<div class="cluster"><button type="submit" class="button">' . $document->escape($isEditing ? 'Save Category' : 'Create Category') . '</button><a class="button button-secondary" href="/admin/categories">Cancel</a></div>';
        $content .= '</form>';
        $content .= '<p class="muted">A category path like <code>/guides/getting-started</code> creates an archive at <code>/guides/getting-started</code> and content URLs like <code>/guides/getting-started/post-slug</code>.</p>';
        $content .= '</section>';

        if ($editingCategory !== null) {
            $content .= '<section class="panel stack">';
            $content .= '<div><p class="kicker">Danger Zone</p><h2 class="page-title">Delete category</h2><p class="page-subtitle">Delete is only available when the category has no child categories and no assigned content.</p></div>';
            $content .= '<form method="post" action="/admin/categories/delete" class="inline-form">';
            $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($deleteCsrfToken) . '">';
            $content .= '<input type="hidden" name="id" value="' . $document->escape($editingCategory->id) . '">';
            $content .= '<button class="button-danger" type="submit">Delete Category</button>';
            $content .= '</form>';
            $content .= '</section>';
        }

        $content .= '</main>';

        return $document->render($heading, $content, $subtitle, 'theme-admin');
    }

    private function textField(string $label, string $name, string $value, ?string $error, string $help, DocumentRenderer $document): string
    {
        $html = '<div class="field">';
        $html .= '<label for="' . $document->escape($name) . '">' . $document->escape($label) . '</label>';
        $html .= '<input id="' . $document->escape($name) . '" name="' . $document->escape($name) . '" type="text" value="' . $document->escape($value) . '">';
        if ($error !== null && $error !== '') {
            $html .= '<p class="field-error">' . $document->escape($error) . '</p>';
        }
        $html .= '<small class="field-help">' . $document->escape($help) . '</small>';
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string, string> $options
     */
    private function selectField(string $label, string $name, string $current, array $options, ?string $error, string $help, DocumentRenderer $document): string
    {
        $html = '<div class="field">';
        $html .= '<label for="' . $document->escape($name) . '">' . $document->escape($label) . '</label>';
        $html .= '<select id="' . $document->escape($name) . '" name="' . $document->escape($name) . '">';

        foreach ($options as $value => $optionLabel) {
            $selected = $value === $current ? ' selected' : '';
            $html .= '<option value="' . $document->escape($value) . '"' . $selected . '>' . $document->escape($optionLabel) . '</option>';
        }

        $html .= '</select>';
        if ($error !== null && $error !== '') {
            $html .= '<p class="field-error">' . $document->escape($error) . '</p>';
        }
        $html .= '<small class="field-help">' . $document->escape($help) . '</small>';
        $html .= '</div>';

        return $html;
    }

    private function textareaField(string $label, string $name, string $value, ?string $error, string $help, DocumentRenderer $document): string
    {
        $html = '<div class="field">';
        $html .= '<label for="' . $document->escape($name) . '">' . $document->escape($label) . '</label>';
        $html .= '<textarea id="' . $document->escape($name) . '" name="' . $document->escape($name) . '" rows="5">' . $document->escape($value) . '</textarea>';
        if ($error !== null && $error !== '') {
            $html .= '<p class="field-error">' . $document->escape($error) . '</p>';
        }
        $html .= '<small class="field-help">' . $document->escape($help) . '</small>';
        $html .= '</div>';

        return $html;
    }
}