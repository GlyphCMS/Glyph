<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\http\Request;
use Glyph\adapters\http\Response;
use Glyph\services\install\InstallState;
use Glyph\ui\auth\AuthController;
use Glyph\ui\frontend\FrontendController;
use Glyph\ui\http\Router;
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

$bootstrap = require __DIR__ . '/config.php';
require __DIR__ . '/errors.php';
require __DIR__ . '/runtime.php';
require __DIR__ . '/container.php';

$config = $bootstrap['config'];
$paths = $bootstrap['paths'];

$filesystem = new LocalFilesystem();
$installState = new InstallState($config['generated'], $paths, $filesystem);
$request = Request::fromGlobals();

if (!$installState->isInstalled() && !str_starts_with($request->path(), '/install')) {
    return Response::redirect('/install/');
}

$maintenanceResponse = glyphMaintenanceResponse($paths, $request);
if ($maintenanceResponse !== null) {
    return $maintenanceResponse;
}

$controllers = glyphBuildControllers($config, $paths, $request, $filesystem, $installState);
$router = new Router();

/** @var callable(Router, FrontendController, AuthController, AdminController, CategoryController, ContentController, MediaController, SettingsController, ThemeController, PluginController, PluginPageController, NavigationController, UserController, SystemController): void $registerRoutes */
$registerRoutes = require __DIR__ . '/routes.php';
$registerRoutes(
    $router,
    $controllers['frontend'],
    $controllers['auth'],
    $controllers['admin'],
    $controllers['category'],
    $controllers['content'],
    $controllers['media'],
    $controllers['settings'],
    $controllers['theme'],
    $controllers['plugin'],
    $controllers['pluginPage'],
    $controllers['navigation'],
    $controllers['user'],
    $controllers['system'],
);

return $router->dispatch($request);

