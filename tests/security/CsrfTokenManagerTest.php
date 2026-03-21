<?php

declare(strict_types=1);

use Glyph\adapters\security\CsrfTokenManager;
use Glyph\adapters\security\SecretGenerator;
use Glyph\adapters\session\SessionManager;

$sessionPath = sys_get_temp_dir() . '/glyph-session-' . bin2hex(random_bytes(4));

try {
    $sessionManager = new SessionManager(
        authConfig: [
            'session_cookie_name' => 'glyph_test_session',
            'session_lifetime_seconds' => 3600,
            'session_cookie_same_site' => 'Lax',
        ],
        sessionSavePath: $sessionPath,
    );

    $sessionManager->start();

    $manager = new CsrfTokenManager($sessionManager, new SecretGenerator());
    $token = $manager->token('test_form');

    if ($token === '') {
        return false;
    }

    if (!$manager->validate('test_form', $token)) {
        return false;
    }

    if ($manager->validate('test_form', 'bad-token')) {
        return false;
    }

    return true;
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    if (is_dir($sessionPath)) {
        $items = scandir($sessionPath);
        if (is_array($items)) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                @unlink($sessionPath . '/' . $item);
            }
        }

        @rmdir($sessionPath);
    }
}