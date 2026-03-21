<?php

declare(strict_types=1);

namespace Glyph\services\auth;

final class LoginInput
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly bool $rememberMe,
    ) {
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function fromPost(array $post): self
    {
        $email = isset($post['email']) && is_string($post['email'])
            ? trim($post['email'])
            : '';

        $password = isset($post['password']) && is_string($post['password'])
            ? $post['password']
            : '';

        $rememberMe = isset($post['remember_me']) && $post['remember_me'] === '1';

        return new self(
            email: $email,
            password: $password,
            rememberMe: $rememberMe,
        );
    }
}