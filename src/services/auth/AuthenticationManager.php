<?php

declare(strict_types=1);

namespace Glyph\services\auth;

use Glyph\adapters\security\PasswordHasher;
use Glyph\adapters\security\SecretGenerator;
use Glyph\adapters\session\SessionManager;
use Glyph\adapters\storage\UserFileRepository;
use Glyph\domain\auth\RoleCapabilities;
use Glyph\domain\users\UserRecord;

final class AuthenticationManager
{
    private const SESSION_USER_ID_KEY = 'auth_user_id';

    /**
     * @param array<string, mixed> $authConfig
     */
    public function __construct(
        private readonly SessionManager $sessionManager,
        private readonly UserFileRepository $userRepository,
        private readonly PasswordHasher $passwordHasher,
        private readonly SecretGenerator $secretGenerator,
        private readonly array $authConfig,
    ) {
    }

    public function currentUser(): ?UserRecord
    {
        $userId = $this->sessionManager->get(self::SESSION_USER_ID_KEY);

        if (!is_string($userId) || $userId === '') {
            return null;
        }

        return $this->userRepository->findById($userId);
    }

    public function hasCapability(string $capability): bool
    {
        $user = $this->currentUser();

        if ($user === null || !$user->isActive) {
            return false;
        }

        return RoleCapabilities::hasCapability($user->role, $capability);
    }

    public function attemptLogin(LoginInput $input): bool
    {
        $user = $this->userRepository->findByEmail($input->email);

        if ($user === null || !$user->isActive) {
            return false;
        }

        if (!$this->passwordHasher->verify($input->password, $user->passwordHash)) {
            return false;
        }

        $loggedInAt = $this->timestamp();
        $user = $user->withLastLoginAt($loggedInAt);
        $this->userRepository->save($user);

        $this->sessionManager->regenerate();
        $this->sessionManager->set(self::SESSION_USER_ID_KEY, $user->id);

        if ($input->rememberMe) {
            $this->issueRememberMeToken($user);
        }

        return true;
    }

    public function restoreFromRememberCookie(?string $cookieValue): void
    {
        if ($cookieValue === null || $cookieValue === '' || $this->currentUser() !== null) {
            return;
        }

        [$selector, $token] = $this->parseSplitToken($cookieValue);

        if ($selector === null || $token === null) {
            return;
        }

        $user = $this->userRepository->findByRememberSelector($selector);

        if ($user === null || !$user->isActive) {
            return;
        }

        $matchedToken = null;
        $remainingTokens = [];

        foreach ($user->rememberTokens as $rememberToken) {
            if (strtotime($rememberToken['expires_at']) === false || strtotime($rememberToken['expires_at']) < time()) {
                continue;
            }

            if ($rememberToken['selector'] === $selector) {
                $matchedToken = $rememberToken;
            }

            $remainingTokens[] = $rememberToken;
        }

        if ($matchedToken === null || !$this->passwordHasher->verify($token, $matchedToken['token_hash'])) {
            return;
        }

        $this->sessionManager->regenerate();
        $this->sessionManager->set(self::SESSION_USER_ID_KEY, $user->id);

        $cleanedUser = $user->withRememberTokens($remainingTokens, $this->timestamp());
        $this->userRepository->save($cleanedUser);
        $this->issueRememberMeToken($cleanedUser);
    }

    public function logout(): void
    {
        $user = $this->currentUser();
        $cookieName = $this->rememberCookieName();

        if ($user !== null) {
            $this->userRepository->save($user->withRememberTokens([], $this->timestamp()));
        }

        $this->sessionManager->destroy();
        setcookie($cookieName, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => $this->sameSite(),
        ]);
    }

    public function issuePasswordResetToken(string $email): ?string
    {
        $user = $this->userRepository->findByEmail($email);

        if ($user === null || !$user->isActive) {
            return null;
        }

        $selector = $this->secretGenerator->generateId(9);
        $token = $this->secretGenerator->generateHex(18);
        $expiresAt = gmdate('c', time() + $this->passwordResetLifetimeSeconds());

        if (!is_string($expiresAt) || $expiresAt === '') {
            throw new \RuntimeException('Failed to determine password reset expiration.');
        }

        $tokens = [];

        foreach ($user->passwordResetTokens as $resetToken) {
            if (strtotime($resetToken['expires_at']) !== false && strtotime($resetToken['expires_at']) >= time()) {
                $tokens[] = $resetToken;
            }
        }

        $tokens[] = [
            'selector' => $selector,
            'token_hash' => $this->passwordHasher->hash($token),
            'expires_at' => $expiresAt,
        ];

        $this->userRepository->save($user->withPasswordResetTokens($tokens, $this->timestamp()));

        return $selector . ':' . $token;
    }

    public function isPasswordResetTokenValid(string $tokenValue): bool
    {
        return $this->findValidPasswordResetUser($tokenValue, false) !== null;
    }

    public function resetPassword(string $tokenValue, string $newPassword): bool
    {
        $user = $this->findValidPasswordResetUser($tokenValue, true);

        if ($user === null) {
            return false;
        }

        $updated = $user->withPasswordHash(
            $this->passwordHasher->hash($newPassword),
            $this->timestamp(),
        );

        $updated = $updated->withRememberTokens([], $updated->updatedAt);
        $this->userRepository->save($updated);

        return true;
    }

    private function findValidPasswordResetUser(string $tokenValue, bool $consume): ?UserRecord
    {
        [$selector, $token] = $this->parseSplitToken($tokenValue);

        if ($selector === null || $token === null) {
            return null;
        }

        $user = $this->userRepository->findByPasswordResetSelector($selector);

        if ($user === null || !$user->isActive) {
            return null;
        }

        $matchedToken = null;
        $remainingTokens = [];

        foreach ($user->passwordResetTokens as $resetToken) {
            if (strtotime($resetToken['expires_at']) === false || strtotime($resetToken['expires_at']) < time()) {
                continue;
            }

            if ($resetToken['selector'] === $selector) {
                $matchedToken = $resetToken;
                continue;
            }

            $remainingTokens[] = $resetToken;
        }

        if ($matchedToken === null || !$this->passwordHasher->verify($token, $matchedToken['token_hash'])) {
            return null;
        }

        if ($consume) {
            $user = $user->withPasswordResetTokens($remainingTokens, $this->timestamp());
            $this->userRepository->save($user);
        }

        return $user;
    }

    private function issueRememberMeToken(UserRecord $user): void
    {
        $selector = $this->secretGenerator->generateId(9);
        $token = $this->secretGenerator->generateHex(18);
        $expiresAtUnix = time() + $this->rememberLifetimeSeconds();
        $expiresAt = gmdate('c', $expiresAtUnix);

        if (!is_string($expiresAt) || $expiresAt === '') {
            throw new \RuntimeException('Failed to determine remember token expiration.');
        }

        $rememberTokens = [];
        foreach ($user->rememberTokens as $rememberToken) {
            if (strtotime($rememberToken['expires_at']) !== false && strtotime($rememberToken['expires_at']) >= time()) {
                $rememberTokens[] = $rememberToken;
            }
        }

        $rememberTokens[] = [
            'selector' => $selector,
            'token_hash' => $this->passwordHasher->hash($token),
            'expires_at' => $expiresAt,
        ];

        $updatedUser = $user->withRememberTokens($rememberTokens, $this->timestamp());
        $this->userRepository->save($updatedUser);

        setcookie($this->rememberCookieName(), $selector . ':' . $token, [
            'expires' => $expiresAtUnix,
            'path' => '/',
            'domain' => '',
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => $this->sameSite(),
        ]);
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function parseSplitToken(string $tokenValue): array
    {
        $parts = explode(':', $tokenValue, 2);

        if (count($parts) !== 2 || !is_string($parts[0]) || !is_string($parts[1]) || $parts[0] === '' || $parts[1] === '') {
            return [null, null];
        }

        return [$parts[0], $parts[1]];
    }

    private function rememberCookieName(): string
    {
        $cookieName = $this->authConfig['remember_cookie_name'] ?? null;

        if (!is_string($cookieName) || $cookieName === '') {
            throw new \RuntimeException('Invalid remember cookie name configuration.');
        }

        return $cookieName;
    }

    private function rememberLifetimeSeconds(): int
    {
        $lifetime = $this->authConfig['remember_lifetime_seconds'] ?? null;

        if (!is_int($lifetime) || $lifetime <= 0) {
            throw new \RuntimeException('Invalid remember_lifetime_seconds configuration.');
        }

        return $lifetime;
    }

    private function passwordResetLifetimeSeconds(): int
    {
        $lifetime = $this->authConfig['password_reset_lifetime_seconds'] ?? null;

        if (!is_int($lifetime) || $lifetime <= 0) {
            throw new \RuntimeException('Invalid password_reset_lifetime_seconds configuration.');
        }

        return $lifetime;
    }

    private function sameSite(): string
    {
        $sameSite = $this->authConfig['session_cookie_same_site'] ?? 'Lax';

        return in_array($sameSite, ['Lax', 'Strict', 'None'], true)
            ? $sameSite
            : 'Lax';
    }

    private function isHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? null;

        return is_string($https) && $https !== '' && strtolower($https) !== 'off';
    }

    private function timestamp(): string
    {
        $timestamp = gmdate('c');

        if (!is_string($timestamp) || $timestamp === '') {
            throw new \RuntimeException('Failed to determine timestamp.');
        }

        return $timestamp;
    }
}
