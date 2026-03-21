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

$root = sys_get_temp_dir() . '/glyph-content-featured-image-fallback-' . bin2hex(random_bytes(6));
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

    $csrfTokenManager = new CsrfTokenManager($sessionManager, $secretGenerator);
    $controller = new ContentController(
        $authenticationManager,
        $contentService,
        $csrfTokenManager,
        $autosaveService,
        $slugManager,
        [
            'timezone' => 'America/New_York',
            'date_format' => 'm/d/Y',
            'time_format' => 'g:i A',
        ],
    );

    $response = $controller->edit(new Request(
        method: 'POST',
        path: '/admin/content/edit',
        query: [],
        post: [
            '_csrf_token' => $csrfTokenManager->token('content_edit'),
            'type' => 'post',
            'id' => $record->id,
            'title' => 'Hello',
            'slug' => '/hello',
            'status' => 'published',
            'excerpt' => 'Excerpt',
            'body_html' => '<p>Hello</p>',
            'featured_image' => '/uploads/images/old.png',
            'seo_title' => '',
            'seo_description' => '',
            'menu_order' => '0',
        ],
        server: [],
        cookies: [],
    ));

    $headersProperty = new ReflectionProperty($response, 'headers');
    $headersProperty->setAccessible(true);
    $headers = $headersProperty->getValue($response);

    if (($headers['Location'] ?? null) === null) {
        return false;
    }

    $saved = $contentService->findById('post', $record->id);

    if ($saved === null || $saved->featuredImage !== '/uploads/images/new.png') {
        return false;
    }

    return $autosaveService->load('edit_' . $record->type . '_' . $record->id) === null;
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

