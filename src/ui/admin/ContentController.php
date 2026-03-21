<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\adapters\http\Request;
use Glyph\adapters\http\Response;
use Glyph\adapters\security\CsrfTokenManager;
use Glyph\domain\auth\RoleCapabilities;
use Glyph\domain\content\ContentRecord;
use Glyph\services\auth\AuthenticationManager;
use Glyph\services\categories\CategoryService;
use Glyph\services\content\ContentAutosaveService;
use Glyph\services\content\ContentInput;
use Glyph\services\content\ContentService;
use Glyph\services\content\ContentValidationResult;
use Glyph\services\content\SlugManager;
use Glyph\ui\shared\DateTimeFormatter;
use Glyph\ui\shared\DocumentRenderer;

final class ContentController
{
    private const CREATE_FORM_ID = 'content_create';
    private const EDIT_FORM_ID = 'content_edit';
    private const DELETE_FORM_ID = 'content_delete';
    private const AUTOSAVE_FORM_ID = 'content_autosave';
    private const AUTOSAVE_DISCARD_FORM_ID = 'content_autosave_discard';
    private const MEDIA_UPLOAD_FORM_ID = MediaController::UPLOAD_FORM_ID;

    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly ContentService $contentService,
        private readonly CsrfTokenManager $csrfTokenManager,
        private readonly ContentAutosaveService $contentAutosaveService,
        private readonly SlugManager $slugManager,
        private readonly array $siteConfig = [],
        private readonly ?CategoryService $categoryService = null,
    ) {
    }

    public function index(Request $request): Response
    {
        $guard = $this->guard(RoleCapabilities::CONTENT_READ);
        if ($guard !== null) {
            return $guard;
        }

        $renderer = $this->renderer();
        $filters = $this->listFilters($request);
        $contents = $this->filterContents($this->contentService->listAll(), $filters);
        $successMessage = $request->queryFlag('deleted')
            ? 'Content deleted successfully.'
            : null;

        return Response::html(
            $renderer->renderList(
                contents: $contents,
                deleteCsrfToken: $this->csrfTokenManager->token(self::DELETE_FORM_ID),
                filters: $filters,
                successMessage: $successMessage,
                categoryLabels: $this->categoryLabels(),
            )
        );
    }

    public function showCreate(Request $request): Response
    {
        $guard = $this->guard(RoleCapabilities::CONTENT_CREATE);
        if ($guard !== null) {
            return $guard;
        }

        $type = $request->queryString('type') ?? 'post';
        $safeType = $type === 'page' ? 'page' : 'post';
        $autosave = $this->contentAutosaveService->load($this->autosaveScopeForCreate($safeType));
        $canBypassHtmlSanitization = $this->canBypassHtmlSanitization();
        $canPublishContent = $this->canPublishContent();

        $input = $this->normalizeContentInput($autosave?->input ?? new ContentInput(
            type: $safeType,
            title: '',
            slug: '',
            status: 'draft',
            excerpt: '',
            bodyHtml: '',
            featuredImage: null,
            parentId: null,
            seoTitle: '',
            seoDescription: '',
            navigationTitle: '',
            menuOrder: '0',
            showInNavigation: $safeType === 'page',
        ))->withBypassPermission($canBypassHtmlSanitization);

        $renderer = $this->renderer();

        return Response::html(
            $renderer->renderForm(
                mode: 'create',
                input: $input,
                validationResult: new ContentValidationResult([]),
                successMessage: null,
                errorMessage: null,
                csrfToken: $this->csrfTokenManager->token(self::CREATE_FORM_ID),
                contentId: null,
                parentOptions: $this->pageParentOptions(null),
                redirectSlugs: [],
                autosaveSavedAt: $autosave?->savedAt,
                autosaveCsrfToken: $this->csrfTokenManager->token(self::AUTOSAVE_FORM_ID),
                autosaveDiscardCsrfToken: $this->csrfTokenManager->token(self::AUTOSAVE_DISCARD_FORM_ID),
                mediaUploadCsrfToken: $this->csrfTokenManager->token(self::MEDIA_UPLOAD_FORM_ID),
                sanitizeContentHtml: $this->contentService->sanitizationEnabled(),
                canBypassHtmlSanitization: $canBypassHtmlSanitization,
                canPublishContent: $canPublishContent,
                categoryOptions: $this->categoryOptions(),
                categoryPaths: $this->categoryPaths(),
            )
        );
    }

    public function create(Request $request): Response
    {
        $guard = $this->guard(RoleCapabilities::CONTENT_CREATE);
        if ($guard !== null) {
            return $guard;
        }

        $canBypassHtmlSanitization = $this->canBypassHtmlSanitization();
        $requestedInput = $this->contentInputFromPost($request->post());
        $input = $this->enforcePublishPermission($requestedInput);
        $validationResult = $this->contentService->validate($input, $canBypassHtmlSanitization);

        if (!$this->csrfTokenManager->validate(self::CREATE_FORM_ID, $request->postString('_csrf_token'))) {
            return $this->renderInvalidCreate($input, $validationResult, 'Your session token is invalid. Please try again.', 400);
        }

        if (!$validationResult->isValid()) {
            return $this->renderInvalidCreate($input, $validationResult, null);
        }

        $currentUser = $this->authenticationManager->currentUser();
        if ($currentUser === null) {
            return Response::redirect('/login');
        }

        try {
            $created = $this->contentService->create($input, $currentUser->id, $canBypassHtmlSanitization);
            $this->contentAutosaveService->delete($this->autosaveScopeForCreate($created->type));
        } catch (\Throwable $throwable) {
            return $this->renderInvalidCreate($input, $validationResult, $throwable->getMessage());
        }

        $createdQuery = $input->status === 'draft' && $requestedInput->status === 'published'
            ? 'created=1&saved_as_draft=1'
            : 'created=1';

        return Response::redirect($this->editUrl($created, $createdQuery));
    }

    public function showEdit(Request $request): Response
    {
        $guard = $this->guard(RoleCapabilities::CONTENT_UPDATE);
        if ($guard !== null) {
            return $guard;
        }

        $type = $request->queryTrimmedString('type');
        $id = $request->queryTrimmedString('id');
        $content = $this->contentService->findById($type, $id);

        if ($content === null) {
            return Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Not Found</title></head><body><h1>404</h1><p>Content not found.</p></body></html>', 404);
        }

        $autosaveScope = $this->autosaveScopeForExisting($content);
        $skipAutosaveRestore = $request->queryFlag('saved') || $request->queryFlag('created');

        if ($skipAutosaveRestore) {
            $this->contentAutosaveService->delete($autosaveScope);
        }

        $autosave = $skipAutosaveRestore ? null : $this->contentAutosaveService->load($autosaveScope);
        $canBypassHtmlSanitization = $this->canBypassHtmlSanitization();
        $canPublishContent = $this->canPublishContent();

        $input = $this->normalizeContentInput($autosave !== null
            ? $autosave->input
            : new ContentInput(
                type: $content->type,
                title: $content->title,
                slug: $content->baseSlug !== '' ? $content->baseSlug : $content->slug,
                status: $content->status,
                excerpt: $content->excerpt,
                bodyHtml: $content->bodyHtml,
                featuredImage: $content->featuredImage,
                parentId: $content->parentId,
                categoryId: $content->categoryId,
                seoTitle: $content->seoTitle,
                seoDescription: $content->seoDescription,
                navigationTitle: $content->navigationTitle,
                menuOrder: (string) $content->menuOrder,
                showInNavigation: $content->showInNavigation,
                bypassHtmlSanitization: $content->bypassHtmlSanitization,
                seoImage: $content->seoImage,
            ))->withBypassPermission($canBypassHtmlSanitization);

        $renderer = $this->renderer();
        $successMessage = match (true) {
            $request->queryFlag('saved_as_draft') => 'Content was saved as a draft. Publishing requires the Author role or higher.',
            $request->queryFlag('created') => 'Content created successfully.',
            $request->queryFlag('saved') => 'Content saved successfully.',
            default => null,
        };

        return Response::html(
            $renderer->renderForm(
                mode: 'edit',
                input: $input,
                validationResult: new ContentValidationResult([]),
                successMessage: $successMessage,
                errorMessage: null,
                csrfToken: $this->csrfTokenManager->token(self::EDIT_FORM_ID),
                contentId: $content->id,
                parentOptions: $this->pageParentOptions($content->id),
                redirectSlugs: $content->redirects,
                autosaveSavedAt: $autosave?->savedAt,
                autosaveCsrfToken: $this->csrfTokenManager->token(self::AUTOSAVE_FORM_ID),
                autosaveDiscardCsrfToken: $this->csrfTokenManager->token(self::AUTOSAVE_DISCARD_FORM_ID),
                mediaUploadCsrfToken: $this->csrfTokenManager->token(self::MEDIA_UPLOAD_FORM_ID),
                sanitizeContentHtml: $this->contentService->sanitizationEnabled(),
                canBypassHtmlSanitization: $canBypassHtmlSanitization,
                canPublishContent: $canPublishContent,
                categoryOptions: $this->categoryOptions(),
                categoryPaths: $this->categoryPaths(),
            )
        );
    }

    public function edit(Request $request): Response
    {
        $guard = $this->guard(RoleCapabilities::CONTENT_UPDATE);
        if ($guard !== null) {
            return $guard;
        }

        $post = $request->post();
        $type = $request->postTrimmedString('type');
        $id = $request->postTrimmedString('id');
        $content = $this->contentService->findById($type, $id);

        if ($content === null) {
            return Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Not Found</title></head><body><h1>404</h1><p>Content not found.</p></body></html>', 404);
        }

        $canBypassHtmlSanitization = $this->canBypassHtmlSanitization();
        $requestedInput = $this->applyAutosaveImageFallbacks($content, $this->contentInputFromPost($post));
        $input = $this->enforcePublishPermission($requestedInput, $content);
        $validationResult = $this->contentService->validate($input, $canBypassHtmlSanitization);

        if (!$this->csrfTokenManager->validate(self::EDIT_FORM_ID, $request->postString('_csrf_token'))) {
            return $this->renderInvalidEdit($content, $input, $validationResult, 'Your session token is invalid. Please try again.', 400);
        }

        if (!$validationResult->isValid()) {
            return $this->renderInvalidEdit($content, $input, $validationResult, null);
        }

        try {
            $updated = $this->contentService->update($content, $input, $canBypassHtmlSanitization);
            $this->contentAutosaveService->delete($this->autosaveScopeForExisting($updated));
        } catch (\Throwable $throwable) {
            return $this->renderInvalidEdit($content, $input, $validationResult, $throwable->getMessage());
        }

        $updatedQuery = $input->status === 'draft'
            && $requestedInput->status === 'published'
            && $content->status !== 'published'
            ? 'saved=1&saved_as_draft=1'
            : 'saved=1';

        return Response::redirect($this->editUrl($updated, $updatedQuery));
    }

    public function autosave(Request $request): Response
    {
        $guard = $this->guard(RoleCapabilities::CONTENT_UPDATE);
        if ($guard !== null) {
            return Response::json(['ok' => false, 'message' => 'Unauthorized.'], 403);
        }

        if (!$this->csrfTokenManager->validate(self::AUTOSAVE_FORM_ID, $request->postString('_csrf_token'))) {
            return Response::json(['ok' => false, 'message' => 'Invalid autosave token.'], 400);
        }

        $mode = $request->postString('mode');
        $input = $this->contentInputFromPost($request->post());

        try {
            if ($mode === 'edit') {
                $type = $request->postTrimmedString('type');
                $id = $request->postTrimmedString('id');
                $content = $this->contentService->findById($type, $id);

                if ($content === null) {
                    return Response::json(['ok' => false, 'message' => 'Content not found.'], 404);
                }

                $this->contentAutosaveService->save($this->autosaveScopeForExisting($content), $input);
            } else {
                $createType = $input->type === 'page' ? 'page' : 'post';
                $this->contentAutosaveService->save($this->autosaveScopeForCreate($createType), $input);
            }
        } catch (\Throwable $throwable) {
            return Response::json(['ok' => false, 'message' => $throwable->getMessage()], 400);
        }

        return Response::json(['ok' => true, 'saved_at' => $this->dateTimeFormatter()->formatDateTime(gmdate('c'))]);
    }

    public function discardAutosave(Request $request): Response
    {
        $guard = $this->guard(RoleCapabilities::CONTENT_UPDATE);
        if ($guard !== null) {
            return $guard;
        }

        if (!$this->csrfTokenManager->validate(self::AUTOSAVE_DISCARD_FORM_ID, $request->postString('_csrf_token'))) {
            return Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Bad Request</title></head><body><h1>400</h1><p>Invalid form token.</p></body></html>', 400);
        }

        $mode = $request->postTrimmedString('mode');
        $type = $request->postTrimmedString('type');
        if ($type === '') {
            $type = 'post';
        }

        if ($mode === 'edit') {
            $id = $request->postTrimmedString('id');
            $content = $this->contentService->findById($type, $id);
            if ($content !== null) {
                $this->contentAutosaveService->delete($this->autosaveScopeForExisting($content));
                return Response::redirect($this->editUrl($content));
            }
        }

        $safeType = $type === 'page' ? 'page' : 'post';
        $this->contentAutosaveService->delete($this->autosaveScopeForCreate($safeType));

        return Response::redirect('/admin/content/create?type=' . rawurlencode($safeType));
    }

    public function delete(Request $request): Response
    {
        $guard = $this->guard(RoleCapabilities::CONTENT_DELETE);
        if ($guard !== null) {
            return $guard;
        }

        if (!$this->csrfTokenManager->validate(self::DELETE_FORM_ID, $request->postString('_csrf_token'))) {
            return Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Bad Request</title></head><body><h1>400</h1><p>Invalid form token.</p></body></html>', 400);
        }

        $type = $request->postTrimmedString('type');
        $id = $request->postTrimmedString('id');
        $content = $this->contentService->findById($type, $id);

        if ($content !== null) {
            $this->contentService->delete($content);
            $this->contentAutosaveService->delete($this->autosaveScopeForExisting($content));
        }

        return Response::redirect('/admin/content?deleted=1');
    }

    private function renderInvalidCreate(ContentInput $input, ContentValidationResult $validationResult, ?string $errorMessage, int $statusCode = 200): Response
    {
        $renderer = $this->renderer();

        return Response::html(
            $renderer->renderForm(
                mode: 'create',
                input: $input,
                validationResult: $validationResult,
                successMessage: null,
                errorMessage: $errorMessage,
                csrfToken: $this->csrfTokenManager->token(self::CREATE_FORM_ID),
                contentId: null,
                parentOptions: $this->pageParentOptions(null),
                redirectSlugs: [],
                autosaveSavedAt: null,
                autosaveCsrfToken: $this->csrfTokenManager->token(self::AUTOSAVE_FORM_ID),
                autosaveDiscardCsrfToken: $this->csrfTokenManager->token(self::AUTOSAVE_DISCARD_FORM_ID),
                mediaUploadCsrfToken: $this->csrfTokenManager->token(self::MEDIA_UPLOAD_FORM_ID),
                sanitizeContentHtml: $this->contentService->sanitizationEnabled(),
                canBypassHtmlSanitization: $this->canBypassHtmlSanitization(),
                canPublishContent: $this->canPublishContent(),
                categoryOptions: $this->categoryOptions(),
                categoryPaths: $this->categoryPaths(),
            ),
            $statusCode,
        );
    }

    private function renderInvalidEdit(ContentRecord $content, ContentInput $input, ContentValidationResult $validationResult, ?string $errorMessage, int $statusCode = 200): Response
    {
        $renderer = $this->renderer();

        return Response::html(
            $renderer->renderForm(
                mode: 'edit',
                input: $input,
                validationResult: $validationResult,
                successMessage: null,
                errorMessage: $errorMessage,
                csrfToken: $this->csrfTokenManager->token(self::EDIT_FORM_ID),
                contentId: $content->id,
                parentOptions: $this->pageParentOptions($content->id),
                redirectSlugs: $content->redirects,
                autosaveSavedAt: null,
                autosaveCsrfToken: $this->csrfTokenManager->token(self::AUTOSAVE_FORM_ID),
                autosaveDiscardCsrfToken: $this->csrfTokenManager->token(self::AUTOSAVE_DISCARD_FORM_ID),
                mediaUploadCsrfToken: $this->csrfTokenManager->token(self::MEDIA_UPLOAD_FORM_ID),
                sanitizeContentHtml: $this->contentService->sanitizationEnabled(),
                canBypassHtmlSanitization: $this->canBypassHtmlSanitization(),
                canPublishContent: $this->canPublishContent(),
                categoryOptions: $this->categoryOptions(),
                categoryPaths: $this->categoryPaths(),
            ),
            $statusCode,
        );
    }

    private function renderer(): ContentPageRenderer
    {
        return new ContentPageRenderer($this->dateTimeFormatter());
    }

    private function dateTimeFormatter(): DateTimeFormatter
    {
        return DateTimeFormatter::fromSiteConfig($this->siteConfig);
    }

    /**
     * @param list<ContentRecord> $contents
     * @param array<string, string> $filters
     * @return list<ContentRecord>
     */
    private function filterContents(array $contents, array $filters): array
    {
        return array_values(array_filter($contents, static function (ContentRecord $content) use ($filters): bool {
            if ($filters['type'] !== '' && $content->type !== $filters['type']) {
                return false;
            }
            if ($filters['status'] !== '' && $content->status !== $filters['status']) {
                return false;
            }
            if ($filters['query'] !== '') {
                $haystack = mb_strtolower($content->title . ' ' . $content->slug);
                $needle = mb_strtolower($filters['query']);
                if (!str_contains($haystack, $needle)) {
                    return false;
                }
            }
            return true;
        }));
    }

    /** @return array<string, string> */
    private function listFilters(Request $request): array
    {
        $type = $request->queryTrimmedString('type');
        $status = $request->queryTrimmedString('status');
        $search = $request->queryTrimmedString('q');

        return [
            'type' => in_array($type, ['post', 'page'], true) ? $type : '',
            'status' => in_array($status, ['draft', 'published'], true) ? $status : '',
            'query' => $search,
        ];
    }

    /** @return array<string, string> */
    private function pageParentOptions(?string $excludeContentId): array
    {
        $options = ['' => 'No parent'];

        foreach ($this->contentService->listAll() as $content) {
            if ($content->type !== 'page') {
                continue;
            }
            if ($excludeContentId !== null && $content->id === $excludeContentId) {
                continue;
            }

            $options[$content->id] = $content->title . ' (' . $content->slug . ')';
        }

        return $options;
    }

    private function editUrl(ContentRecord $content, string $queryString = ''): string
    {
        $url = '/admin/content/edit?type=' . rawurlencode($content->type) . '&id=' . rawurlencode($content->id);

        return $queryString !== '' ? $url . '&' . $queryString : $url;
    }

    private function autosaveScopeForCreate(string $type): string
    {
        return 'create_' . $type;
    }

    private function autosaveScopeForExisting(ContentRecord $content): string
    {
        return 'edit_' . $content->type . '_' . $content->id;
    }

    private function canBypassHtmlSanitization(): bool
    {
        return $this->authenticationManager->hasCapability(RoleCapabilities::SETTINGS_MANAGE);
    }

    private function canPublishContent(): bool
    {
        return $this->authenticationManager->hasCapability(RoleCapabilities::CONTENT_PUBLISH);
    }

    private function enforcePublishPermission(ContentInput $input, ?ContentRecord $existing = null): ContentInput
    {
        if ($this->canPublishContent()) {
            return $input;
        }

        if ($input->status !== 'published') {
            return $input;
        }

        if ($existing !== null && $existing->status === 'published') {
            return $input;
        }

        return $input->withStatus('draft');
    }

    /**
     * @param array<string, mixed> $post
     */
    private function contentInputFromPost(array $post): ContentInput
    {
        return $this->normalizeContentInput(ContentInput::fromPost($post)->withBypassPermission($this->canBypassHtmlSanitization()));
    }

    private function normalizeContentInput(ContentInput $input): ContentInput
    {
        $slug = trim($input->slug);

        return $input->withSlug($slug === '' ? '' : $this->slugManager->normalizeSegment($slug));
    }

    private function applyAutosaveImageFallbacks(ContentRecord $content, ContentInput $input): ContentInput
    {
        $autosave = $this->contentAutosaveService->load($this->autosaveScopeForExisting($content));

        if ($autosave === null) {
            return $input;
        }

        $resolved = $input;
        $autosaveFeaturedImage = $autosave->input->featuredImage;
        if (
            $input->featuredImage === $content->featuredImage
            && $autosaveFeaturedImage !== $content->featuredImage
            && $autosaveFeaturedImage !== $input->featuredImage
        ) {
            $resolved = $resolved->withFeaturedImage($autosaveFeaturedImage);
        }

        $autosaveSeoImage = $autosave->input->seoImage;
        if (
            $resolved->seoImage === $content->seoImage
            && $autosaveSeoImage !== $content->seoImage
            && $autosaveSeoImage !== $resolved->seoImage
        ) {
            $resolved = $resolved->withSeoImage($autosaveSeoImage);
        }

        return $resolved;
    }

    private function guard(string $capability): ?Response
    {
        $currentUser = $this->authenticationManager->currentUser();
        if ($currentUser === null) {
            return Response::redirect('/login');
        }

        DocumentRenderer::setAdminUserContext($currentUser->email, $currentUser->role, $currentUser->displayNameOrFallback());

        if (!$this->authenticationManager->hasCapability($capability)) {
            return Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Forbidden</title></head><body><h1>403</h1><p>You do not have permission to access this page.</p></body></html>', 403);
        }

        return null;
    }

    /** @return array<string, string> */
    private function categoryOptions(): array
    {
        return $this->categoryService?->options() ?? ['' => 'No category'];
    }

    /** @return array<string, string> */
    private function categoryPaths(): array
    {
        return $this->categoryService?->categoryPathsById() ?? [];
    }

    /** @return array<string, string> */
    private function categoryLabels(): array
    {
        if ($this->categoryService === null) {
            return [];
        }

        $labels = [];
        foreach ($this->categoryService->orderedForDisplay() as $row) {
            $category = $row['category'];
            $path = trim((string) $row['archive_path'], '/');
            $labels[$category->id] = $path === ''
                ? $category->name
                : implode(' / ', array_map(
                    static fn (string $segment): string => ucwords(str_replace(['-', '_'], ' ', $segment)),
                    array_filter(explode('/', $path)),
                ));
        }

        return $labels;
    }
}
