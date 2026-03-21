<?php

declare(strict_types=1);

namespace Glyph\services\users;

final class UserFormInput
{
    public function __construct(
        public readonly string $email,
        public readonly string $displayName,
        public readonly string $role,
        public readonly bool $isActive,
        public readonly string $password,
        public readonly string $passwordConfirmation,
    ) {
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function fromPost(array $post): self
    {
        return new self(
            email: isset($post['email']) && is_string($post['email']) ? trim($post['email']) : '',
            displayName: isset($post['display_name']) && is_string($post['display_name']) ? trim($post['display_name']) : '',
            role: isset($post['role']) && is_string($post['role']) ? trim($post['role']) : '',
            isActive: isset($post['is_active']) && $post['is_active'] === '1',
            password: isset($post['password']) && is_string($post['password']) ? $post['password'] : '',
            passwordConfirmation: isset($post['password_confirmation']) && is_string($post['password_confirmation']) ? $post['password_confirmation'] : '',
        );
    }

    public static function fromUser(string $email, string $displayName, string $role, bool $isActive): self
    {
        return new self($email, $displayName, $role, $isActive, '', '');
    }
}
