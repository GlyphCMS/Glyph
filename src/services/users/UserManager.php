<?php

declare(strict_types=1);

namespace Glyph\services\users;

use Glyph\adapters\security\PasswordHasher;
use Glyph\adapters\security\SecretGenerator;
use Glyph\adapters\storage\UserFileRepository;
use Glyph\domain\users\UserRecord;

final class UserManager
{
    public function __construct(
        private readonly UserFileRepository $userRepository,
        private readonly PasswordHasher $passwordHasher,
        private readonly SecretGenerator $secretGenerator,
    ) {
    }

    /** @return list<UserRecord> */
    public function listUsers(): array { return $this->userRepository->all(); }
    public function findUser(string $id): ?UserRecord { return $this->userRepository->findById($id); }

    /** @return list<string> */
    public function roleOptions(): array { return ['reader', 'contributor', 'author', 'editor', 'administrator']; }

    public function validateForCreate(UserFormInput $input): UserFormValidationResult
    {
        return new UserFormValidationResult($this->baseValidation($input, true, null));
    }

    public function validateForEdit(UserFormInput $input, UserRecord $currentUser): UserFormValidationResult
    {
        return new UserFormValidationResult($this->baseValidation($input, false, $currentUser));
    }

    public function create(UserFormInput $input): UserRecord
    {
        $timestamp = $this->timestamp();
        $user = new UserRecord(
            id: $this->secretGenerator->generateId(12),
            email: strtolower($input->email),
            passwordHash: $this->passwordHasher->hash($input->password),
            role: $input->role,
            isActive: $input->isActive,
            createdAt: $timestamp,
            updatedAt: $timestamp,
            lastLoginAt: null,
            rememberTokens: [],
            passwordResetTokens: [],
            displayName: $input->displayName,
        );
        $this->userRepository->save($user);

        return $this->reloadSavedUser($user->id, $user->displayName, 'create');
    }

    public function update(UserRecord $user, UserFormInput $input): UserRecord
    {
        $updated = $user->withProfile(
            email: strtolower($input->email),
            role: $input->role,
            isActive: $input->isActive,
            updatedAt: $this->timestamp(),
            displayName: $input->displayName,
        );

        if ($input->password !== '') {
            $updated = $updated->withPasswordHash($this->passwordHasher->hash($input->password), $this->timestamp());
        }

        $this->userRepository->save($updated);

        return $this->reloadSavedUser($updated->id, $input->displayName, 'update');
    }

    /** @return array<string, string> */
    private function baseValidation(UserFormInput $input, bool $requirePassword, ?UserRecord $currentUser): array
    {
        $errors = [];

        if ($input->email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (filter_var($input->email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Email must be valid.';
        } else {
            $existing = $this->userRepository->findByEmail($input->email);
            if ($existing !== null && ($currentUser === null || $existing->id !== $currentUser->id)) {
                $errors['email'] = 'A user with that email already exists.';
            }
        }

        if ($input->displayName === '') {
            $errors['display_name'] = 'Display name is required.';
        }

        if (!in_array($input->role, $this->roleOptions(), true)) {
            $errors['role'] = 'Role is invalid.';
        }

        if ($requirePassword || $input->password !== '' || $input->passwordConfirmation !== '') {
            if (strlen($input->password) < 8) {
                $errors['password'] = 'Password must be at least 8 characters.';
            }
            if ($input->password !== $input->passwordConfirmation) {
                $errors['password_confirmation'] = 'Passwords do not match.';
            }
        }

        return $errors;
    }

    private function timestamp(): string
    {
        $timestamp = gmdate('c');
        if (!is_string($timestamp) || $timestamp === '') {
            throw new \RuntimeException('Failed to determine timestamp.');
        }
        return $timestamp;
    }

    private function reloadSavedUser(string $userId, string $expectedDisplayName, string $operation): UserRecord
    {
        $saved = $this->userRepository->findById($userId);

        if ($saved === null) {
            throw new \RuntimeException(sprintf('User %s failed because the saved record could not be reloaded.', $operation));
        }

        if ($saved->displayName !== $expectedDisplayName) {
            throw new \RuntimeException(sprintf(
                'User %s failed because the saved display name did not persist. Expected "%s" but found "%s".',
                $operation,
                $expectedDisplayName,
                $saved->displayName
            ));
        }

        return $saved;
    }
}

