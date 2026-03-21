<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\security\PasswordHasher;
use Glyph\adapters\security\SecretGenerator;
use Glyph\adapters\session\SessionManager;
use Glyph\adapters\storage\UserFileRepository;
use Glyph\domain\users\UserRecord;
use Glyph\services\auth\AuthenticationManager;

$root = sys_get_temp_dir() . '/glyph-password-reset-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root . '/users');
    $filesystem->ensureDirectoryExists($root . '/sessions');

    $passwordHasher = new PasswordHasher();
    $secretGenerator = new SecretGenerator();
    $userRepository = new UserFileRepository($filesystem, $root . '/users');

    $userRepository->save(new UserRecord(
        id: 'user_1',
        email: 'person@example.com',
        passwordHash: $passwordHasher->hash('oldpassword123'),
        role: 'editor',
        isActive: true,
        createdAt: gmdate('c'),
        updatedAt: gmdate('c'),
        lastLoginAt: null,
        rememberTokens: [],
        passwordResetTokens: [],
        displayName: 'Demo User',
    ));

    $sessionManager = new SessionManager([
        'session_name' => 'glyph_test_session',
        'session_cookie_name' => 'glyph_test_cookie',
        'remember_cookie_name' => 'glyph_test_remember',
        'cookie_lifetime' => 0,
        'session_lifetime_seconds' => 7200,
        'remember_lifetime' => 1209600,
        'remember_lifetime_seconds' => 1209600,
        'is_secure_cookie' => false,
        'is_http_only_cookie' => true,
        'session_cookie_same_site' => 'Lax',
    ], $root . '/sessions');
    $sessionManager->start();

    $auth = new AuthenticationManager(
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

    $token = $auth->issuePasswordResetToken('person@example.com');

    if (!is_string($token) || $token === '') {
        return false;
    }

    if (!$auth->isPasswordResetTokenValid($token)) {
        return false;
    }

    if (!$auth->resetPassword($token, 'newpassword123')) {
        return false;
    }

    if ($auth->isPasswordResetTokenValid($token)) {
        return false;
    }

    $updated = $userRepository->findByEmail('person@example.com');
    if ($updated === null) {
        return false;
    }

    if (!$passwordHasher->verify('newpassword123', $updated->passwordHash)) {
        return false;
    }

    return true;
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
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
