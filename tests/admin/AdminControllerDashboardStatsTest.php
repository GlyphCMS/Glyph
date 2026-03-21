<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\http\Request;
use Glyph\adapters\security\HtmlSanitizer;
use Glyph\adapters\security\PasswordHasher;
use Glyph\adapters\security\SecretGenerator;
use Glyph\adapters\session\SessionManager;
use Glyph\adapters\storage\ContentFileRepository;
use Glyph\adapters\storage\RedirectFileRepository;
use Glyph\adapters\storage\UserFileRepository;
use Glyph\domain\users\UserRecord;
use Glyph\services\auth\AuthenticationManager;
use Glyph\services\content\ContentInput;
use Glyph\services\content\ContentService;
use Glyph\services\content\ContentValidator;
use Glyph\services\content\SlugManager;
use Glyph\services\plugins\PluginResolver;
use Glyph\services\system\SystemInfoService;
use Glyph\services\themes\ThemeResolver;
use Glyph\ui\admin\AdminController;

$root = sys_get_temp_dir() . '/glyph-admin-dashboard-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    foreach ([
        '/content/posts',
        '/content/pages',
        '/data/redirects',
        '/data/users',
        '/data/sessions',
        '/data',
        '/storage',
        '/uploads',
        '/themes/default',
        '/plugins/seo',
        '/config',
    ] as $suffix) {
        $filesystem->ensureDirectoryExists($root . $suffix);
    }

    $filesystem->writeFile($root . '/themes/default/theme.json', json_encode([
        'name' => 'Default',
        'version' => '1.0.0',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

    $filesystem->writeFile($root . '/plugins/seo/plugin.json', json_encode([
        'name' => 'SEO',
        'version' => '1.0.0',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

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
        displayName: 'Ryan',
    ));
    $userRepository->save(new UserRecord(
        id: 'editor_1',
        email: 'editor@example.com',
        passwordHash: $passwordHasher->hash('password123'),
        role: 'editor',
        isActive: false,
        createdAt: gmdate('c'),
        updatedAt: gmdate('c'),
        lastLoginAt: null,
        rememberTokens: [],
        passwordResetTokens: [],
        displayName: 'Editor',
    ));

    $sessionManager = new SessionManager(
        authConfig: [
            'session_cookie_name' => 'glyph_admin_dashboard_session',
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

    $contentService->create(new ContentInput(
        type: 'post',
        title: 'Published post',
        slug: '/published-post',
        status: 'published',
        excerpt: 'Excerpt',
        bodyHtml: '<p>Post</p>',
        featuredImage: null,
        parentId: null,
        seoTitle: '',
        seoDescription: '',
    ), 'owner_1');

    $contentService->create(new ContentInput(
        type: 'post',
        title: 'Draft post',
        slug: '/draft-post',
        status: 'draft',
        excerpt: 'Excerpt',
        bodyHtml: '<p>Draft post</p>',
        featuredImage: null,
        parentId: null,
        seoTitle: '',
        seoDescription: '',
    ), 'owner_1');

    $contentService->create(new ContentInput(
        type: 'page',
        title: 'Published page',
        slug: '/published-page',
        status: 'published',
        excerpt: 'Excerpt',
        bodyHtml: '<p>Page</p>',
        featuredImage: null,
        parentId: null,
        seoTitle: '',
        seoDescription: '',
    ), 'owner_1');

    $contentService->create(new ContentInput(
        type: 'page',
        title: 'Draft page',
        slug: '/draft-page',
        status: 'draft',
        excerpt: 'Excerpt',
        bodyHtml: '<p>Draft page</p>',
        featuredImage: null,
        parentId: null,
        seoTitle: '',
        seoDescription: '',
    ), 'owner_1');

    $systemInfoService = new SystemInfoService(
        filesystem: $filesystem,
        contentService: $contentService,
        userRepository: $userRepository,
        themeResolver: new ThemeResolver($filesystem, $root . '/themes', 'default'),
        pluginResolver: new PluginResolver($filesystem, $root . '/plugins', ['enabled' => ['seo']]),
        appConfig: [
            'version' => '0.1.0-dev',
            'environment' => 'production',
        ],
        siteConfig: [
            'site_name' => 'Glyph Demo',
            'active_theme' => 'default',
            'timezone' => 'America/New_York',
        ],
        paths: [
            'content' => $root . '/content',
            'data' => $root . '/data',
            'uploads' => $root . '/uploads',
            'themes' => $root . '/themes',
            'plugins' => $root . '/plugins',
            'storage' => $root . '/storage',
            'config' => $root . '/config',
        ],
    );

    $controller = new AdminController($authenticationManager, $systemInfoService, null);

    $response = $controller->dashboard(new Request(
        method: 'GET',
        path: '/admin',
        query: [],
        post: [],
        server: [],
        cookies: [],
    ));

    $bodyProperty = new ReflectionProperty($response, 'body');
    $bodyProperty->setAccessible(true);
    $body = $bodyProperty->getValue($response);

    if (!is_string($body)) {
        return false;
    }

    return str_contains($body, 'Site Overview')
        && str_contains($body, 'At a glance')
        && !str_contains($body, 'Workspace')
        && !str_contains($body, 'Site Name')
        && !str_contains($body, 'Active Theme')
        && !str_contains($body, 'Timezone')
        && !str_contains($body, 'admin-metric-card__label">Published<')
        && !str_contains($body, 'admin-metric-card__label">Drafts<')
        && str_contains($body, 'admin-metric-card__label">Posts<')
        && str_contains($body, 'admin-metric-card__label">Pages<')
        && str_contains($body, 'admin-metric-card__label">Users<')
        && str_contains($body, 'admin-metric-card__label">Plugins<')
        && str_contains($body, '1 draft')
        && str_contains($body, '1 active')
        && str_contains($body, '1 enabled')
        && str_contains($body, 'Storage Free');
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
