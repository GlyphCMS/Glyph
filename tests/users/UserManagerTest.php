<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\security\PasswordHasher;
use Glyph\adapters\security\SecretGenerator;
use Glyph\adapters\storage\UserFileRepository;
use Glyph\domain\users\UserRecord;
use Glyph\services\users\UserFormInput;
use Glyph\services\users\UserManager;

$root = sys_get_temp_dir() . '/glyph-user-manager-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root);

    $repository = new UserFileRepository($filesystem, $root);
    $manager = new UserManager($repository, new PasswordHasher(), new SecretGenerator());

    $administrator = new UserRecord(
        id: 'admin1',
        email: 'admin@example.com',
        passwordHash: (new PasswordHasher())->hash('password123'),
        role: 'administrator',
        isActive: true,
        createdAt: gmdate('c'),
        updatedAt: gmdate('c'),
        lastLoginAt: null,
        rememberTokens: [],
        passwordResetTokens: [],
    );
    $repository->save($administrator);

    $invalid = $manager->validateForCreate(new UserFormInput('admin@example.com', 'Admin', 'author', true, 'short', 'nope'));
    if ($invalid->isValid()) {
        return false;
    }

    $created = $manager->create(new UserFormInput('author@example.com', 'Author', 'author', true, 'password123', 'password123'));
    if ($created->email !== 'author@example.com' || $created->role !== 'author') {
        return false;
    }

    $loaded = $repository->findById($created->id);
    if ($loaded === null || $loaded->role !== 'author') {
        return false;
    }

    $editValid = $manager->validateForEdit(
        new UserFormInput('admin@example.com', 'Admin', 'editor', false, '', ''),
        $administrator
    );
    if (!$editValid->isValid()) {
        return false;
    }

    $updated = $manager->update($loaded, new UserFormInput('editor@example.com', 'Editor Two', 'editor', true, 'newpassword123', 'newpassword123'));
    if ($updated->email !== 'editor@example.com' || $updated->role !== 'editor' || $updated->displayName !== 'Editor Two') {
        return false;
    }

    $fallbackUser = new UserRecord(
        id: 'reader1',
        email: 'ryan@example.com',
        passwordHash: (new PasswordHasher())->hash('password123'),
        role: 'reader',
        isActive: true,
        createdAt: gmdate('c'),
        updatedAt: gmdate('c'),
        lastLoginAt: null,
        rememberTokens: [],
        passwordResetTokens: [],
        displayName: '',
    );
    $repository->save($fallbackUser);

    $fallbackLoaded = $repository->findById('reader1');
    if ($fallbackLoaded === null || $fallbackLoaded->displayNameOrFallback() !== 'ryan') {
        return false;
    }

    $caseUpdated = $manager->update($fallbackLoaded, new UserFormInput('ryan@example.com', 'Ryan', 'reader', true, '', ''));
    if ($caseUpdated->displayName !== 'Ryan') {
        return false;
    }

    $reloadedCaseUpdated = $repository->findById('reader1');
    if ($reloadedCaseUpdated === null || $reloadedCaseUpdated->displayName !== 'Ryan' || $reloadedCaseUpdated->displayNameOrFallback() !== 'Ryan') {
        return false;
    }

    return true;
} finally {
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
