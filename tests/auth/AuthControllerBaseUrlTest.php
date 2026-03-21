<?php

declare(strict_types=1);

use Glyph\ui\auth\AuthController;

$reflection = new ReflectionClass(AuthController::class);
$controller = $reflection->newInstanceWithoutConstructor();

$siteConfigProperty = $reflection->getProperty('siteConfig');
$siteConfigProperty->setAccessible(true);
$siteConfigProperty->setValue($controller, [
    'site_url' => 'https://example.com/',
]);

$baseUrlMethod = $reflection->getMethod('baseUrl');
$baseUrlMethod->setAccessible(true);
$configuredBaseUrl = $baseUrlMethod->invoke($controller);

if ($configuredBaseUrl !== 'https://example.com') {
    return false;
}

$controller = $reflection->newInstanceWithoutConstructor();
$siteConfigProperty->setValue($controller, [
    'site_url' => '',
]);

$_SERVER['HTTP_HOST'] = 'attacker.example';
$fallbackBaseUrl = $baseUrlMethod->invoke($controller);
unset($_SERVER['HTTP_HOST']);

return $fallbackBaseUrl === 'http://localhost';

