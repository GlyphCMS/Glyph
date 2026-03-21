<?php

declare(strict_types=1);

use Glyph\domain\content\ContentRecord;
use Glyph\services\content\ContentInput;
use Glyph\services\content\ContentValidationResult;
use Glyph\ui\admin\ContentPageRenderer;
use Glyph\ui\shared\DateTimeFormatter;

$renderer = new ContentPageRenderer(new DateTimeFormatter('m/d/Y', 'g:i A', 'America/New_York'));
$timestamp = '2026-03-10T22:47:52+00:00';
$formattedDateTime = '03/10/2026 6:47 PM';

$contributorHtml = $renderer->renderForm(
    mode: 'create',
    input: new ContentInput(
        type: 'post',
        title: 'Post title',
        slug: '/post-title',
        status: 'draft',
        excerpt: 'Short excerpt',
        bodyHtml: '<p>Hello</p>',
        featuredImage: null,
        parentId: null,
        seoTitle: '',
        seoDescription: '',
    ),
    validationResult: new ContentValidationResult([]),
    successMessage: null,
    errorMessage: null,
    csrfToken: 'csrf-token',
    contentId: null,
    parentOptions: ['' => 'No parent'],
    redirectSlugs: [],
    autosaveSavedAt: $timestamp,
    autosaveCsrfToken: 'autosave-token',
    autosaveDiscardCsrfToken: 'discard-token',
    mediaUploadCsrfToken: 'media-token',
    sanitizeContentHtml: true,
    canBypassHtmlSanitization: false,
    canPublishContent: false,
);

$pageHtml = $renderer->renderForm(
    mode: 'edit',
    input: new ContentInput(
        type: 'page',
        title: 'About',
        slug: '/about',
        status: 'published',
        excerpt: 'About excerpt',
        bodyHtml: '<p>About</p>',
        featuredImage: '/uploads/images/about.png',
        parentId: null,
        seoTitle: 'About',
        seoDescription: 'About page',
        navigationTitle: 'About',
        menuOrder: '1',
        showInNavigation: true,
        seoImage: '/uploads/images/about-seo.png',
    ),
    validationResult: new ContentValidationResult([]),
    successMessage: null,
    errorMessage: null,
    csrfToken: 'csrf-token',
    contentId: 'page_1',
    parentOptions: ['' => 'No parent'],
    redirectSlugs: ['/old-about'],
    autosaveSavedAt: null,
    autosaveCsrfToken: 'autosave-token',
    autosaveDiscardCsrfToken: 'discard-token',
    mediaUploadCsrfToken: 'media-token',
    sanitizeContentHtml: true,
    canBypassHtmlSanitization: true,
    canPublishContent: true,
);

$listHtml = $renderer->renderList(
    contents: [new ContentRecord(
        id: 'post_1',
        type: 'post',
        title: 'Welcome Post',
        slug: '/welcome',
        status: 'published',
        excerpt: 'Short excerpt that is long enough to show the calmer preview treatment.',
        bodyHtml: '<p>Hello</p>',
        featuredImage: null,
        authorId: 'owner_1',
        parentId: null,
        publishedAt: $timestamp,
        createdAt: $timestamp,
        updatedAt: $timestamp,
        redirects: [],
        seoTitle: '',
        seoDescription: '',
    )],
    deleteCsrfToken: 'delete-token',
    filters: [
        'query' => '',
        'type' => '',
        'status' => '',
    ],
    successMessage: null,
);

$excerptPosition = strpos($contributorHtml, 'name="excerpt" type="text"');
$slugPosition = strpos($contributorHtml, 'name="slug" type="text"');
$csrfTokenCount = substr_count($contributorHtml, 'name="_csrf_token"');

return str_contains($contributorHtml, 'id="page-settings-section" hidden')
    && str_contains($pageHtml, 'id="page-settings-section"')
    && !str_contains($pageHtml, 'id="page-settings-section" hidden')
    && str_contains($pageHtml, 'class="editor-tool-group"')
    && str_contains($contributorHtml, 'class="button button-secondary button-publish js-publish-button"')
    && str_contains($contributorHtml, 'Contributors can save drafts, but an author or higher needs to publish them.')
    && str_contains($contributorHtml, 'data-can-publish-content="0"')
    && str_contains($pageHtml, 'data-can-publish-content="1"')
    && str_contains($contributorHtml, '>Save Draft<')
    && !str_contains($contributorHtml, '<option value="published"')
    && str_contains($pageHtml, '<option value="published" selected>Published</option>')
    && $excerptPosition !== false
    && $slugPosition !== false
    && $excerptPosition < $slugPosition
    && str_contains($contributorHtml, 'id="slug-preview"')
    && str_contains($contributorHtml, 'id="autosave-status"')
    && !str_contains($contributorHtml, 'id="unsaved-status"')
    && str_contains($contributorHtml, 'Upload Featured Image')
    && str_contains($contributorHtml, 'Choose a file and it will upload right away.')
    && str_contains($contributorHtml, 'id="featured-image-upload-token" value="media-token"')
    && !str_contains($contributorHtml, 'id="featured-image-upload-form"')
    && str_contains($pageHtml, 'name="seo_image"')
    && str_contains($pageHtml, 'id="seo-image-upload-token" value="media-token"')
    && str_contains($pageHtml, 'class="seo-image-field__upload"')
    && str_contains($pageHtml, 'data-target="seo_image"')
    && str_contains($pageHtml, 'src="/uploads/images/about-seo.png" alt="Social preview image"')
    && $csrfTokenCount === 3
    && str_contains($contributorHtml, $formattedDateTime)
    && !str_contains($contributorHtml, $timestamp)
    && substr_count($listHtml, '>Dashboard<') === 1
    && str_contains($listHtml, 'hero__mobile-action')
    && substr_count($listHtml, '>New Post<') === 2
    && substr_count($listHtml, '>New Page<') === 2
    && str_contains($listHtml, 'content-library-header__actions')
    && str_contains($listHtml, 'content-filter-bar')
    && str_contains($listHtml, 'content-filter-form--live')
    && str_contains($listHtml, 'id="content-library-search"')
    && str_contains($listHtml, 'id="content-type-filter"')
    && str_contains($listHtml, 'id="content-status-filter"')
    && str_contains($listHtml, 'id="content-library-filter-status"')
    && str_contains($listHtml, 'data-content-search="welcome post /welcome"')
    && str_contains($listHtml, 'syncFilters')
    && !str_contains($listHtml, 'Open Media Library')
    && str_contains($listHtml, 'content-directory-list')
    && str_contains($listHtml, 'content-directory-card')
    && str_contains($listHtml, 'content-directory-topline')
    && str_contains($listHtml, 'content-directory-excerpt')
    && str_contains($listHtml, 'content-directory-side')
    && str_contains($listHtml, 'content-directory-updated__value')
    && str_contains($listHtml, 'content-directory-actions')
    && str_contains($listHtml, $formattedDateTime)
    && !str_contains($listHtml, '<table class="table table--content-list">')
    && !str_contains($listHtml, $timestamp)
    && str_contains($pageHtml, '<summary class="sidebar-disclosure__summary"><span>SEO and Preview</span></summary>')
    && str_contains($pageHtml, 'Slug, search preview, and social preview live here instead of the sidebar.')
    && str_contains($pageHtml, 'field-help-row')
    && str_contains($pageHtml, 'seo-description-field');
