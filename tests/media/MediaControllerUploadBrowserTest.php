<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\filesystem\UploadedFileInspector;
use Glyph\adapters\http\Request;
use Glyph\adapters\security\CsrfTokenManager;
use Glyph\adapters\security\PasswordHasher;
use Glyph\adapters\security\SecretGenerator;
use Glyph\adapters\session\SessionManager;
use Glyph\adapters\storage\MediaFileRepository;
use Glyph\adapters\storage\UserFileRepository;
use Glyph\domain\users\UserRecord;
use Glyph\services\auth\AuthenticationManager;
use Glyph\services\media\MediaService;
use Glyph\services\media\MediaValidator;
use Glyph\ui\admin\MediaController;

$root = sys_get_temp_dir() . '/glyph-media-browser-upload-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();
$previousFiles = $_FILES;

$readResponse = static function (object $response): array {
    $statusProperty = new ReflectionProperty($response, 'statusCode');
    $statusProperty->setAccessible(true);
    $bodyProperty = new ReflectionProperty($response, 'body');
    $bodyProperty->setAccessible(true);

    return [
        'status' => $statusProperty->getValue($response),
        'body' => $bodyProperty->getValue($response),
    ];
};

try {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $filesystem->ensureDirectoryExists($root . '/data/media');
    $filesystem->ensureDirectoryExists($root . '/data/users');
    $filesystem->ensureDirectoryExists($root . '/data/sessions');
    $filesystem->ensureDirectoryExists($root . '/uploads/images');
    $filesystem->ensureDirectoryExists($root . '/tmp');

    $passwordHasher = new PasswordHasher();
    $secretGenerator = new SecretGenerator();

    $userRepository = new UserFileRepository($filesystem, $root . '/data/users');
    $userRepository->save(new UserRecord(
        id: 'owner_1',
        email: 'owner@example.com',
        passwordHash: $passwordHasher->hash('password123'),
        role: 'owner',
        isActive: true,
        createdAt: gmdate('c'),
        updatedAt: gmdate('c'),
        lastLoginAt: null,
        rememberTokens: [],
        passwordResetTokens: [],
        displayName: 'Owner',
    ));

    $sessionManager = new SessionManager(
        authConfig: [
            'session_cookie_name' => 'glyph_media_upload_session',
            'session_lifetime_seconds' => 7200,
            'session_cookie_same_site' => 'Lax',
        ],
        sessionSavePath: $root . '/data/sessions',
    );
    $sessionManager->start();
    $sessionManager->set('auth_user_id', 'owner_1');

    $authenticationManager = new AuthenticationManager(
        sessionManager: $sessionManager,
        userRepository: $userRepository,
        passwordHasher: $passwordHasher,
        secretGenerator: $secretGenerator,
        authConfig: [
            'remember_cookie_name' => 'glyph_media_upload_remember',
            'remember_lifetime_seconds' => 1209600,
            'password_reset_lifetime_seconds' => 3600,
            'session_cookie_same_site' => 'Lax',
        ],
    );

    $mediaService = new MediaService(
        filesystem: $filesystem,
        uploadedFileInspector: new UploadedFileInspector(),
        mediaValidator: new MediaValidator(['png'], ['image/png'], 4096),
        mediaRepository: new MediaFileRepository($filesystem, $root . '/data/media'),
        secretGenerator: $secretGenerator,
        uploadsImagesPath: $root . '/uploads/images',
    );

    $csrfTokenManager = new CsrfTokenManager($sessionManager, $secretGenerator);
    $controller = new MediaController(
        $authenticationManager,
        $mediaService,
        $csrfTokenManager,
        [],
    );

    $imagePath = $root . '/tmp/hero.png';
    $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4//8/AwAI/AL+KDv2WQAAAABJRU5ErkJggg==', true);
    if (!is_string($pngData) || $pngData === '') {
        return false;
    }
    file_put_contents($imagePath, $pngData);

    $_FILES['media_file'] = [
        'name' => 'hero.png',
        'tmp_name' => $imagePath,
        'size' => filesize($imagePath),
        'error' => UPLOAD_ERR_OK,
    ];

    $invalidResponse = $controller->uploadBrowser(new Request(
        method: 'POST',
        path: '/admin/media/upload/browser',
        query: [],
        post: ['_csrf_token' => 'invalid-token'],
        server: [],
        cookies: [],
    ));
    $invalidPayload = $readResponse($invalidResponse);
    $invalidDecoded = json_decode((string) $invalidPayload['body'], true);

    if ($invalidPayload['status'] !== 400 || !is_array($invalidDecoded) || ($invalidDecoded['ok'] ?? true) !== false) {
        return false;
    }

    if ($mediaService->listAll() !== [] || !is_file($imagePath)) {
        return false;
    }

    $response = $controller->uploadBrowser(new Request(
        method: 'POST',
        path: '/admin/media/upload/browser',
        query: [],
        post: ['_csrf_token' => $csrfTokenManager->token(MediaController::UPLOAD_FORM_ID)],
        server: [],
        cookies: [],
    ));

    $responseData = $readResponse($response);
    $decoded = json_decode((string) $responseData['body'], true);

    if ($responseData['status'] !== 201 || !is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
        return false;
    }

    if (!isset($decoded['item']) || !is_array($decoded['item'])) {
        return false;
    }

    if (($decoded['item']['original_name'] ?? null) !== 'hero.png') {
        return false;
    }

    $savedItems = $mediaService->listAll();
    if (count($savedItems) !== 1) {
        return false;
    }

    $savedItem = $savedItems[0];
    if ($savedItem->originalName !== 'hero.png') {
        return false;
    }

    if (!is_file($root . '/' . $savedItem->storagePath)) {
        return false;
    }

    return !is_file($imagePath) && str_starts_with($savedItem->publicPath, '/uploads/images/');
} finally {
    $_FILES = $previousFiles;

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    if (is_dir($root)) {
        $filesystem->deleteDirectoryRecursively($root);
    }
}

