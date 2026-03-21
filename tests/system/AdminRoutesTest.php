<?php

declare(strict_types=1);

use Glyph\ui\admin\AdminController;
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

$router = new Router();
$registerRoutes = require dirname(__DIR__, 2) . '/bootstrap/routes.php';

$instantiate = static fn (string $className): object => (new ReflectionClass($className))->newInstanceWithoutConstructor();

$registerRoutes(
    $router,
    $instantiate(FrontendController::class),
    $instantiate(AuthController::class),
    $instantiate(AdminController::class),
    $instantiate(ContentController::class),
    $instantiate(MediaController::class),
    $instantiate(SettingsController::class),
    $instantiate(ThemeController::class),
    $instantiate(PluginController::class),
    $instantiate(PluginPageController::class),
    $instantiate(NavigationController::class),
    $instantiate(UserController::class),
    $instantiate(SystemController::class),
);

$routesProperty = new ReflectionProperty(Router::class, 'routes');
$routesProperty->setAccessible(true);
$routes = $routesProperty->getValue($router);

return isset($routes['POST']['/admin/system/migrations/run'])
    && isset($routes['POST']['/admin/media/upload/browser'])
    && isset($routes['POST']['/admin/media/delete']);
