<?php

declare(strict_types=1);

namespace Glyph\adapters\security;

use Glyph\adapters\session\SessionManager;

final class CsrfTokenManager
{
    private const SESSION_KEY = 'csrf_tokens';

    public function __construct(
        private readonly SessionManager $sessionManager,
        private readonly SecretGenerator $secretGenerator,
    ) {
    }

    public function token(string $formId): string
    {
        $tokens = $this->tokens();

        if (isset($tokens[$formId]) && is_string($tokens[$formId]) && $tokens[$formId] !== '') {
            return $tokens[$formId];
        }

        $token = $this->secretGenerator->generateHex(32);
        $tokens[$formId] = $token;
        $this->sessionManager->set(self::SESSION_KEY, $tokens);

        return $token;
    }

    public function validate(string $formId, ?string $submittedToken): bool
    {
        if ($submittedToken === null || $submittedToken === '') {
            return false;
        }

        $tokens = $this->tokens();
        $expectedToken = $tokens[$formId] ?? null;

        if (!is_string($expectedToken) || $expectedToken === '') {
            return false;
        }

        return hash_equals($expectedToken, $submittedToken);
    }

    /**
     * @return array<string, string>
     */
    private function tokens(): array
    {
        $tokens = $this->sessionManager->get(self::SESSION_KEY);

        if (!is_array($tokens)) {
            return [];
        }

        $normalized = [];

        foreach ($tokens as $key => $value) {
            if (is_string($key) && is_string($value) && $value !== '') {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}