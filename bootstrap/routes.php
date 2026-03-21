<?php

declare(strict_types=1);

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
use Glyph\ui\http\Router;

return static function (
    Router $router,
    FrontendController $frontendController,
    AuthController $authController,
    AdminController $adminController,
    CategoryController $categoryController,
    ContentController $contentController,
    MediaController $mediaController,
    SettingsController $settingsController,
    ThemeController $themeController,
    PluginController $pluginController,
    PluginPageController $pluginPageController,
    NavigationController $navigationController,
    UserController $userController,
    SystemController $systemController,
): void {
    $router->get('/', [$frontendController, 'home']);
    $router->get('/health', [$frontendController, 'health']);
    $router->get('/categories', [$frontendController, 'categories']);
    $router->get('/search', [$frontendController, 'search']);

    $router->get('/login', [$authController, 'showLogin']);
    $router->post('/login', [$authController, 'login']);
    $router->get('/forgot-password', [$authController, 'showForgotPassword']);
    $router->post('/forgot-password', [$authController, 'forgotPassword']);
    $router->get('/reset-password', [$authController, 'showResetPassword']);
    $router->post('/reset-password', [$authController, 'resetPassword']);
    $router->get('/logout', [$authController, 'logout']);

    $router->get('/admin', [$adminController, 'dashboard']);
    $router->get('/admin/categories', [$categoryController, 'index']);
    $router->get('/admin/categories/create', [$categoryController, 'showCreate']);
    $router->get('/admin/categories/edit', [$categoryController, 'showEdit']);
    $router->post('/admin/categories/save', [$categoryController, 'save']);
    $router->post('/admin/categories/delete', [$categoryController, 'delete']);
    $router->get('/admin/content', [$contentController, 'index']);
    $router->get('/admin/content/create', [$contentController, 'showCreate']);
    $router->post('/admin/content/create', [$contentController, 'create']);
    $router->get('/admin/content/edit', [$contentController, 'showEdit']);
    $router->post('/admin/content/edit', [$contentController, 'edit']);
    $router->post('/admin/content/delete', [$contentController, 'delete']);
    $router->post('/admin/content/autosave', [$contentController, 'autosave']);
    $router->post('/admin/content/autosave/discard', [$contentController, 'discardAutosave']);
    $router->get('/admin/media', [$mediaController, 'index']);
    $router->get('/admin/media/browser', [$mediaController, 'browser']);
    $router->post('/admin/media/upload', [$mediaController, 'upload']);
    $router->post('/admin/media/upload/browser', [$mediaController, 'uploadBrowser']);
    $router->post('/admin/media/delete', [$mediaController, 'delete']);
    $router->get('/admin/settings', [$settingsController, 'show']);
    $router->get('/admin/system', [$systemController, 'show']);
    $router->post('/admin/system/maintenance', [$systemController, 'saveMaintenance']);
    $router->post('/admin/system/update-settings', [$systemController, 'saveUpdateSettings']);
    $router->post('/admin/system/update-check', [$systemController, 'checkForUpdates']);
    $router->post('/admin/system/update-package-validate', [$systemController, 'validateUpdatePackage']);
    $router->post('/admin/system/update-package-apply', [$systemController, 'applyUpdatePackage']);
    $router->post('/admin/system/migrations/run', [$systemController, 'runMigrations']);
    $router->post('/admin/system/backup', [$systemController, 'backup']);
    $router->get('/admin/users', [$userController, 'index']);
    $router->get('/admin/users/create', [$userController, 'showCreate']);
    $router->post('/admin/users/create', [$userController, 'create']);
    $router->get('/admin/users/edit', [$userController, 'showEdit']);
    $router->post('/admin/users/edit', [$userController, 'edit']);
    $router->get('/admin/navigation', [$navigationController, 'show']);
    $router->post('/admin/navigation', [$navigationController, 'save']);
    $router->post('/admin/settings', [$settingsController, 'save']);
    $router->post('/admin/settings/test-email', [$settingsController, 'sendTestEmail']);
    $router->get('/admin/themes', [$themeController, 'index']);
    $router->post('/admin/themes/install', [$themeController, 'install']);
    $router->post('/admin/themes/activate', [$themeController, 'activate']);
    $router->post('/admin/themes/delete', [$themeController, 'delete']);
    $router->get('/admin/plugins', [$pluginController, 'index']);
    $router->post('/admin/plugins/install', [$pluginController, 'install']);
    $router->post('/admin/plugins/toggle', [$pluginController, 'toggle']);
    $router->post('/admin/plugins/delete', [$pluginController, 'delete']);
    $router->get('/admin/plugin-page', [$pluginPageController, 'render']);
    $router->post('/admin/plugin-page', [$pluginPageController, 'render']);

    $router->fallback([$frontendController, 'fallback']);
};

