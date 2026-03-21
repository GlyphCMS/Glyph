<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\http\Request;
use Glyph\adapters\security\CsrfTokenManager;
use Glyph\adapters\security\HtmlSanitizer;
use Glyph\adapters\security\PasswordHasher;
use Glyph\adapters\security\SecretGenerator;
use Glyph\adapters\session\SessionManager;
use Glyph\adapters\storage\ContentFileRepository;
use Glyph\adapters\storage\RedirectFileRepository;
use Glyph\adapters\storage\UserFileRepository;
use Glyph\domain\users\UserRecord;
use Glyph\services\auth\AuthenticationManager;
use Glyph\services\content\ContentAutosaveService;
use Glyph\services\content\ContentInput;
use Glyph\services\content\ContentService;
use Glyph\services\content\ContentValidator;
use Glyph\services\content\SlugManager;
use Glyph\ui\admin\ContentController;

$root = sys_get_temp_dir() . '/glyph-content-controller-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $filesystem->ensureDirectoryExists($root . '/content/posts');
    $filesystem->ensureDirectoryExists($root . '/content/pages');
    $filesystem->ensureDirectoryExists($root . '/data/redirects');
    $filesystem->ensureDirectoryExists($root . '/data/autosave');
    $filesystem->ensureDirectoryExists($root . '/data/users');
    $filesystem->ensureDirectoryExists($root . '/data/sessions');

    $passwordHasher = new PasswordHasher();
    $secretGenerator = new SecretGenerator();
    $slugManager = new SlugManager();

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
            'session_cookie_name' => 'glyph_test_session',
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
            'remember_cookie_name' => 'glyph_test_remember',
            'remember_lifetime_seconds' => 1209600,
            'password_reset_lifetime_seconds' => 3600,
            'session_cookie_same_site' => 'Lax',
        ],
    );

    $contentService = new ContentService(
        contentRepository: new ContentFileRepository(
            filesystem: $filesystem,
            postsPath: $root . '/content/posts',
            pagesPath: $root . '/content/pages',
        ),
        redirectRepository: new RedirectFileRepository(
            filesystem: $filesystem,
            redirectFilePath: $root . '/data/redirects/redirects.json',
        ),
        validator: new ContentValidator($slugManager),
        slugManager: $slugManager,
        secretGenerator: $secretGenerator,
        htmlSanitizer: new HtmlSanitizer(),
    );

    $record = $contentService->create(
        new ContentInput(
            type: 'post',
            title: 'Hello',
            slug: '/hello',
            status: 'published',
            excerpt: 'Excerpt',
            bodyHtml: '<p>Hello</p>',
            featuredImage: '/uploads/images/old.png',
            parentId: null,
            seoTitle: '',
            seoDescription: '',
        ),
        'owner_1',
    );

    $autosaveService = new ContentAutosaveService($filesystem, $root . '/data/autosave');
    $autosaveService->save(
        'edit_' . $record->type . '_' . $record->id,
        new ContentInput(
            type: 'post',
            title: 'Hello',
            slug: '/hello',
            status: 'published',
            excerpt: 'Excerpt',
            bodyHtml: '<p>Hello</p>',
            featuredImage: '/uploads/images/new.png',
            parentId: null,
            seoTitle: '',
            seoDescription: '',
        ),
    );

    $controller = new ContentController(
        $authenticationManager,
        $contentService,
        new CsrfTokenManager($sessionManager, $secretGenerator),
        $autosaveService,
        $slugManager,
        [
            'timezone' => 'America/New_York',
            'date_format' => 'm/d/Y',
            'time_format' => 'g:i A',
        ],
    );

    $response = $controller->showEdit(new Request(
        method: 'GET',
        path: '/admin/content/edit',
        query: [
            'type' => 'post',
            'id' => $record->id,
            'saved' => '1',
        ],
        post: [],
        server: [],
        cookies: [],
    ));

    $bodyProperty = new ReflectionProperty($response, 'body');
    $bodyProperty->setAccessible(true);
    $body = $bodyProperty->getValue($response);

    if (!is_string($body) || !str_contains($body, '/uploads/images/old.png')) {
        return false;
    }

    if (str_contains($body, '/uploads/images/new.png')) {
        return false;
    }

    if (str_contains($body, 'Autosave restored.')) {
        return false;
    }

    if ($autosaveService->load('edit_' . $record->type . '_' . $record->id) !== null) {
        return false;
    }

    return true;
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($root);
    }
}


