<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\adapters\http\Request;
use Glyph\adapters\http\Response;
use Glyph\adapters\security\CsrfTokenManager;
use Glyph\domain\auth\RoleCapabilities;
use Glyph\services\auth\AuthenticationManager;
use Glyph\services\users\UserFormInput;
use Glyph\services\users\UserFormValidationResult;
use Glyph\services\users\UserManager;
use Glyph\ui\shared\DateTimeFormatter;

final class UserController
{
    private const CREATE_FORM_ID = 'user_create';
    private const EDIT_FORM_ID = 'user_edit';

    /** @param array<string, mixed> $siteConfig */
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly CsrfTokenManager $csrfTokenManager,
        private readonly UserManager $userManager,
        private readonly array $siteConfig = [],
    ) {
    }

    public function index(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $renderer = new UserPageRenderer(DateTimeFormatter::fromSiteConfig($this->siteConfig));

        return Response::html($renderer->renderList($this->userManager->listUsers()));
    }

    public function showCreate(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $renderer = new UserPageRenderer();

        return Response::html($renderer->renderForm(
            mode: 'create',
            input: new UserFormInput('', '', 'reader', true, '', ''),
            validation: new UserFormValidationResult([]),
            roleOptions: $this->userManager->roleOptions(),
            csrfToken: $this->csrfTokenManager->token(self::CREATE_FORM_ID),
            userId: null,
            successMessage: null,
            errorMessage: null,
        ));
    }

    public function create(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $input = UserFormInput::fromPost($request->post());

        if (!$this->csrfTokenManager->validate(self::CREATE_FORM_ID, $request->postTrimmedString('_csrf_token'))) {
            return $this->renderCreate($input, new UserFormValidationResult([]), 'Your session token is invalid. Please try again.');
        }

        $validation = $this->userManager->validateForCreate($input);

        if (!$validation->isValid()) {
            return $this->renderCreate($input, $validation, null);
        }

        try {
            $this->userManager->create($input);
        } catch (\Throwable $throwable) {
            return $this->renderCreate($input, $validation, $throwable->getMessage());
        }

        return Response::redirect('/admin/users');
    }

    public function showEdit(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $user = $this->userManager->findUser($request->queryTrimmedString('id'));

        if ($user === null) {
            return Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Not Found</title></head><body><h1>404</h1><p>User not found.</p></body></html>', 404);
        }

        $renderer = new UserPageRenderer();

        return Response::html($renderer->renderForm(
            mode: 'edit',
            input: UserFormInput::fromUser($user->email, $user->displayNameOrFallback(), $user->role, $user->isActive),
            validation: new UserFormValidationResult([]),
            roleOptions: $this->userManager->roleOptions(),
            csrfToken: $this->csrfTokenManager->token(self::EDIT_FORM_ID),
            userId: $user->id,
            successMessage: null,
            errorMessage: null,
        ));
    }

    public function edit(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $user = $this->userManager->findUser($request->postTrimmedString('id'));

        if ($user === null) {
            return Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Not Found</title></head><body><h1>404</h1><p>User not found.</p></body></html>', 404);
        }

        $input = UserFormInput::fromPost($request->post());

        if (!$this->csrfTokenManager->validate(self::EDIT_FORM_ID, $request->postTrimmedString('_csrf_token'))) {
            return $this->renderEdit($user->id, $input, new UserFormValidationResult([]), 'Your session token is invalid. Please try again.');
        }

        $validation = $this->userManager->validateForEdit($input, $user);

        if (!$validation->isValid()) {
            return $this->renderEdit($user->id, $input, $validation, null);
        }

        try {
            $this->userManager->update($user, $input);
        } catch (\Throwable $throwable) {
            return $this->renderEdit($user->id, $input, $validation, $throwable->getMessage());
        }

        return Response::redirect('/admin/users');
    }

    private function renderCreate(UserFormInput $input, UserFormValidationResult $validation, ?string $errorMessage): Response
    {
        $renderer = new UserPageRenderer();

        return Response::html($renderer->renderForm(
            mode: 'create',
            input: $input,
            validation: $validation,
            roleOptions: $this->userManager->roleOptions(),
            csrfToken: $this->csrfTokenManager->token(self::CREATE_FORM_ID),
            userId: null,
            successMessage: null,
            errorMessage: $errorMessage,
        ));
    }

    private function renderEdit(string $userId, UserFormInput $input, UserFormValidationResult $validation, ?string $errorMessage): Response
    {
        $renderer = new UserPageRenderer();

        return Response::html($renderer->renderForm(
            mode: 'edit',
            input: $input,
            validation: $validation,
            roleOptions: $this->userManager->roleOptions(),
            csrfToken: $this->csrfTokenManager->token(self::EDIT_FORM_ID),
            userId: $userId,
            successMessage: null,
            errorMessage: $errorMessage,
        ));
    }

    private function guard(): ?Response
    {
        $currentUser = $this->authenticationManager->currentUser();

        if ($currentUser === null) {
            return Response::redirect('/login');
        }

        \Glyph\ui\shared\DocumentRenderer::setAdminUserContext($currentUser->email, $currentUser->role, $currentUser->displayNameOrFallback());

        if (!$this->authenticationManager->hasCapability(RoleCapabilities::USER_MANAGE)) {
            return Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Forbidden</title></head><body><h1>403</h1><p>You do not have permission to manage users.</p></body></html>', 403);
        }

        return null;
    }
}

