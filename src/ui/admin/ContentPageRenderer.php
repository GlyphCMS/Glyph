<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\domain\content\ContentRecord;
use Glyph\services\content\ContentInput;
use Glyph\services\content\ContentValidationResult;
use Glyph\ui\shared\DateTimeFormatter;
use Glyph\ui\shared\DocumentRenderer;

final class ContentPageRenderer
{
    private readonly DateTimeFormatter $dateTimeFormatter;

    public function __construct(?DateTimeFormatter $dateTimeFormatter = null)
    {
        $this->dateTimeFormatter = $dateTimeFormatter ?? new DateTimeFormatter();
    }
    /**
     * @param list<ContentRecord> $contents
     * @param array<string, string> $filters
     * @param array<string, string> $categoryLabels
     */
    public function renderList(array $contents, string $deleteCsrfToken, array $filters, ?string $successMessage, array $categoryLabels = []): string
    {
        $document = new DocumentRenderer();

        $content = '<main class="page-shell stack">';
        $content .= '<section class="hero"><div class="toolbar toolbar--hero"><h1 class="hero__title">Manage Posts &amp; Pages</h1><div class="cluster hero__actions"><a class="button button-secondary" href="/admin">Dashboard</a><a class="button hero__mobile-action" href="/admin/content/create?type=post">New Post</a><a class="button hero__mobile-action" href="/admin/content/create?type=page">New Page</a></div></div></section>';

        $content .= '<section class="panel stack"><div class="toolbar content-library-header"><h2 class="page-title">All Content</h2><div class="cluster content-library-header__actions"><a class="button" href="/admin/content/create?type=post">New Post</a><a class="button" href="/admin/content/create?type=page">New Page</a></div></div>';

        if ($successMessage !== null && $successMessage !== '') {
            $content .= '<div class="notice notice--success"><p><strong>' . $document->escape($successMessage) . '</strong></p></div>';
        }

        $content .= '<div class="content-filter-bar">';
        $content .= '<div class="content-filter-form content-filter-form--live">';
        $content .= $this->renderFilterField('Search', 'content-library-search', $filters['query'] ?? '', 'Search title, excerpt, or slug', $document, 'content-library-search');
        $content .= $this->renderSelect('Type', 'content_type_filter', $filters['type'] ?? '', ['' => 'All Types', 'post' => 'Post', 'page' => 'Page'], null, $document, 'content-type-filter');
        $content .= $this->renderSelect('Status', 'content_status_filter', $filters['status'] ?? '', ['' => 'All Statuses', 'draft' => 'Draft', 'published' => 'Published'], null, $document, 'content-status-filter');
        $content .= '</div>';
        $content .= '<p id="content-library-filter-status" class="muted content-filter-status">' . $document->escape((string) count($contents)) . ' items</p>';
        $content .= '</div>';

        if ($contents === []) {
            $content .= '<div class="notice notice--warning"><p class="empty-state">No content exists yet. Create a post or page to get started.</p></div></section></main>';
            return $document->render('Manage Content', $content, 'Manage Glyph content.', 'theme-admin');
        }

        $content .= '<div class="content-directory-list">';
        foreach ($contents as $contentRecord) {
            $content .= $this->renderContentDirectoryCard($contentRecord, $deleteCsrfToken, $document, $categoryLabels);
        }
        $content .= '</div>';
        $content .= $this->contentListScript();
        $content .= '</section></main>';

        return $document->render('Manage Content', $content, 'Manage Glyph content.', 'theme-admin');
    }


    private function renderContentDirectoryCard(ContentRecord $contentRecord, string $deleteCsrfToken, DocumentRenderer $document, array $categoryLabels = []): string
    {
        $categoryLabel = $contentRecord->categoryId !== null
            ? (string) ($categoryLabels[$contentRecord->categoryId] ?? '')
            : '';
        $searchText = strtolower(trim(implode(' ', array_filter([
            $contentRecord->title,
            $contentRecord->excerpt,
            $contentRecord->slug,
            $categoryLabel,
        ]))));

        $card = '<article class="content-directory-card" data-content-search="' . $document->escape($searchText) . '" data-content-type="' . $document->escape($contentRecord->type) . '" data-content-status="' . $document->escape($contentRecord->status) . '">';
        $card .= '<div class="content-directory-main">';
        $card .= '<div class="content-directory-topline">';
        $card .= '<span class="content-directory-type">' . $document->escape(ucfirst($contentRecord->type)) . '</span>';
        $card .= '<span class="badge badge--status-' . $document->escape($contentRecord->status) . '">' . $document->escape(ucfirst($contentRecord->status)) . '</span>';
        if ($categoryLabel !== '') {
            $card .= '<span class="badge">Category: ' . $document->escape($categoryLabel) . '</span>';
        }
        if ($contentRecord->type === 'page' && $contentRecord->showInNavigation) {
            $card .= '<span class="content-directory-note">Header Menu</span>';
        }
        $card .= '</div>';
        $card .= '<h3 class="content-directory-title">' . $document->escape($contentRecord->title) . '</h3>';
        if ($contentRecord->excerpt !== '') {
            $card .= '<p class="content-directory-excerpt">' . $document->escape($this->truncate($contentRecord->excerpt, 170)) . '</p>';
        }
        $card .= '</div>';
        $card .= '<div class="content-directory-side">';
        $card .= '<div class="content-directory-actions">';
        $card .= '<a class="button button-secondary" href="/admin/content/edit?type=' . rawurlencode($contentRecord->type) . '&id=' . rawurlencode($contentRecord->id) . '">Edit</a>';
        $card .= '<a class="button button-secondary" href="' . $document->escape($contentRecord->slug) . '" target="_blank" rel="noreferrer noopener">View</a>';
        $card .= '<form class="inline-form" method="post" action="/admin/content/delete"><input type="hidden" name="_csrf_token" value="' . $document->escape($deleteCsrfToken) . '"><input type="hidden" name="type" value="' . $document->escape($contentRecord->type) . '"><input type="hidden" name="id" value="' . $document->escape($contentRecord->id) . '"><button class="button-danger" type="submit">Delete</button></form>';
        $card .= '</div>';
        $card .= '<div class="content-directory-updated content-directory-updated--side"><span class="content-directory-updated__label">Last updated</span><span class="content-directory-updated__value">' . $document->escape($this->dateTimeFormatter->formatDateTime($contentRecord->updatedAt)) . '</span></div>';
        $card .= '</div>';
        $card .= '</article>';

        return $card;
    }
    /**
     * @param array<string, string> $parentOptions
     * @param list<string> $redirectSlugs
     */
    public function renderForm(string $mode, ContentInput $input, ContentValidationResult $validationResult, ?string $successMessage, ?string $errorMessage, string $csrfToken, ?string $contentId, array $parentOptions, array $redirectSlugs, ?string $autosaveSavedAt, string $autosaveCsrfToken, string $autosaveDiscardCsrfToken, string $mediaUploadCsrfToken, bool $sanitizeContentHtml, bool $canBypassHtmlSanitization, bool $canPublishContent, array $categoryOptions = [], array $categoryPaths = []): string
    {
        $document = new DocumentRenderer();
        $isEdit = $mode === 'edit';
        $action = $isEdit ? '/admin/content/edit' : '/admin/content/create';
        $heading = $isEdit ? 'Edit Content' : 'Create Content';
        $subtitle = $isEdit ? 'Update the content record, metadata, slug path, SEO fields, and redirects.' : 'Create a new post or page using the shared content model.';
        $previewTitle = $input->seoTitle !== '' ? $input->seoTitle : ($input->title !== '' ? $input->title : 'Untitled content');
        $previewDescription = $input->seoDescription !== '' ? $input->seoDescription : ($input->excerpt !== '' ? $this->truncate($input->excerpt, 160) : 'Write an excerpt or SEO description to preview how this entry may appear in search and social cards.');
        $previewUrl = $this->previewUrl($input->slug, $input->categoryId, $categoryPaths);
        $previewImage = ($input->seoImage ?? '') !== '' ? (string) $input->seoImage : (string) ($input->featuredImage ?? '');
        $statusOptions = ['draft' => 'Draft'];
        if ($canPublishContent || $input->status === 'published') {
            $statusOptions['published'] = 'Published';
        }
        $publishButtonLabel = !$canPublishContent
            ? ($isEdit && $input->status === 'published' ? 'Save Changes' : 'Save Draft')
            : ($isEdit ? 'Save Changes' : ($input->status === 'published' ? 'Publish' : 'Save Draft'));
        $publishHelpText = $canPublishContent
            ? ''
            : '<p class="sidebar-note">Contributors can save drafts, but an author or higher needs to publish them.</p>';
        $bodyHtmlHelpText = $sanitizeContentHtml
            ? 'Use the compact helper groups to insert common HTML. Body HTML is sanitized on save unless an administrator or owner explicitly bypasses it. Autosave runs while you type.'
            : 'Use the compact helper groups to insert common HTML. Global body HTML sanitization is off, so this content saves exactly as written. Autosave runs while you type.';

        $content = '<main class="page-shell page-shell--editor stack">';
        $content .= '<section class="hero hero--editor"><div class="toolbar"><h1 class="hero__title">' . $document->escape($heading) . '</h1><div class="cluster"><a class="button button-secondary" href="/admin/content">Back to Content</a></div></div></section>';

        if ($autosaveSavedAt !== null && $autosaveSavedAt !== '') {
            $content .= '<div class="notice notice--success"><div class="notice__layout"><div class="notice__content"><p><strong>Autosave restored.</strong> Draft fields were loaded from the latest autosave saved at ' . $document->escape($this->dateTimeFormatter->formatDateTime($autosaveSavedAt)) . '.</p></div>';
            $content .= '<form method="post" action="/admin/content/autosave/discard" class="inline-form notice__action">';
            $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($autosaveDiscardCsrfToken) . '">';
            $content .= '<input type="hidden" name="mode" value="' . $document->escape($mode) . '">';
            $content .= '<input type="hidden" name="type" value="' . $document->escape($input->type) . '">';
            if ($contentId !== null) {
                $content .= '<input type="hidden" name="id" value="' . $document->escape($contentId) . '">';
            }
            $content .= '<button type="submit" class="button button-secondary button--compact">Discard Autosave</button></form></div></div>';
        }

        $content .= '<form id="content-editor-form" method="post" action="' . $document->escape($action) . '" data-can-publish-content="' . ($canPublishContent ? '1' : '0') . '">';
        $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($csrfToken) . '">';
        $content .= '<input type="hidden" name="_autosave_token" value="' . $document->escape($autosaveCsrfToken) . '">';
        $content .= '<input type="hidden" name="mode" value="' . $document->escape($mode) . '">';
        if ($contentId !== null) {
            $content .= '<input type="hidden" name="id" value="' . $document->escape($contentId) . '">';
        }

        $content .= '<div class="editor-layout editor-layout--simple">';
        $content .= '<div class="editor-main stack">';

        if ($successMessage !== null && $successMessage !== '') {
            $content .= '<div class="notice notice--success"><p><strong>' . $document->escape($successMessage) . '</strong></p></div>';
        }
        if ($errorMessage !== null && $errorMessage !== '') {
            $content .= '<div class="notice notice--error"><p><strong>' . $document->escape($errorMessage) . '</strong></p></div>';
        }
        $seoHasErrors = $validationResult->firstError('slug') !== null
            || $validationResult->firstError('seo_title') !== null
            || $validationResult->firstError('seo_description') !== null
            || $validationResult->firstError('seo_image') !== null;
        $seoBody = '<p class="sidebar-note">Slug, search preview, and social preview live here instead of the sidebar.</p>';
        $seoBody .= '<div class="form-grid form-grid--two">';
        $seoBody .= $this->renderField('Slug', 'slug', 'text', $input->slug, $validationResult->firstError('slug'), 'Example: about or keeping-things-simple', $document, 'js-slug-field');
        $seoBody .= $this->renderFieldWithMeta('SEO Title', 'seo_title', 'text', $input->seoTitle, $validationResult->firstError('seo_title'), 'Optional override for the HTML title tag.', 'seo-title-count', 'SEO title: ' . mb_strlen($input->seoTitle) . '/200', $document, 'js-seo-title');
        $seoBody .= '</div>';
        $seoBody .= '<div class="form-grid form-grid--two seo-preview-grid">';
        $seoBody .= $this->renderFieldWithMeta('SEO Description', 'seo_description', 'text', $input->seoDescription, $validationResult->firstError('seo_description'), 'Optional override for the meta description.', 'seo-description-count', 'SEO description: ' . mb_strlen($input->seoDescription) . '/320', $document, 'js-seo-description');
        $seoBody .= $this->renderSeoImageField($input->seoImage ?? '', $validationResult->firstError('seo_image'), $mediaUploadCsrfToken, $document);
        $seoBody .= '</div>';
        $seoBody .= '<div class="search-preview"><p class="search-preview__title" id="search-preview-title">' . $document->escape($previewTitle) . '</p><p class="search-preview__url" id="search-preview-url">' . $document->escape($previewUrl) . '</p><p class="search-preview__description" id="search-preview-description">' . $document->escape($previewDescription) . '</p></div>';
        $seoBody .= '<div class="social-card" id="social-card-preview"><div class="social-card__image' . ($previewImage === '' ? ' social-card__image--empty' : '') . '"><img id="social-card-image" src="' . $document->escape($previewImage) . '" alt="Social preview image"></div><div class="social-card__body"><p class="social-card__host">' . $document->escape($previewUrl) . '</p><p class="social-card__title" id="social-card-title">' . $document->escape($previewTitle) . '</p><p class="social-card__description" id="social-card-description">' . $document->escape($previewDescription) . '</p></div></div>';

        $content .= '<section class="panel stack">';
        $content .= '<div class="form-section-header"><h2 class="form-section-header__title">Writing</h2><p class="form-section-header__subtitle">Keep the editor focused on the title, summary, and body.</p></div>';
        $content .= '<div class="form-grid form-grid--two">';
        $content .= $this->renderField('Title', 'title', 'text', $input->title, $validationResult->firstError('title'), 'The display title shown in lists and on the page itself.', $document, 'js-title-field');
        $content .= $this->renderField('Excerpt', 'excerpt', 'text', $input->excerpt, $validationResult->firstError('excerpt'), 'Used for lists, previews, and SEO fallback.', $document, 'js-excerpt-field');
        $content .= '</div>';
        $content .= $this->renderBodyField($input->bodyHtml, $validationResult->firstError('body_html'), $bodyHtmlHelpText, $document);
        $content .= '</section>';
        $content .= '<details class="sidebar-disclosure editor-section-disclosure"' . ($seoHasErrors ? ' open' : '') . '><summary class="sidebar-disclosure__summary"><span>SEO and Preview</span></summary><div class="sidebar-disclosure__content">' . $seoBody . '</div></details>';
        $content .= '</div>';

        $publishBody = '<div class="form-grid">';
        $publishBody .= $this->renderSelect('Type', 'type', $input->type, ['post' => 'Post', 'page' => 'Page'], $validationResult->firstError('type'), $document);
        $publishBody .= $this->renderSelect('Status', 'status', $input->status, $statusOptions, $validationResult->firstError('status'), $document);
        $publishBody .= $this->renderSelect('Category', 'category_id', $input->categoryId ?? '', $categoryOptions === [] ? ['' => 'No category'] : $categoryOptions, $validationResult->firstError('category_id'), $document);
        $publishBody .= '</div>';
        if ($publishHelpText !== '') {
            $publishBody .= $publishHelpText;
        }
        $publishBody .= '<div class="publish-status-stack"><span class="meta-chip meta-chip--wide">Preview URL <code id="slug-preview">' . $document->escape($previewUrl) . '</code></span><span class="meta-chip meta-chip--wide" id="autosave-status">Autosave idle</span></div>';
        $publishBody .= '<button type="submit" class="button button-secondary button-publish js-publish-button">' . $document->escape($publishButtonLabel) . '</button>';

        $pageSettingsBody = '<p class="sidebar-note">Navigation and hierarchy options only apply when this entry is a page.</p>';
        $pageSettingsBody .= '<div class="form-grid">';
        $pageSettingsBody .= $this->renderSelect('Parent Page', 'parent_id', $input->parentId ?? '', $parentOptions, $validationResult->firstError('parent_id'), $document);
        $pageSettingsBody .= $this->renderField('Navigation Title', 'navigation_title', 'text', $input->navigationTitle, $validationResult->firstError('navigation_title'), 'Optional shorter label for menus.', $document);
        $pageSettingsBody .= $this->renderField('Menu Order', 'menu_order', 'text', $input->menuOrder, $validationResult->firstError('menu_order'), 'Lower values appear first in page navigation.', $document);
        $pageSettingsBody .= '<div class="field"><label class="checkbox-row"><input type="checkbox" name="show_in_navigation" value="1"' . ($input->showInNavigation ? ' checked' : '') . '> Show in automatic navigation</label><small class="field-help">Only affects page-based automatic menus.</small></div>';
        $pageSettingsBody .= '</div>';

        $content .= '<aside class="editor-sidebar editor-sidebar--sticky">';
        $content .= $this->renderSidebarDisclosure('Publish', $publishBody, $document, true);
        $content .= $this->renderSidebarDisclosure('Featured Image', $this->renderFeaturedImageField($input->featuredImage ?? '', $validationResult->firstError('featured_image'), $mediaUploadCsrfToken, $document), $document, true);
        $content .= $this->renderSidebarDisclosure('Page Settings', $pageSettingsBody, $document, false, 'page-settings-section', $input->type !== 'page');

        if ($redirectSlugs !== []) {
            $redirectBody = '<p class="sidebar-note">Old slugs that redirect to this content.</p><ul class="redirect-list">';
            foreach ($redirectSlugs as $redirectSlug) {
                $redirectBody .= '<li><code>' . $document->escape($redirectSlug) . '</code></li>';
            }
            $redirectBody .= '</ul>';
            $content .= $this->renderSidebarDisclosure('Redirect History', $redirectBody, $document);
        }

        $content .= '</aside>';
        $content .= '</div>';
        $content .= '</form>';
        $content .= $this->renderMediaPickerModal($mediaUploadCsrfToken, $document);
        $content .= $this->editorScript($categoryPaths);
        $content .= '</main>';

        return $document->render($heading, $content, $subtitle, 'theme-admin');
    }

    /**
     * @param array<string, string> $categoryPaths
     */
    private function previewUrl(string $slug, ?string $categoryId, array $categoryPaths): string
    {
        $trimmedSlug = trim($slug, '/');

        if ($trimmedSlug === '') {
            return '/';
        }

        $prefix = trim((string) ($categoryPaths[$categoryId ?? ''] ?? ''), '/');

        return $prefix !== ''
            ? '/' . $prefix . '/' . $trimmedSlug
            : '/' . $trimmedSlug;
    }

    private function renderContentFilterForm(array $filters, DocumentRenderer $document, string $idPrefix): string
    {
        $form = '<form method="get" action="/admin/content" class="content-filter-form">';
        $form .= $this->renderFilterField('Search', 'q', $filters['query'] ?? '', 'Search title or slug', $document, $idPrefix . '-q');
        $form .= $this->renderSelect('Type', 'type', $filters['type'] ?? '', ['' => 'All Types', 'post' => 'Post', 'page' => 'Page'], null, $document, $idPrefix . '-type');
        $form .= $this->renderSelect('Status', 'status', $filters['status'] ?? '', ['' => 'All Statuses', 'draft' => 'Draft', 'published' => 'Published'], null, $document, $idPrefix . '-status');
        $form .= '<div class="content-filter-form__actions"><button type="submit">Apply</button><a class="button button-secondary" href="/admin/content">Reset</a></div>';

        return $form . '</form>';
    }

    private function renderFilterField(string $label, string $name, string $value, string $placeholder, DocumentRenderer $document, ?string $id = null): string
    {
        $fieldId = $id ?? $name;

        return '<div class="field"><label for="' . $document->escape($fieldId) . '">' . $document->escape($label) . '</label><input id="' . $document->escape($fieldId) . '" name="' . $document->escape($name) . '" type="text" placeholder="' . $document->escape($placeholder) . '" value="' . $document->escape($value) . '"></div>';
    }

    private function contentListScript(): string
    {
        return <<<'SCRIPT'
<script>
(() => {
    const searchInput = document.getElementById("content-library-search");
    const typeFilter = document.getElementById("content-type-filter");
    const statusFilter = document.getElementById("content-status-filter");
    const status = document.getElementById("content-library-filter-status");
    const cards = Array.from(document.querySelectorAll(".content-directory-card"));

    const syncFilters = () => {
        const query = searchInput instanceof HTMLInputElement ? searchInput.value.trim().toLowerCase() : "";
        const typeValue = typeFilter instanceof HTMLSelectElement ? typeFilter.value : "";
        const statusValue = statusFilter instanceof HTMLSelectElement ? statusFilter.value : "";
        let visibleCount = 0;

        cards.forEach((card) => {
            if (!(card instanceof HTMLElement)) {
                return;
            }

            const searchValue = card.dataset.contentSearch || "";
            const cardType = card.dataset.contentType || "";
            const cardStatus = card.dataset.contentStatus || "";
            const matchesQuery = query === "" || searchValue.includes(query);
            const matchesType = typeValue === "" || cardType === typeValue;
            const matchesStatus = statusValue === "" || cardStatus === statusValue;
            const isVisible = matchesQuery && matchesType && matchesStatus;
            card.hidden = !isVisible;
            if (isVisible) {
                visibleCount++;
            }
        });

        if (status instanceof HTMLElement) {
            const hasActiveFilters = query !== "" || typeValue !== "" || statusValue !== "";
            status.textContent = hasActiveFilters
                ? `${visibleCount} of ${cards.length} items`
                : `${cards.length} items`;
        }
    };

    [searchInput, typeFilter, statusFilter].forEach((element) => {
        if (element) {
            element.addEventListener("input", syncFilters);
            element.addEventListener("change", syncFilters);
        }
    });

    syncFilters();
})();
</script>
SCRIPT;
    }
    private function renderSidebarDisclosure(string $title, string $body, DocumentRenderer $document, bool $open = false, ?string $id = null, bool $hidden = false): string
    {
        $attributes = ' class="sidebar-disclosure"';

        if ($id !== null && $id !== '') {
            $attributes .= ' id="' . $document->escape($id) . '"';
        }

        if ($open) {
            $attributes .= ' open';
        }

        if ($hidden) {
            $attributes .= ' hidden';
        }

        return '<details' . $attributes . '><summary class="sidebar-disclosure__summary"><span>' . $document->escape($title) . '</span></summary><div class="sidebar-disclosure__content">' . $body . '</div></details>';
    }

    private function renderField(string $label, string $name, string $type, string $value, ?string $error, string $helpText, DocumentRenderer $document, string $inputClass = ''): string
    {
        $field = '<div class="field"><label for="' . $document->escape($name) . '">' . $document->escape($label) . '</label><input class="' . $document->escape($inputClass) . '" id="' . $document->escape($name) . '" name="' . $document->escape($name) . '" type="' . $document->escape($type) . '" value="' . $document->escape($value) . '"><small class="field-help">' . $document->escape($helpText) . '</small>';
        if ($error !== null && $error !== '') {
            $field .= '<small class="field-error">' . $document->escape($error) . '</small>';
        }
        return $field . '</div>';
    }

    /**
     * @param array<string, string> $options
     */
    private function renderFieldWithMeta(string $label, string $name, string $type, string $value, ?string $error, string $helpText, string $metaId, string $metaText, DocumentRenderer $document, string $inputClass = ''): string
    {
        $field = '<div class="field"><label for="' . $document->escape($name) . '">' . $document->escape($label) . '</label><input class="' . $document->escape($inputClass) . '" id="' . $document->escape($name) . '" name="' . $document->escape($name) . '" type="' . $document->escape($type) . '" value="' . $document->escape($value) . '"><div class="field-help-row"><small class="field-help">' . $document->escape($helpText) . '</small><span class="meta-chip" id="' . $document->escape($metaId) . '">' . $document->escape($metaText) . '</span></div>';
        if ($error !== null && $error !== '') {
            $field .= '<small class="field-error">' . $document->escape($error) . '</small>';
        }
        return $field . '</div>';
    }

    private function renderTextareaWithMeta(string $label, string $name, string $value, ?string $error, string $helpText, string $metaId, string $metaText, DocumentRenderer $document, bool $fullWidth = false, string $textareaClass = ''): string
    {
        $field = '<div class="field' . ($fullWidth ? ' field--full' : '') . '"><label for="' . $document->escape($name) . '">' . $document->escape($label) . '</label><textarea class="' . $document->escape($textareaClass) . '" id="' . $document->escape($name) . '" name="' . $document->escape($name) . '">' . $document->escape($value) . '</textarea><div class="field-help-row"><small class="field-help">' . $document->escape($helpText) . '</small><span class="meta-chip" id="' . $document->escape($metaId) . '">' . $document->escape($metaText) . '</span></div>';
        if ($error !== null && $error !== '') {
            $field .= '<small class="field-error">' . $document->escape($error) . '</small>';
        }
        return $field . '</div>';
    }

    private function renderSelect(string $label, string $name, string $currentValue, array $options, ?string $error, DocumentRenderer $document, ?string $id = null): string
    {
        $fieldId = $id ?? $name;
        $field = '<div class="field"><label for="' . $document->escape($fieldId) . '">' . $document->escape($label) . '</label><select id="' . $document->escape($fieldId) . '" name="' . $document->escape($name) . '">';
        foreach ($options as $value => $optionLabel) {
            $selected = $value === $currentValue ? ' selected' : '';
            $field .= '<option value="' . $document->escape($value) . '"' . $selected . '>' . $document->escape($optionLabel) . '</option>';
        }
        $field .= '</select>';
        if ($error !== null && $error !== '') {
            $field .= '<small class="field-error">' . $document->escape($error) . '</small>';
        }
        return $field . '</div>';
    }

    private function renderTextarea(string $label, string $name, string $value, ?string $error, string $helpText, DocumentRenderer $document, bool $fullWidth = false, string $textareaClass = ''): string
    {
        $field = '<div class="field' . ($fullWidth ? ' field--full' : '') . '"><label for="' . $document->escape($name) . '">' . $document->escape($label) . '</label><textarea class="' . $document->escape($textareaClass) . '" id="' . $document->escape($name) . '" name="' . $document->escape($name) . '">' . $document->escape($value) . '</textarea><small class="field-help">' . $document->escape($helpText) . '</small>';
        if ($error !== null && $error !== '') {
            $field .= '<small class="field-error">' . $document->escape($error) . '</small>';
        }
        return $field . '</div>';
    }

    private function renderFeaturedImageField(string $value, ?string $error, string $uploadCsrfToken, DocumentRenderer $document): string
    {
        return $this->renderImageField(
            value: $value,
            error: $error,
            uploadCsrfToken: $uploadCsrfToken,
            document: $document,
            fieldName: 'featured_image',
            fieldLabel: 'Image Path',
            inputClass: 'js-featured-image-field',
            pickerMode: 'featured',
            previewShellId: 'featured-image-preview-shell',
            previewId: 'featured-image-preview',
            uploadTokenId: 'featured-image-upload-token',
            uploadInputId: 'featured-image-upload-input',
            uploadStatusId: 'featured-image-upload-status',
            emptyTitle: 'Upload Featured Image',
            emptyText: 'Choose a file and it will upload right away.',
            uploadStatusText: 'Choose a JPEG, PNG, GIF, or WebP image.',
            previewAlt: 'Featured image preview',
        );
    }

    private function renderSeoImageField(string $value, ?string $error, string $uploadCsrfToken, DocumentRenderer $document): string
    {
        $field = '<div class="field image-field seo-image-field"><label for="seo_image">SEO Image Path</label><div class="editor-action-row"><input id="seo_image" name="seo_image" type="text" value="' . $document->escape($value) . '" class="js-seo-image-field" placeholder="/uploads/images/..." autocomplete="off"><button type="button" class="button button-secondary js-open-media-picker" data-target="seo_image" data-mode="seo">Library</button></div>';
        $field .= '<div class="seo-image-field__upload"><input id="seo-image-upload-token" type="hidden" value="' . $document->escape($uploadCsrfToken) . '"><input id="seo-image-upload-input" type="file" accept="image/jpeg,image/png,image/gif,image/webp"></div>';
        $field .= '<div id="seo-image-upload-status" class="featured-image-upload-status muted" data-tone="neutral" aria-live="polite"></div>';
        $field .= '<div class="featured-image-preview' . ($value === '' ? ' featured-image-preview--empty' : '') . '" id="seo-image-preview-shell"><img id="seo-image-preview" src="' . $document->escape($value) . '" alt="SEO image preview"></div>';
        if ($error !== null && $error !== '') {
            $field .= '<small class="field-error">' . $document->escape($error) . '</small>';
        }
        return $field . '</div>';
    }

    private function renderImageField(
        string $value,
        ?string $error,
        string $uploadCsrfToken,
        DocumentRenderer $document,
        string $fieldName,
        string $fieldLabel,
        string $inputClass,
        string $pickerMode,
        string $previewShellId,
        string $previewId,
        string $uploadTokenId,
        string $uploadInputId,
        string $uploadStatusId,
        string $emptyTitle,
        string $emptyText,
        string $uploadStatusText,
        string $previewAlt,
        string $fieldClass = ''
    ): string {
        $fieldClassName = trim('field image-field ' . $fieldClass);
        $field = '<div class="' . $document->escape($fieldClassName) . '"><label for="' . $document->escape($fieldName) . '">' . $document->escape($fieldLabel) . '</label><div class="editor-action-row"><input id="' . $document->escape($fieldName) . '" name="' . $document->escape($fieldName) . '" type="text" value="' . $document->escape($value) . '" class="' . $document->escape($inputClass) . '" placeholder="/uploads/images/..." autocomplete="off"><button type="button" class="button button-secondary js-open-media-picker" data-target="' . $document->escape($fieldName) . '" data-mode="' . $document->escape($pickerMode) . '">Library</button></div>';
        $field .= '<div class="featured-image-preview' . ($value === '' ? ' featured-image-preview--empty' : '') . '" id="' . $document->escape($previewShellId) . '">';
        $field .= '<div class="featured-image-empty-state"><p class="featured-image-empty-state__title">' . $document->escape($emptyTitle) . '</p><p class="featured-image-empty-state__text">' . $document->escape($emptyText) . '</p><div class="featured-image-upload-form"><input id="' . $document->escape($uploadTokenId) . '" type="hidden" value="' . $document->escape($uploadCsrfToken) . '"><input id="' . $document->escape($uploadInputId) . '" type="file" accept="image/jpeg,image/png,image/gif,image/webp"></div><div id="' . $document->escape($uploadStatusId) . '" class="featured-image-upload-status muted" data-tone="neutral" aria-live="polite">' . $document->escape($uploadStatusText) . '</div></div>';
        $field .= '<img id="' . $document->escape($previewId) . '" src="' . $document->escape($value) . '" alt="' . $document->escape($previewAlt) . '">';
        $field .= '</div>';
        if ($error !== null && $error !== '') {
            $field .= '<small class="field-error">' . $document->escape($error) . '</small>';
        }
        return $field . '</div>';
    }

    private function renderEditorToolButton(string $action, string $label, string $title, DocumentRenderer $document): string
    {
        return '<button type="button" class="button button-secondary editor-tool-button js-editor-action" data-action="' . $document->escape($action) . '" title="' . $document->escape($title) . '" aria-label="' . $document->escape($title) . '">' . $document->escape($label) . '</button>';
    }

    private function renderBodyField(string $value, ?string $error, string $helpText, DocumentRenderer $document): string
    {
        $field = '<div class="field field--full">';
        $field .= '<div class="editor-toolbar">';
        $field .= '<div class="editor-toolbar__groups">';
        $field .= '<div class="editor-tool-group" role="group" aria-label="Inline formatting">';
        $field .= $this->renderEditorToolButton('strong', 'B', 'Bold', $document);
        $field .= $this->renderEditorToolButton('em', 'I', 'Italic', $document);
        $field .= $this->renderEditorToolButton('link', 'Link', 'Insert link', $document);
        $field .= '</div>';
        $field .= '<div class="editor-tool-group" role="group" aria-label="Headings and structure">';
        $field .= $this->renderEditorToolButton('h2', 'H2', 'Insert heading level 2', $document);
        $field .= $this->renderEditorToolButton('h3', 'H3', 'Insert heading level 3', $document);
        $field .= $this->renderEditorToolButton('paragraph', 'P', 'Insert paragraph', $document);
        $field .= $this->renderEditorToolButton('ul', 'List', 'Insert unordered list', $document);
        $field .= '</div>';
        $field .= '<div class="editor-tool-group" role="group" aria-label="Blocks and media">';
        $field .= $this->renderEditorToolButton('blockquote', 'Quote', 'Insert blockquote', $document);
        $field .= $this->renderEditorToolButton('code', 'Code', 'Insert code block', $document);
        $field .= $this->renderEditorToolButton('hr', 'Rule', 'Insert divider', $document);
        $field .= '<button type="button" class="button button-secondary editor-tool-button js-open-media-picker" data-target="body_html" data-mode="inline" title="Insert image" aria-label="Insert image">Image</button>';
        $field .= '</div>';
        $field .= '</div>';
        $field .= '</div>';
        $field .= '<label for="body_html">Body</label><textarea class="js-body-field editor-body" id="body_html" name="body_html" spellcheck="true">' . $document->escape($value) . '</textarea><small class="field-help">' . $document->escape($helpText) . '</small>';
        if ($error !== null && $error !== '') {
            $field .= '<small class="field-error">' . $document->escape($error) . '</small>';
        }
        return $field . '</div>';
    }

    private function truncate(string $value, int $length): string
    {
        if (mb_strlen($value) <= $length) {
            return $value;
        }
        return mb_substr($value, 0, max(0, $length - 3)) . '...';
    }

    private function renderMediaPickerModal(string $uploadCsrfToken, DocumentRenderer $document): string
    {
        $content = '<div id="media-picker-modal" class="media-picker-modal" hidden>';
        $content .= '<div class="media-picker-backdrop js-close-media-picker"></div>';
        $content .= '<div class="media-picker-panel">';
        $content .= '<div class="toolbar"><div><p class="kicker">Media Picker</p><h2 class="page-title">Choose an image</h2></div><button type="button" class="button button-secondary js-close-media-picker">Close</button></div>';
        $content .= '<section class="media-picker-upload stack">';
        $content .= '<div><p class="kicker">Upload</p><h3 class="page-title">Add a new image</h3><p class="page-subtitle">Upload a JPEG, PNG, GIF, or WebP file without leaving the editor.</p></div>';
        $content .= '<form id="media-picker-upload-form" class="form-grid" enctype="multipart/form-data">';
        $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($uploadCsrfToken) . '">';
        $content .= '<div class="field"><label for="media-picker-file">Image File</label><input id="media-picker-file" type="file" name="media_file" accept="image/jpeg,image/png,image/gif,image/webp" required><small class="field-help">Uploaded images are stored in the shared media library.</small></div>';
        $content .= '<div class="cluster"><button id="media-picker-upload-submit" type="submit">Upload and Use Image</button></div>';
        $content .= '<div id="media-picker-upload-status" class="media-picker-upload-status muted" data-tone="neutral" aria-live="polite">Upload a JPEG, PNG, GIF, or WebP image here.</div>';
        $content .= '</form>';
        $content .= '</section>';
        $content .= '<div class="field"><label for="media-picker-search">Search Library</label><input id="media-picker-search" type="text" placeholder="Search images by filename"></div>';
        $content .= '<div id="media-picker-status" class="muted" aria-live="polite">Loading media...</div>';
        $content .= '<div id="media-picker-grid" class="media-grid media-grid--picker"></div>';
        $content .= '</div></div>';

        return $content;
    }

    /**
     * @param array<string, string> $categoryPaths
     */
    private function editorScript(array $categoryPaths): string
    {
        $categoryPathsJson = json_encode($categoryPaths, JSON_UNESCAPED_SLASHES);

        if (!is_string($categoryPathsJson)) {
            throw new \RuntimeException('Failed to encode category paths.');
        }

        return str_replace('__CATEGORY_PATHS__', $categoryPathsJson, <<<'SCRIPT'
<script>
(() => {
    const form = document.getElementById("content-editor-form");
    if (!form) return;

    const titleField = form.querySelector(".js-title-field");
    const slugField = form.querySelector(".js-slug-field");
    const excerptField = form.querySelector(".js-excerpt-field");
    const featuredImageField = form.querySelector(".js-featured-image-field");
    const seoImageField = form.querySelector(".js-seo-image-field");
    const contentTypeField = form.querySelector("[name=\"type\"]");
    const statusField = form.querySelector("[name=\"status\"]");
    const formModeField = form.querySelector("[name=\"mode\"]");
    const canPublishContent = form.dataset.canPublishContent === "1";
    const slugPreview = document.getElementById("slug-preview");
    const autosaveStatus = document.getElementById("autosave-status");
    const publishButton = form.querySelector(".js-publish-button");
    const seoTitle = form.querySelector(".js-seo-title");
    const seoDescription = form.querySelector(".js-seo-description");
    const seoTitleCount = document.getElementById("seo-title-count");
    const seoDescriptionCount = document.getElementById("seo-description-count");
    const searchPreviewTitle = document.getElementById("search-preview-title");
    const searchPreviewUrl = document.getElementById("search-preview-url");
    const searchPreviewDescription = document.getElementById("search-preview-description");
    const socialCardTitle = document.getElementById("social-card-title");
    const socialCardDescription = document.getElementById("social-card-description");
    const socialCardHost = document.querySelector(".social-card__host");
    const socialCardImage = document.getElementById("social-card-image");
    const socialCardImageShell = socialCardImage ? socialCardImage.closest('.social-card__image') : null;
    const featuredImagePreview = document.getElementById("featured-image-preview");
    const featuredImagePreviewShell = document.getElementById("featured-image-preview-shell") || (featuredImagePreview ? featuredImagePreview.parentElement : null);
    const seoImagePreview = document.getElementById("seo-image-preview");
    const seoImagePreviewShell = document.getElementById("seo-image-preview-shell") || (seoImagePreview ? seoImagePreview.parentElement : null);
    const pageSettingsSection = document.getElementById("page-settings-section");
    const modal = document.getElementById("media-picker-modal");
    const modalGrid = document.getElementById("media-picker-grid");
    const modalStatus = document.getElementById("media-picker-status");
    const modalSearch = document.getElementById("media-picker-search");
    const modalUploadForm = document.getElementById("media-picker-upload-form");
    const modalUploadInput = document.getElementById("media-picker-file");
    const modalUploadSubmit = document.getElementById("media-picker-upload-submit");
    const modalUploadStatus = document.getElementById("media-picker-upload-status");
    const bodyField = document.getElementById("body_html");
    const categoryField = form.querySelector("[name=\"category_id\"]");
    const categoryPaths = __CATEGORY_PATHS__;

    let pickerItems = [];
    let pickerTarget = null;
    let pickerMode = "featured";
    let autosaveTimer = null;
    let isSubmitting = false;
    let lastAutosavedSerialized = "";
    let formBaseline = new URLSearchParams(new FormData(form)).toString();

    const managedImageFields = [
        {
            mode: 'featured',
            field: featuredImageField,
            preview: featuredImagePreview,
            previewShell: featuredImagePreviewShell,
            uploadInput: document.getElementById('featured-image-upload-input'),
            uploadToken: document.getElementById('featured-image-upload-token'),
            uploadStatus: document.getElementById('featured-image-upload-status'),
            uploadingMessage: 'Uploading featured image...',
            successMessage: 'Featured image uploaded.',
            idleMessage: 'Choose a JPEG, PNG, GIF, or WebP image.',
        },
        {
            mode: 'seo',
            field: seoImageField,
            preview: seoImagePreview,
            previewShell: seoImagePreviewShell,
            uploadInput: document.getElementById('seo-image-upload-input'),
            uploadToken: document.getElementById('seo-image-upload-token'),
            uploadStatus: document.getElementById('seo-image-upload-status'),
            uploadingMessage: 'Uploading SEO image...',
            successMessage: 'SEO image uploaded.',
            idleMessage: 'Choose a JPEG, PNG, GIF, or WebP image.',
        },
    ];

    const normalizeSlugSegment = (value) => {
        return String(value || "").toLowerCase()
            .replace(/[^a-z0-9_-]+/g, "-")
            .replace(/-+/g, "-")
            .replace(/^[-/]+|[-/]+$/g, "");
    };

    const currentCategoryPrefix = () => {
        if (!(categoryField instanceof HTMLSelectElement)) return "";
        return String(categoryPaths[categoryField.value] || "").replace(/^\/+|\/+$/g, "");
    };

    const buildPreviewPath = (slugValue) => {
        const normalizedSlug = normalizeSlugSegment(slugValue);
        if (normalizedSlug === "") return "/";

        const prefix = currentCategoryPrefix();
        return prefix !== "" ? `/${prefix}/${normalizedSlug}` : `/${normalizedSlug}`;
    };

    const isBlankSlugValue = (value) => {
        return normalizeSlugSegment(value) === "";
    };

    const syncSlugField = () => {
        if (!slugField) return;
        const rawValue = slugField.value.trim();
        if (isBlankSlugValue(rawValue)) {
            slugField.value = "";
            return;
        }
        const normalized = normalizeSlugSegment(rawValue);
        if (slugField.value !== normalized) slugField.value = normalized;
    };

    const generatedSlugFromTitle = () => {
        if (!titleField || titleField.value.trim() === "") return "";
        return normalizeSlugSegment(titleField.value);
    };

    const slugMatchesGeneratedTitle = () => {
        if (!slugField) return false;
        const currentValue = slugField.value.trim();
        const generatedValue = generatedSlugFromTitle();
        if (isBlankSlugValue(currentValue)) return true;
        return generatedValue !== "" && normalizeSlug(currentValue) === generatedValue;
    };

    const syncSlugFromTitle = () => {
        if (!titleField || !slugField) return;
        if (slugField.dataset.userEdited === "true" && !slugMatchesGeneratedTitle()) return;

        const generatedValue = generatedSlugFromTitle();
        if (generatedValue === "") {
            if (isBlankSlugValue(slugField.value)) {
                slugField.value = "";
            }
            slugField.dataset.userEdited = "false";
            return;
        }

        slugField.value = generatedValue;
        slugField.dataset.userEdited = "false";
    };
    const currentFormState = () => new URLSearchParams(new FormData(form)).toString();

    const uploadButtonLabel = () => {
        if (pickerMode === "featured") return "Upload and Use as Featured Image";
        if (pickerMode === "seo") return "Upload and Use as SEO Image";
        return "Upload and Insert Image";
    };

    const chooseButtonLabel = () => {
        if (pickerMode === "featured") return "Use as Featured Image";
        if (pickerMode === "seo") return "Use as SEO Image";
        return "Insert Image";
    };

    const setUploadStatus = (message, tone = "neutral") => {
        if (!modalUploadStatus) return;
        modalUploadStatus.textContent = message;
        modalUploadStatus.dataset.tone = tone;
    };

    const syncUploadButton = () => {
        if (!modalUploadSubmit) return;
        modalUploadSubmit.textContent = uploadButtonLabel();
    };
    const updatePageSettingsVisibility = () => {
        if (!contentTypeField || !pageSettingsSection) return;
        const isPage = contentTypeField.value === "page";
        const wasHidden = pageSettingsSection.hidden;
        pageSettingsSection.hidden = !isPage;
        if (isPage && wasHidden) {
            pageSettingsSection.open = true;
        }
    };

    const updateUnsavedState = () => {
        const isDirty = currentFormState() !== formBaseline;
        if (publishButton) {
            publishButton.classList.toggle("is-active", isDirty);
        }
        return isDirty;
    };

    const syncPublishButtonLabel = () => {
        if (!publishButton) return;
        if (!canPublishContent) {
            if (formModeField && formModeField.value === 'edit' && statusField && statusField.value === 'published') {
                publishButton.textContent = 'Save Changes';
                return;
            }
            publishButton.textContent = 'Save Draft';
            return;
        }
        if (formModeField && formModeField.value === 'edit') {
            publishButton.textContent = 'Save Changes';
            return;
        }
        publishButton.textContent = statusField && statusField.value === 'published' ? 'Publish' : 'Save Draft';
    };

    const updateSlugPreview = () => {
        if (slugField && slugPreview) {
            const value = slugField.value.trim() ? buildPreviewPath(slugField.value) : "/";
            slugPreview.textContent = value;
            if (searchPreviewUrl) searchPreviewUrl.textContent = value;
            if (socialCardHost) socialCardHost.textContent = value;
        }
    };

    const currentPreviewTitle = () => {
        if (seoTitle && seoTitle.value.trim() !== "") return seoTitle.value.trim();
        if (titleField && titleField.value.trim() !== "") return titleField.value.trim();
        return "Untitled content";
    };

    const currentPreviewDescription = () => {
        if (seoDescription && seoDescription.value.trim() !== "") return seoDescription.value.trim();
        if (excerptField && excerptField.value.trim() !== "") return excerptField.value.trim();
        return "Write an excerpt or SEO description to preview how this entry may appear in search and social cards.";
    };

    const currentPreviewImage = () => {
        const seoValue = seoImageField && seoImageField.value.trim() !== "" ? seoImageField.value.trim() : "";
        if (seoValue !== "") return seoValue;
        return featuredImageField && featuredImageField.value.trim() !== "" ? featuredImageField.value.trim() : "";
    };

    const updateSeoPreview = () => {
        const title = currentPreviewTitle();
        const description = currentPreviewDescription();

        if (seoTitle && seoTitleCount) seoTitleCount.textContent = `SEO title: ${seoTitle.value.length}/200`;
        if (seoDescription && seoDescriptionCount) seoDescriptionCount.textContent = `SEO description: ${seoDescription.value.length}/320`;
        if (searchPreviewTitle) searchPreviewTitle.textContent = title;
        if (searchPreviewDescription) searchPreviewDescription.textContent = description;
        if (socialCardTitle) socialCardTitle.textContent = title;
        if (socialCardDescription) socialCardDescription.textContent = description;
    };

    const syncPreviewImageShell = (managedField) => {
        if (!managedField || !managedField.field) return;
        const value = managedField.field.value.trim();
        if (managedField.preview) managedField.preview.src = value;
        if (managedField.previewShell) managedField.previewShell.classList.toggle('featured-image-preview--empty', value === '');
    };

    const updateSocialCardImage = () => {
        const value = currentPreviewImage();
        if (socialCardImage) socialCardImage.src = value;
        if (socialCardImageShell) socialCardImageShell.classList.toggle('social-card__image--empty', value === '');
    };

    const updateImagePreviews = () => {
        managedImageFields.forEach((managedField) => syncPreviewImageShell(managedField));
        updateSocialCardImage();
    };

    const setManagedUploadStatus = (managedField, message, tone = "neutral") => {
        if (!managedField || !managedField.uploadStatus) return;
        managedField.uploadStatus.textContent = message;
        managedField.uploadStatus.dataset.tone = tone;
    };

    const uploadManagedImage = async (managedField, event) => {
        event.preventDefault();
        if (!managedField || !managedField.uploadInput || !managedField.uploadToken || !managedField.field) return;

        const files = managedField.uploadInput.files;
        if (!files || files.length === 0) {
            setManagedUploadStatus(managedField, "Choose an image to upload.", "error");
            managedField.uploadInput.focus();
            return;
        }

        managedField.uploadInput.disabled = true;
        setManagedUploadStatus(managedField, managedField.uploadingMessage, "neutral");

        try {
            const body = new FormData();
            body.append('_csrf_token', managedField.uploadToken.value || '');
            body.append('media_file', files[0]);

            const response = await fetch('/admin/media/upload/browser', {
                method: 'POST',
                body,
            });
            const payload = await response.json();
            if (!response.ok || !payload.ok || !payload.item) {
                throw new Error(payload.message || 'The upload failed.');
            }

            pickerItems = [payload.item, ...pickerItems.filter((item) => item.id !== payload.item.id)];
            managedField.field.value = payload.item.public_path;
            managedField.field.dispatchEvent(new Event('input', { bubbles: true }));
            managedField.uploadInput.value = '';
            setManagedUploadStatus(managedField, managedField.successMessage, "success");
        } catch (error) {
            const message = error instanceof Error ? error.message : 'The upload failed.';
            setManagedUploadStatus(managedField, message, "error");
        } finally {
            managedField.uploadInput.disabled = false;
        }
    };

    const insertAtCursor = (field, text) => {
        const start = field.selectionStart || 0;
        const end = field.selectionEnd || 0;
        const value = field.value;
        field.value = value.slice(0, start) + text + value.slice(end);
        const nextPosition = start + text.length;
        field.selectionStart = nextPosition;
        field.selectionEnd = nextPosition;
        field.dispatchEvent(new Event("input", { bubbles: true }));
        field.focus();
    };

    const wrapSelection = (field, before, after = '') => {
        const start = field.selectionStart || 0;
        const end = field.selectionEnd || 0;
        const value = field.value;
        const selected = value.slice(start, end) || 'Text';
        field.value = value.slice(0, start) + before + selected + after + value.slice(end);
        field.selectionStart = start + before.length;
        field.selectionEnd = start + before.length + selected.length;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.focus();
    };

    const insertBlock = (field, content) => {
        const prefix = field.value !== '' && !field.value.endsWith("\n") ? "\n" : "";
        insertAtCursor(field, prefix + content);
    };

    const runEditorAction = (action) => {
        if (!bodyField) return;
        switch (action) {
            case 'strong':
                wrapSelection(bodyField, '<strong>', '</strong>');
                break;
            case 'em':
                wrapSelection(bodyField, '<em>', '</em>');
                break;
            case 'link': {
                const href = window.prompt('Link URL', 'https://');
                if (!href) return;
                wrapSelection(bodyField, `<a href="${href}">`, '</a>');
                break;
            }
            case 'h2':
                insertBlock(bodyField, '<h2>Section Heading</h2>\n');
                break;
            case 'h3':
                insertBlock(bodyField, '<h3>Subheading</h3>\n');
                break;
            case 'paragraph':
                insertBlock(bodyField, '<p>Paragraph text.</p>\n');
                break;
            case 'ul':
                insertBlock(bodyField, '<ul>\n    <li>List item</li>\n    <li>List item</li>\n</ul>\n');
                break;
            case 'blockquote':
                insertBlock(bodyField, '<blockquote><p>Quoted text.</p></blockquote>\n');
                break;
            case 'code':
                insertBlock(bodyField, '<pre><code>Code snippet</code></pre>\n');
                break;
            case 'hr':
                insertBlock(bodyField, '<hr>\n');
                break;
        }
    };

    const applyPickerSelection = (item) => {
        if (!item || typeof item.public_path !== 'string') return;

        if ((pickerMode === 'featured' || pickerMode === 'seo') && pickerTarget) {
            pickerTarget.value = item.public_path;
            pickerTarget.dispatchEvent(new Event('input', { bubbles: true }));
        } else if (pickerMode === 'inline' && bodyField) {
            insertAtCursor(bodyField, `<img src="${item.public_path}" alt="">`);
        }

        closePicker();
    };

    const createInfoText = (tagName, className, text) => {
        const element = document.createElement(tagName);
        element.className = className;
        element.textContent = text;
        return element;
    };

    const renderPickerGrid = () => {
        if (!modalGrid) return;
        const query = String(modalSearch?.value || '').trim().toLowerCase();
        const filtered = pickerItems.filter((item) => {
            const originalName = String(item.original_name || '').toLowerCase();
            const publicPath = String(item.public_path || '').toLowerCase();
            return originalName.includes(query) || publicPath.includes(query);
        });

        modalGrid.innerHTML = '';

        if (filtered.length === 0) {
            if (modalStatus) modalStatus.textContent = 'No media matched the current search.';
            return;
        }

        if (modalStatus) {
            modalStatus.textContent = `${filtered.length} image${filtered.length === 1 ? '' : 's'} available`;
        }

        for (const item of filtered) {
            const card = document.createElement('article');
            card.className = 'media-card stack';

            const image = document.createElement('img');
            image.className = 'media-preview';
            image.src = String(item.public_path || '');
            image.alt = String(item.original_name || '');
            card.appendChild(image);

            const info = document.createElement('div');
            info.className = 'stack';
            info.appendChild(createInfoText('strong', '', String(item.original_name || 'Untitled image')));
            info.appendChild(createInfoText('div', 'code', String(item.public_path || '')));
            info.appendChild(createInfoText('p', 'muted', `${String(item.width || 0)}x${String(item.height || 0)}`));
            card.appendChild(info);

            const actions = document.createElement('div');
            actions.className = 'cluster';
            const choose = document.createElement('button');
            choose.type = 'button';
            choose.className = 'button';
            choose.textContent = chooseButtonLabel();
            choose.addEventListener('click', () => applyPickerSelection(item));
            actions.appendChild(choose);
            card.appendChild(actions);
            modalGrid.appendChild(card);
        }
    };

    const loadPickerItems = async () => {
        if (modalStatus) modalStatus.textContent = 'Loading media...';
        try {
            const response = await fetch('/admin/media/browser');
            const payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.message || 'Failed to load media.');
            pickerItems = Array.isArray(payload.items) ? payload.items : [];
            renderPickerGrid();
        } catch (error) {
            if (modalStatus) modalStatus.textContent = 'Failed to load media.';
        }
    };

    const uploadPickerItem = async (event) => {
        event.preventDefault();
        if (!modalUploadForm || !modalUploadInput) return;

        const files = modalUploadInput.files;
        if (!files || files.length === 0) {
            setUploadStatus('Choose an image to upload.', 'error');
            modalUploadInput.focus();
            return;
        }

        if (modalUploadSubmit) {
            modalUploadSubmit.disabled = true;
            modalUploadSubmit.textContent = 'Uploading...';
        }
        setUploadStatus('Uploading image...', 'neutral');

        try {
            const response = await fetch('/admin/media/upload/browser', {
                method: 'POST',
                body: new FormData(modalUploadForm),
            });
            const payload = await response.json();
            if (!response.ok || !payload.ok || !payload.item) {
                throw new Error(payload.message || 'The upload failed.');
            }

            if (modalSearch) {
                modalSearch.value = '';
            }
            pickerItems = [payload.item, ...pickerItems.filter((item) => item.id !== payload.item.id)];
            renderPickerGrid();
            setUploadStatus('Image uploaded successfully.', 'success');
            modalUploadForm.reset();
            applyPickerSelection(payload.item);
        } catch (error) {
            const message = error instanceof Error ? error.message : 'The upload failed.';
            setUploadStatus(message, 'error');
        } finally {
            if (modalUploadSubmit) {
                modalUploadSubmit.disabled = false;
                syncUploadButton();
            }
        }
    };

    const openPicker = (targetId, mode) => {
        pickerMode = mode;
        pickerTarget = document.getElementById(targetId);
        if (!modal || !pickerTarget) return;

        modal.hidden = false;
        document.body.classList.add('has-modal-open');
        if (modalSearch) {
            modalSearch.value = '';
        }
        if (modalUploadForm) {
            modalUploadForm.reset();
        }
        syncUploadButton();
        setUploadStatus('Upload a JPEG, PNG, GIF, or WebP image here.', 'neutral');
        loadPickerItems();
    };

    const closePicker = () => {
        if (!modal) return;
        modal.hidden = true;
        document.body.classList.remove('has-modal-open');
        if (modalUploadForm) {
            modalUploadForm.reset();
        }
        syncUploadButton();
    };

    document.querySelectorAll('.js-open-media-picker').forEach((button) => {
        button.addEventListener('click', () => openPicker(button.dataset.target || '', button.dataset.mode || 'featured'));
    });
    document.querySelectorAll('.js-close-media-picker').forEach((button) => button.addEventListener('click', closePicker));
    if (modalSearch) modalSearch.addEventListener('input', renderPickerGrid);
    if (modalUploadForm) modalUploadForm.addEventListener('submit', uploadPickerItem);

    managedImageFields.forEach((managedField) => {
        if (managedField.field) {
            managedField.field.addEventListener('input', updateImagePreviews);
        }
        if (managedField.uploadInput) {
            managedField.uploadInput.addEventListener('change', (event) => uploadManagedImage(managedField, event));
        }
        setManagedUploadStatus(managedField, managedField.idleMessage, 'neutral');
    });

    if (titleField && slugField) {
        if (slugField.dataset.userEdited !== "true") {
            slugField.dataset.userEdited = slugMatchesGeneratedTitle() ? "false" : "true";
        }

        titleField.addEventListener("input", () => {
            syncSlugFromTitle();
            updateSlugPreview();
        });
        titleField.addEventListener("blur", () => {
            syncSlugFromTitle();
            updateSlugPreview();
            updateUnsavedState();
        });
    }

    if (slugField) {
        slugField.addEventListener("input", () => {
            slugField.dataset.userEdited = slugMatchesGeneratedTitle() ? "false" : "true";
            updateSlugPreview();
        });
        slugField.addEventListener("blur", () => {
            syncSlugField();
            slugField.dataset.userEdited = slugMatchesGeneratedTitle() ? "false" : "true";
            updateSlugPreview();
            updateUnsavedState();
        });
    }

    [titleField, excerptField, seoTitle, seoDescription].forEach((field) => {
        if (field) field.addEventListener("input", updateSeoPreview);
    });
    if (contentTypeField) contentTypeField.addEventListener("change", updatePageSettingsVisibility);
    if (statusField) statusField.addEventListener("change", syncPublishButtonLabel);
    if (categoryField) categoryField.addEventListener("change", updateSlugPreview);

    syncSlugFromTitle();
    syncPublishButtonLabel();
    updateSlugPreview();
    updateSeoPreview();
    updateImagePreviews();
    updatePageSettingsVisibility();
    updateUnsavedState();
    syncUploadButton();

    const autosave = async () => {
        if (isSubmitting) return;
        syncSlugFromTitle();
        syncSlugField();
        updateSlugPreview();
        const data = new URLSearchParams(new FormData(form));
        const autosaveTokenField = form.querySelector('[name="_autosave_token"]');
        if (!autosaveTokenField) return;
        data.set('_csrf_token', autosaveTokenField.value);
        const serialized = data.toString();
        if (serialized === lastAutosavedSerialized) return;
        lastAutosavedSerialized = serialized;
        if (autosaveStatus) autosaveStatus.textContent = 'Autosaving...';
        try {
            const response = await fetch('/admin/content/autosave', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: serialized,
            });
            const payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.message || 'Autosave failed.');
            if (autosaveStatus) autosaveStatus.textContent = `Autosaved ${payload.saved_at}`;
        } catch (error) {
            if (autosaveStatus) autosaveStatus.textContent = 'Autosave failed';
        }
    };

    const queueAutosave = () => {
        updateUnsavedState();
        window.clearTimeout(autosaveTimer);
        autosaveTimer = window.setTimeout(autosave, 1200);
    };

    form.addEventListener('input', queueAutosave);
    form.addEventListener('change', queueAutosave);

    form.addEventListener('submit', () => {
        syncSlugField();
        updateSlugPreview();
        syncPublishButtonLabel();
        isSubmitting = true;
        window.clearTimeout(autosaveTimer);
        formBaseline = currentFormState();

        if (autosaveStatus) autosaveStatus.textContent = 'Autosave paused while saving';
    });

    window.addEventListener('beforeunload', (event) => {
        if (!updateUnsavedState()) return;
        event.preventDefault();
        event.returnValue = '';
    });
})();
</script>
SCRIPT
        );
    }
}







