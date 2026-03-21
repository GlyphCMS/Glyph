<?php

declare(strict_types=1);

use Glyph\domain\users\UserRecord;
use Glyph\ui\admin\UserPageRenderer;
use Glyph\ui\shared\DateTimeFormatter;

$renderer = new UserPageRenderer(new DateTimeFormatter('m/d/Y', 'g:i A', 'America/New_York'));
$timestamp = '2026-03-10T22:47:52+00:00';
$formattedDateTime = '03/10/2026 6:47 PM';

$html = $renderer->renderList([
    new UserRecord(
        id: 'user_1',
        email: 'ryan@example.com',
        passwordHash: 'hash',
        role: 'owner',
        isActive: true,
        createdAt: $timestamp,
        updatedAt: $timestamp,
        lastLoginAt: $timestamp,
        rememberTokens: [],
        passwordResetTokens: [],
        displayName: 'Ryan',
    ),
]);

return str_contains($html, $formattedDateTime)
    && !str_contains($html, $timestamp)
    && str_contains($html, 'button button-secondary button--compact')
    && str_contains($html, '>Ryan<');
