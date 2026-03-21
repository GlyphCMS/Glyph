<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\filesystem\UploadedFileInspector;
use Glyph\adapters\filesystem\UploadedZipArchive;
use Glyph\adapters\filesystem\UploadedZipInspector;
use Glyph\adapters\http\Request;
use Glyph\adapters\security\CsrfTokenManager;
use Glyph\adapters\security\HtmlSanitizer;
use Glyph\adapters\security\PasswordHasher;
use Glyph\adapters\security\SecretGenerator;
use Glyph\adapters\session\SessionManager;
use Glyph\adapters\storage\CategoryFileRepository;
use Glyph\adapters\storage\ContentFileRepository;
use Glyph\adapters\storage\MediaFileRepository;
use Glyph\adapters\storage\PhpConfigWriter;
use Glyph\adapters\storage\RedirectFileRepository;
use Glyph\adapters\storage\UserFileRepository;
use Glyph\domain\shared\AppPaths;
use Glyph\services\auth\AuthenticationManager;
use Glyph\services\auth\LoginThrottle;
use Glyph\services\categories\CategoryService;
use Glyph\services\content\ContentAutosaveService;
use Glyph\services\content\ContentService;
use Glyph\services\content\ContentValidator;
use Glyph\services\content\SlugManager;
use Glyph\services\install\InstallState;
use Glyph\services\media\MediaService;
use Glyph\services\media\MediaValidator;
use Glyph\services\navigation\NavigationManager;
use Glyph\services\plugins\HookManager;
use Glyph\services\plugins\PluginAdminService;
use Glyph\services\plugins\PluginLoader;
use Glyph\services\plugins\PluginPackageInstaller;
use Glyph\services\plugins\PluginResolver;
use Glyph\services\plugins\PluginSettingsStore;
use Glyph\services\settings\SettingsManager;
use Glyph\services\settings\SettingsValidator;
use Glyph\services\system\MaintenanceManager;
use Glyph\services\system\MigrationManager;
use Glyph\services\system\SystemBackupManager;
use Glyph\services\system\SystemInfoService;
use Glyph\services\system\UpdateApplyService;
use Glyph\services\system\UpdateManager;
use Glyph\services\system\UpdateManifestFetcher;
use Glyph\services\system\UpdatePackageValidator;
use Glyph\services\system\UpdatePreflightChecker;
use Glyph\services\system\UpdateService;
use Glyph\services\system\VersionStateManager;
use Glyph\services\themes\ThemeAdminService;
use Glyph\services\themes\ThemePackageInstaller;
use Glyph\services\themes\ThemeRenderer;
use Glyph\services\themes\ThemeResolver;
use Glyph\services\users\UserManager;
use Glyph\ui\admin\AdminController;
use Glyph\ui\admin\CategoryController;
use Glyph\ui\admin\ContentController;
use Glyph\ui\admin\MediaController;
use Glyph\ui\admin\NavigationController;
use Glyph\ui\admin\PluginController;
use Glyph\ui\admin\PluginPageController;
use Glyph\ui\admin\SettingsController;
use Glyph\ui\admin\SystemController;
use Glyph\ui\admin\ThemeController;
use Glyph\ui\admin\UserController;
use Glyph\ui\auth\AuthController;
use Glyph\ui\frontend\FrontendController;
use Glyph\ui\shared\DocumentRenderer;

/**
 * @param array<string, mixed> $config
 * @return array{
 *   frontend: FrontendController,
 *   auth: AuthController,
 *   admin: AdminController,
 *   category: CategoryController,
 *   content: ContentController,
 *   media: MediaController,
 *   settings: SettingsController,
 *   theme: ThemeController,
 *   plugin: PluginController,
 *   pluginPage: PluginPageController,
 *   navigation: NavigationController,
 *   user: UserController,
 *   system: SystemController
 * }
 */
function glyphBuildControllers(array $config, AppPaths $paths, Request $request, LocalFilesystem $filesystem, InstallState $installState): array
{
    $configWriter = new PhpConfigWriter($filesystem);
    $passwordHasher = new PasswordHasher();
    $secretGenerator = new SecretGenerator();
    $uploadedZipArchive = new UploadedZipArchive($filesystem, new UploadedZipInspector());

    $sessionManager = new SessionManager(
        authConfig: $config['auth'],
        sessionSavePath: $paths->get('data_sessions'),
    );
    $sessionManager->start();

    $csrfTokenManager = new CsrfTokenManager($sessionManager, $secretGenerator);
    $hookManager = new HookManager();

    $userRepository = new UserFileRepository(
        filesystem: $filesystem,
        usersPath: $paths->get('data_users'),
    );

    $authenticationManager = new AuthenticationManager(
        sessionManager: $sessionManager,
        userRepository: $userRepository,
        passwordHasher: $passwordHasher,
        secretGenerator: $secretGenerator,
        authConfig: $config['auth'],
    );

    $authenticationManager->restoreFromRememberCookie(
        $request->cookie($config['auth']['remember_cookie_name'] ?? '', null)
    );

    $loginThrottle = new LoginThrottle(
        filesystem: $filesystem,
        throttlePath: $paths->get('data_cache'),
        maxAttempts: $config['auth']['login_rate_limit_max_attempts'],
        windowSeconds: $config['auth']['login_rate_limit_window_seconds'],
        scope: 'login',
    );

    $forgotPasswordThrottle = new LoginThrottle(
        filesystem: $filesystem,
        throttlePath: $paths->get('data_cache'),
        maxAttempts: 5,
        windowSeconds: 900,
        scope: 'forgot-password',
    );

    $pluginResolver = new PluginResolver(
        filesystem: $filesystem,
        pluginsPath: $paths->get('plugins'),
        pluginsConfig: $config['plugins'],
    );

    $pluginSettingsStore = new PluginSettingsStore(
        configWriter: $configWriter,
        settingsPath: $paths->get('data_system') . '/plugin-settings.php',
    );

    $pluginLoader = new PluginLoader(
        pluginResolver: $pluginResolver,
        hookManager: $hookManager,
        pluginSettingsStore: $pluginSettingsStore,
        csrfTokenManager: $csrfTokenManager,
    );
    $pluginLoader->loadEnabledPlugins();

    $slugManager = new SlugManager();
    $contentAutosaveService = new ContentAutosaveService(
        filesystem: $filesystem,
        autosavePath: $paths->get('data_system') . '/editor-autosave',
    );
    $contentRepository = new ContentFileRepository(
        filesystem: $filesystem,
        postsPath: $paths->get('content_posts'),
        pagesPath: $paths->get('content_pages'),
    );
    $redirectRepository = new RedirectFileRepository(
        filesystem: $filesystem,
        redirectFilePath: $paths->get('data_redirects') . '/redirects.json',
    );
    $categoryService = new CategoryService(
        categoryRepository: new CategoryFileRepository(
            filesystem: $filesystem,
            filePath: $paths->get('data_categories') . '/categories.json',
        ),
        slugManager: $slugManager,
        secretGenerator: $secretGenerator,
        contentRepository: $contentRepository,
        redirectRepository: $redirectRepository,
    );

    $contentService = new ContentService(
        contentRepository: $contentRepository,
        redirectRepository: $redirectRepository,
        validator: new ContentValidator($slugManager, $categoryService),
        slugManager: $slugManager,
        secretGenerator: $secretGenerator,
        htmlSanitizer: new HtmlSanitizer(),
        categoryService: $categoryService,
        sanitizeContentHtml: (bool) ($config['site']['sanitize_content_html'] ?? false),
    );

    $mediaValidator = new MediaValidator(
        allowedExtensions: $config['media']['allowed_extensions'],
        allowedMimeTypes: $config['media']['allowed_mime_types'],
        maxUploadBytes: $config['media']['max_upload_bytes'],
    );

    $mediaService = new MediaService(
        filesystem: $filesystem,
        uploadedFileInspector: new UploadedFileInspector(),
        mediaValidator: $mediaValidator,
        mediaRepository: new MediaFileRepository(
            filesystem: $filesystem,
            mediaPath: $paths->get('data_media'),
        ),
        secretGenerator: $secretGenerator,
        uploadsImagesPath: $paths->get('uploads_images'),
    );

    $settingsManager = new SettingsManager(
        configWriter: $configWriter,
        systemPath: $paths->get('data_system'),
    );

    $settingsValidator = new SettingsValidator();

    $versionStateManager = new VersionStateManager(
        configWriter: $configWriter,
        statePath: $paths->get('data_system') . '/version.php',
    );

    $migrationManager = new MigrationManager(
        filesystem: $filesystem,
        configWriter: $configWriter,
        versionStateManager: $versionStateManager,
        appConfig: $config['app'],
        versioningConfig: $config['versioning'],
        paths: glyphMigrationPaths($paths),
    );

    if ($installState->isInstalled() && $migrationManager->autoRunEnabled()) {
        $migrationManager->runPending();
    }

    $maintenanceManager = new MaintenanceManager(
        configWriter: $configWriter,
        systemPath: $paths->get('data_system'),
    );

    $systemBackupManager = new SystemBackupManager(
        filesystem: $filesystem,
        rootPath: $paths->root(),
        backupPath: $paths->get('storage') . '/backups',
        paths: glyphBackupPaths($paths),
    );

    $navigationManager = new NavigationManager(
        configWriter: $configWriter,
        systemPath: $paths->get('data_system'),
        navigationConfig: $config['navigation'],
    );

    $themeResolver = new ThemeResolver(
        filesystem: $filesystem,
        themesPath: $paths->get('themes'),
        defaultTheme: 'default',
    );

    $systemInfoService = new SystemInfoService(
        filesystem: $filesystem,
        contentService: $contentService,
        userRepository: $userRepository,
        themeResolver: $themeResolver,
        pluginResolver: $pluginResolver,
        appConfig: $config['app'],
        siteConfig: $config['site'],
        paths: glyphSystemInfoPaths($paths),
    );

    $updateManager = new UpdateManager(
        configWriter: $configWriter,
        systemPath: $paths->get('data_system'),
    );

    $updatePreflightChecker = new UpdatePreflightChecker(
        filesystem: $filesystem,
        paths: glyphUpdatePreflightPaths($paths),
    );

    $updateService = new UpdateService(
        manifestFetcher: new UpdateManifestFetcher(),
        preflightChecker: $updatePreflightChecker,
        appConfig: $config['app'],
    );

    $updatePackageValidator = new UpdatePackageValidator($uploadedZipArchive);

    $updateApplyService = new UpdateApplyService(
        filesystem: $filesystem,
        uploadedZipArchive: $uploadedZipArchive,
        systemBackupManager: $systemBackupManager,
        installRootPath: dirname(__DIR__),
        systemPath: $paths->get('data_system'),
    );

    DocumentRenderer::setAdminBrandingContext(is_string($config['site']['site_logo'] ?? null) ? (string) ($config['site']['site_logo'] ?? '') : '');

    $documentRenderer = new DocumentRenderer($hookManager);

    $themeRenderer = new ThemeRenderer(
        siteConfig: $config['site'],
        themeResolver: $themeResolver,
        documentRenderer: $documentRenderer,
        contentService: $contentService,
        navigationManager: $navigationManager,
        hookManager: $hookManager,
        categoryService: $categoryService,
    );

    $themePackageInstaller = new ThemePackageInstaller(
        filesystem: $filesystem,
        uploadedZipArchive: $uploadedZipArchive,
        themesPath: $paths->get('themes'),
    );

    $themeAdminService = new ThemeAdminService(
        filesystem: $filesystem,
        configWriter: $configWriter,
        themeResolver: $themeResolver,
        systemPath: $paths->get('data_system'),
        themesPath: $paths->get('themes'),
        siteConfig: $config['site'],
        defaultTheme: 'default',
    );

    $userManager = new UserManager(
        userRepository: $userRepository,
        passwordHasher: $passwordHasher,
        secretGenerator: $secretGenerator,
    );

    $pluginAdminService = new PluginAdminService(
        filesystem: $filesystem,
        configWriter: $configWriter,
        pluginResolver: $pluginResolver,
        pluginSettingsStore: $pluginSettingsStore,
        systemPath: $paths->get('data_system'),
        pluginsConfig: $config['plugins'],
    );

    $pluginPackageInstaller = new PluginPackageInstaller(
        filesystem: $filesystem,
        uploadedZipArchive: $uploadedZipArchive,
        pluginsPath: $paths->get('plugins'),
    );

    return [
        'frontend' => new FrontendController($contentService, $themeRenderer, $config['site'], $categoryService),
        'auth' => new AuthController(
            authenticationManager: $authenticationManager,
            loginThrottle: $loginThrottle,
            forgotPasswordThrottle: $forgotPasswordThrottle,
            csrfTokenManager: $csrfTokenManager,
            mailConfig: $config['mail'],
            siteConfig: $config['site'],
        ),
        'admin' => new AdminController($authenticationManager, $systemInfoService, $hookManager),
        'category' => new CategoryController($authenticationManager, $categoryService, $csrfTokenManager),
        'content' => new ContentController($authenticationManager, $contentService, $csrfTokenManager, $contentAutosaveService, $slugManager, $config['site'], $categoryService),
        'media' => new MediaController($authenticationManager, $mediaService, $csrfTokenManager, $config['site']),
        'settings' => new SettingsController(
            authenticationManager: $authenticationManager,
            csrfTokenManager: $csrfTokenManager,
            settingsManager: $settingsManager,
            settingsValidator: $settingsValidator,
            themeResolver: $themeResolver,
            contentService: $contentService,
            siteConfig: $config['site'],
            generatedConfig: $config['generated'],
            cacheConfig: $config['cache'],
            mailConfig: $config['mail'],
        ),
        'theme' => new ThemeController(
            authenticationManager: $authenticationManager,
            csrfTokenManager: $csrfTokenManager,
            themeResolver: $themeResolver,
            themePackageInstaller: $themePackageInstaller,
            themeAdminService: $themeAdminService,
            siteConfig: $config['site'],
        ),
        'plugin' => new PluginController(
            authenticationManager: $authenticationManager,
            csrfTokenManager: $csrfTokenManager,
            pluginResolver: $pluginResolver,
            pluginAdminService: $pluginAdminService,
            pluginPackageInstaller: $pluginPackageInstaller,
            hookManager: $hookManager,
        ),
        'pluginPage' => new PluginPageController(
            authenticationManager: $authenticationManager,
            hookManager: $hookManager,
        ),
        'navigation' => new NavigationController(
            authenticationManager: $authenticationManager,
            csrfTokenManager: $csrfTokenManager,
            navigationManager: $navigationManager,
            contentService: $contentService,
        ),
        'user' => new UserController(
            authenticationManager: $authenticationManager,
            csrfTokenManager: $csrfTokenManager,
            userManager: $userManager,
            siteConfig: $config['site'],
        ),
        'system' => new SystemController(
            authenticationManager: $authenticationManager,
            csrfTokenManager: $csrfTokenManager,
            maintenanceManager: $maintenanceManager,
            systemBackupManager: $systemBackupManager,
            systemInfoService: $systemInfoService,
            updateManager: $updateManager,
            updateService: $updateService,
            updatePackageValidator: $updatePackageValidator,
            updateApplyService: $updateApplyService,
            migrationManager: $migrationManager,
            maintenanceConfig: $config['maintenance'],
            updaterConfig: $config['updater'],
        ),
    ];
}

function glyphMigrationPaths(AppPaths $paths): array
{
    return [
        'data_cache' => $paths->get('data_cache'),
        'data_categories' => $paths->get('data_categories'),
        'data_indexes' => $paths->get('data_indexes'),
        'data_media' => $paths->get('data_media'),
        'data_redirects' => $paths->get('data_redirects'),
        'data_sessions' => $paths->get('data_sessions'),
        'data_system' => $paths->get('data_system'),
        'data_users' => $paths->get('data_users'),
        'storage' => $paths->get('storage'),
        'storage_logs' => $paths->get('storage_logs'),
    ];
}

function glyphBackupPaths(AppPaths $paths): array
{
    return [
        'content' => $paths->get('content'),
        'data' => $paths->get('data'),
        'uploads' => $paths->get('uploads'),
        'themes' => $paths->get('themes'),
        'plugins' => $paths->get('plugins'),
        'config' => $paths->get('config'),
    ];
}

function glyphSystemInfoPaths(AppPaths $paths): array
{
    return [
        'content' => $paths->get('content'),
        'data' => $paths->get('data'),
        'uploads' => $paths->get('uploads'),
        'themes' => $paths->get('themes'),
        'plugins' => $paths->get('plugins'),
        'storage' => $paths->get('storage'),
        'config' => $paths->get('config'),
    ];
}

function glyphUpdatePreflightPaths(AppPaths $paths): array
{
    return [
        'root' => $paths->root(),
        'content' => $paths->get('content'),
        'data_system' => $paths->get('data_system'),
        'themes' => $paths->get('themes'),
        'plugins' => $paths->get('plugins'),
        'storage' => $paths->get('storage'),
    ];
}

