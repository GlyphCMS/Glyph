<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\adapters\http\Request;
use Glyph\adapters\http\Response;
use Glyph\adapters\security\CsrfTokenManager;
use Glyph\domain\auth\RoleCapabilities;
use Glyph\domain\media\MediaRecord;
use Glyph\services\auth\AuthenticationManager;
use Glyph\services\media\MediaService;
use Glyph\ui\shared\DocumentRenderer;

final class MediaController
{
    public const UPLOAD_FORM_ID = 'media_upload_form';
    private const DELETE_FORM_ID = 'media_delete_form';

    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly MediaService $mediaService,
        private readonly CsrfTokenManager $csrfTokenManager,
        private readonly array $siteConfig,
    ) {
    }

    public function index(Request $request): Response
    {
        $guard = $this->guard(RoleCapabilities::MEDIA_UPLOAD);
        if ($guard !== null) {
            return $guard;
        }

        $renderer = new MediaPageRenderer();

        return Response::html(
            $renderer->renderLibrary(
                mediaItems: $this->mediaService->listAll(),
                siteConfig: $this->siteConfig,
                uploadCsrfToken: $this->csrfTokenManager->token(self::UPLOAD_FORM_ID),
                deleteCsrfToken: $this->csrfTokenManager->token(self::DELETE_FORM_ID),
                errorMessage: $request->queryString('error'),
                successMessage: $request->queryString('success'),
            )
        );
    }

    public function browser(Request $request): Response
    {
        $guard = $this->guard(RoleCapabilities::MEDIA_UPLOAD);
        if ($guard !== null) {
            return Response::json(['ok' => false, 'message' => 'Unauthorized.'], 403);
        }

        return Response::json([
            'ok' => true,
            'items' => array_map($this->serializeMedia(...), $this->mediaService->listAll()),
        ]);
    }

    public function upload(Request $request): Response
    {
        $guard = $this->guard(RoleCapabilities::MEDIA_UPLOAD);
        if ($guard !== null) {
            return $guard;
        }

        if (!$this->csrfTokenManager->validate(self::UPLOAD_FORM_ID, $request->postString('_csrf_token'))) {
            return Response::redirect('/admin/media?error=' . rawurlencode('Your session token is invalid. Please try again.'));
        }

        $currentUser = $this->authenticationManager->currentUser();
        if ($currentUser === null) {
            return Response::redirect('/login');
        }

        $file = $this->requestMediaFile();
        if ($file === null) {
            return Response::redirect('/admin/media?error=' . rawurlencode('No file was uploaded.'));
        }

        $result = $this->mediaService->upload($file, $currentUser->id);

        if (!$result->isSuccessful) {
            return Response::redirect('/admin/media?error=' . rawurlencode($result->errorMessage ?? 'The upload failed.'));
        }

        return Response::redirect('/admin/media?success=' . rawurlencode('Image uploaded successfully.'));
    }

    public function uploadBrowser(Request $request): Response
    {
        $guard = $this->guard(RoleCapabilities::MEDIA_UPLOAD);
        if ($guard !== null) {
            return Response::json(['ok' => false, 'message' => 'Unauthorized.'], 403);
        }

        if (!$this->csrfTokenManager->validate(self::UPLOAD_FORM_ID, $request->postString('_csrf_token'))) {
            return Response::json(['ok' => false, 'message' => 'Your session token is invalid. Please try again.'], 400);
        }

        $currentUser = $this->authenticationManager->currentUser();
        if ($currentUser === null) {
            return Response::json(['ok' => false, 'message' => 'Unauthorized.'], 403);
        }

        $file = $this->requestMediaFile();
        if ($file === null) {
            return Response::json(['ok' => false, 'message' => 'No file was uploaded.'], 400);
        }

        $result = $this->mediaService->upload($file, $currentUser->id);

        if (!$result->isSuccessful || $result->media === null) {
            return Response::json([
                'ok' => false,
                'message' => $result->errorMessage ?? 'The upload failed.',
            ], 400);
        }

        return Response::json([
            'ok' => true,
            'message' => 'Image uploaded successfully.',
            'item' => $this->serializeMedia($result->media),
        ], 201);
    }

    public function delete(Request $request): Response
    {
        $guard = $this->guard(RoleCapabilities::MEDIA_UPLOAD);
        if ($guard !== null) {
            return $guard;
        }

        if (!$this->csrfTokenManager->validate(self::DELETE_FORM_ID, $request->postString('_csrf_token'))) {
            return Response::redirect('/admin/media?error=' . rawurlencode('Your session token is invalid. Please try again.'));
        }

        $id = $request->postTrimmedString('id');
        if ($id === '') {
            return Response::redirect('/admin/media?error=' . rawurlencode('Choose a media item to delete.'));
        }

        try {
            $deleted = $this->mediaService->deleteById($id);
        } catch (\Throwable $throwable) {
            return Response::redirect('/admin/media?error=' . rawurlencode($throwable->getMessage()));
        }

        if (!$deleted) {
            return Response::redirect('/admin/media?error=' . rawurlencode('Media item not found.'));
        }

        return Response::redirect('/admin/media?success=' . rawurlencode('Image deleted successfully.'));
    }

    private function guard(string $capability): ?Response
    {
        $currentUser = $this->authenticationManager->currentUser();

        if ($currentUser === null) {
            return Response::redirect('/login');
        }

        DocumentRenderer::setAdminUserContext($currentUser->email, $currentUser->role, $currentUser->displayNameOrFallback());

        if (!$this->authenticationManager->hasCapability($capability)) {
            return Response::html(
                '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Forbidden</title></head><body><h1>403</h1><p>You do not have permission to access this page.</p></body></html>',
                403,
            );
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function requestMediaFile(): ?array
    {
        $file = $_FILES['media_file'] ?? null;

        return is_array($file) ? $file : null;
    }

    /** @return array<string, int|string> */
    private function serializeMedia(MediaRecord $media): array
    {
        return [
            'id' => $media->id,
            'original_name' => $media->originalName,
            'public_path' => $media->publicPath,
            'mime_type' => $media->mimeType,
            'size_bytes' => $media->sizeBytes,
            'width' => $media->width,
            'height' => $media->height,
            'created_at' => $media->createdAt,
        ];
    }
}


