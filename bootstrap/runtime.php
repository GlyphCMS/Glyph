<?php

declare(strict_types=1);

use Glyph\domain\shared\AppPaths;
use Glyph\adapters\http\Request;
use Glyph\adapters\http\Response;

function glyphMaintenanceResponse(AppPaths $paths, Request $request): ?Response
{
    $maintenanceConfigPath = $paths->get('data_system') . '/maintenance.php';
    $maintenanceConfig = is_file($maintenanceConfigPath)
        ? require $maintenanceConfigPath
        : ['enabled' => false, 'message' => 'Glyph is currently undergoing maintenance. Please check back soon.'];

    if (
        !is_array($maintenanceConfig)
        || (($maintenanceConfig['enabled'] ?? false) !== true)
        || pathIsMaintenanceExempt($request->path())
    ) {
        return null;
    }

    $message = is_string($maintenanceConfig['message'] ?? null) && $maintenanceConfig['message'] !== ''
        ? $maintenanceConfig['message']
        : 'Glyph is currently undergoing maintenance. Please check back soon.';

    return Response::html(
        '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Maintenance</title><link rel="stylesheet" href="/assets/glyph.css"></head><body class="theme-auth"><main class="centered-shell"><div class="auth-card stack"><section class="hero"><p class="hero__eyebrow">Maintenance</p><h1 class="hero__title">We\'ll be back soon.</h1><p class="hero__text">' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p></section></div></main></body></html>',
        503,
        ['Retry-After' => '300'],
    );
}

function pathIsMaintenanceExempt(string $path): bool
{
    $prefixes = [
        '/admin',
        '/login',
        '/logout',
        '/forgot-password',
        '/reset-password',
        '/install',
        '/assets',
        '/themes',
        '/uploads',
    ];

    foreach ($prefixes as $prefix) {
        if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
            return true;
        }
    }

    return false;
}
