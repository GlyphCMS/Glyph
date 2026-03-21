<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\adapters\http\Request;
use Glyph\adapters\http\Response;
use Glyph\adapters\security\CsrfTokenManager;
use Glyph\domain\auth\RoleCapabilities;
use Glyph\domain\categories\CategoryRecord;
use Glyph\services\auth\AuthenticationManager;
use Glyph\services\categories\CategoryInput;
use Glyph\services\categories\CategoryService;
use Glyph\ui\shared\DocumentRenderer;

final class CategoryController
{
    private const SAVE_FORM_ID = 'category_save';
    private const DELETE_FORM_ID = 'category_delete';

    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly CategoryService $categoryService,
        private readonly CsrfTokenManager $csrfTokenManager,
    ) {
    }

    public function index(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        return Response::html($this->renderer()->renderList(
            orderedCategories: $this->categoryService->orderedForDisplay(),
            deleteCsrfToken: $this->csrfTokenManager->token(self::DELETE_FORM_ID),
            successMessage: $this->successMessage($request),
            errorMessage: null,
        ));
    }

    public function showCreate(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        return $this->renderCreateForm(new CategoryInput('', '', '', null), [], null, null);
    }

    public function showEdit(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $editingCategory = $this->categoryService->findById($request->queryTrimmedString('id'));
        if ($editingCategory === null) {
            return Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Not Found</title></head><body><h1>404</h1><p>Category not found.</p></body></html>', 404);
        }

        return $this->renderEditForm(
            editingCategory: $editingCategory,
            input: new CategoryInput(
                name: $editingCategory->name,
                slug: $editingCategory->slug,
                description: $editingCategory->description,
                parentId: $editingCategory->parentId,
            ),
            fieldErrors: [],
            successMessage: $this->successMessage($request),
            errorMessage: null,
        );
    }

    public function save(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $input = CategoryInput::fromPost($request->post());
        $categoryId = $request->postTrimmedString('id');
        $editingCategory = $this->categoryService->findById($categoryId);

        if (!$this->csrfTokenManager->validate(self::SAVE_FORM_ID, $request->postString('_csrf_token'))) {
            return $editingCategory !== null
                ? $this->renderEditForm($editingCategory, $input, [], null, 'Your session token is invalid. Please try again.', 400)
                : $this->renderCreateForm($input, [], null, 'Your session token is invalid. Please try again.', 400);
        }

        $fieldErrors = $this->categoryService->validate($input, $editingCategory?->id);
        if ($fieldErrors !== []) {
            return $editingCategory !== null
                ? $this->renderEditForm($editingCategory, $input, $fieldErrors, null, null, 400)
                : $this->renderCreateForm($input, $fieldErrors, null, null, 400);
        }

        try {
            if ($editingCategory !== null) {
                $this->categoryService->update($editingCategory, $input);

                return Response::redirect('/admin/categories/edit?id=' . rawurlencode($editingCategory->id) . '&saved=1');
            }

            $this->categoryService->create($input);

            return Response::redirect('/admin/categories?created=1');
        } catch (\Throwable $throwable) {
            return $editingCategory !== null
                ? $this->renderEditForm($editingCategory, $input, [], null, $throwable->getMessage(), 400)
                : $this->renderCreateForm($input, [], null, $throwable->getMessage(), 400);
        }
    }

    public function delete(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        if (!$this->csrfTokenManager->validate(self::DELETE_FORM_ID, $request->postString('_csrf_token'))) {
            return Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Bad Request</title></head><body><h1>400</h1><p>Invalid form token.</p></body></html>', 400);
        }

        $category = $this->categoryService->findById($request->postTrimmedString('id'));
        if ($category === null) {
            return Response::redirect('/admin/categories?deleted=1');
        }

        try {
            $this->categoryService->delete($category);
        } catch (\Throwable $throwable) {
            return $this->renderEditForm(
                editingCategory: $category,
                input: new CategoryInput($category->name, $category->slug, $category->description, $category->parentId),
                fieldErrors: [],
                successMessage: null,
                errorMessage: $throwable->getMessage(),
                statusCode: 400,
            );
        }

        return Response::redirect('/admin/categories?deleted=1');
    }

    private function renderCreateForm(
        CategoryInput $input,
        array $fieldErrors,
        ?string $successMessage,
        ?string $errorMessage,
        int $statusCode = 200,
    ): Response {
        return Response::html($this->renderer()->renderForm(
            parentOptions: $this->parentOptions(null),
            input: $input,
            fieldErrors: $fieldErrors,
            editingCategory: null,
            saveCsrfToken: $this->csrfTokenManager->token(self::SAVE_FORM_ID),
            deleteCsrfToken: $this->csrfTokenManager->token(self::DELETE_FORM_ID),
            successMessage: $successMessage,
            errorMessage: $errorMessage,
        ), $statusCode);
    }

    private function renderEditForm(
        CategoryRecord $editingCategory,
        CategoryInput $input,
        array $fieldErrors,
        ?string $successMessage,
        ?string $errorMessage,
        int $statusCode = 200,
    ): Response {
        return Response::html($this->renderer()->renderForm(
            parentOptions: $this->parentOptions($editingCategory),
            input: $input,
            fieldErrors: $fieldErrors,
            editingCategory: $editingCategory,
            saveCsrfToken: $this->csrfTokenManager->token(self::SAVE_FORM_ID),
            deleteCsrfToken: $this->csrfTokenManager->token(self::DELETE_FORM_ID),
            successMessage: $successMessage,
            errorMessage: $errorMessage,
        ), $statusCode);
    }

    /** @return array<string, string> */
    private function parentOptions(?CategoryRecord $editingCategory): array
    {
        $options = ['' => 'No parent'];
        $blockedIds = $editingCategory !== null
            ? $this->categoryService->descendantIdsFor($editingCategory->id)
            : [];

        foreach ($this->categoryService->orderedForDisplay() as $row) {
            $category = $row['category'];
            if (in_array($category->id, $blockedIds, true)) {
                continue;
            }

            $options[$category->id] = str_repeat('-- ', $row['depth']) . $category->name;
        }

        return $options;
    }

    private function successMessage(Request $request): ?string
    {
        return match (true) {
            $request->queryFlag('created') => 'Category created successfully.',
            $request->queryFlag('saved') => 'Category saved successfully.',
            $request->queryFlag('deleted') => 'Category deleted successfully.',
            default => null,
        };
    }

    private function renderer(): CategoryPageRenderer
    {
        return new CategoryPageRenderer();
    }

    private function guard(): ?Response
    {
        $currentUser = $this->authenticationManager->currentUser();
        if ($currentUser === null) {
            return Response::redirect('/login');
        }

        DocumentRenderer::setAdminUserContext($currentUser->email, $currentUser->role, $currentUser->displayNameOrFallback());

        if (!$this->authenticationManager->hasCapability(RoleCapabilities::CATEGORY_MANAGE)) {
            return Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Forbidden</title></head><body><h1>403</h1><p>You do not have permission to manage categories.</p></body></html>', 403);
        }

        return null;
    }
}