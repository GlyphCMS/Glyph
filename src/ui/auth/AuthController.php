<?php

declare(strict_types=1);

namespace Glyph\ui\auth;

use Glyph\adapters\http\Request;
use Glyph\adapters\http\Response;
use Glyph\adapters\security\CsrfTokenManager;
use Glyph\services\auth\AuthenticationManager;
use Glyph\services\auth\LoginInput;
use Glyph\services\auth\LoginThrottle;
use Glyph\services\auth\LoginValidator;
use Glyph\services\auth\LoginValidationResult;
use Glyph\services\mail\MailManager;

final class AuthController
{
    private const LOGIN_FORM_ID = 'login_form';
    private const FORGOT_FORM_ID = 'forgot_password_form';
    private const RESET_FORM_ID = 'reset_password_form';

    /**
     * @param array<string, mixed> $mailConfig
     * @param array<string, mixed> $siteConfig
     */
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly LoginThrottle $loginThrottle,
        private readonly LoginThrottle $forgotPasswordThrottle,
        private readonly CsrfTokenManager $csrfTokenManager,
        private readonly array $mailConfig,
        private readonly array $siteConfig,
    ) {
    }

    public function showLogin(Request $request): Response
    {
        if ($this->authenticationManager->currentUser() !== null) {
            return Response::redirect('/admin');
        }

        return Response::html(
            (new LoginPageRenderer())->render(
                input: new LoginInput('', '', false),
                validationResult: new LoginValidationResult([]),
                loginErrorMessage: null,
                csrfToken: $this->csrfTokenManager->token(self::LOGIN_FORM_ID),
            )
        );
    }

    public function login(Request $request): Response
    {
        $input = LoginInput::fromPost($request->post());
        $validator = new LoginValidator();
        $validationResult = $validator->validate($input);

        if (!$this->csrfTokenManager->validate(self::LOGIN_FORM_ID, $request->postString('_csrf_token'))) {
            return $this->renderLoginPage($input, $validationResult, 'Your session token is invalid. Please try again.');
        }

        if (!$validationResult->isValid()) {
            return $this->renderLoginPage($input, $validationResult, null);
        }

        if ($this->loginThrottle->isBlocked($input->email, $request->clientIp())) {
            return $this->renderLoginPage($input, $validationResult, 'Too many login attempts. Please wait and try again.');
        }

        if (!$this->authenticationManager->attemptLogin($input)) {
            $this->loginThrottle->recordFailure($input->email, $request->clientIp());

            return $this->renderLoginPage($input, $validationResult, 'Invalid email or password.');
        }

        $this->loginThrottle->clear($input->email, $request->clientIp());

        return Response::redirect('/admin');
    }

    public function showForgotPassword(Request $request): Response
    {
        if ($this->authenticationManager->currentUser() !== null) {
            return Response::redirect('/admin');
        }

        return $this->renderForgotPasswordPage();
    }

    public function forgotPassword(Request $request): Response
    {
        $email = $request->postTrimmedString('email');

        if (!$this->csrfTokenManager->validate(self::FORGOT_FORM_ID, $request->postString('_csrf_token'))) {
            return $this->renderForgotPasswordPage($email, null, 'Your session token is invalid. Please try again.', 400);
        }

        if ($this->forgotPasswordThrottle->isBlocked($email, $request->clientIp())) {
            return $this->renderForgotPasswordPage($email, null, 'Too many reset requests. Please wait and try again.', 429);
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            $token = $this->authenticationManager->issuePasswordResetToken($email);

            if ($token !== null) {
                try {
                    $resetUrl = $this->baseUrl() . '/reset-password?token=' . rawurlencode($token);
                    (new MailManager($this->mailConfig))->send(
                        toEmail: $email,
                        subject: $this->siteName() . ' password reset',
                        htmlBody: '<p>You requested a password reset for your Glyph account.</p><p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Reset your password</a></p><p>If you did not request this, you can ignore this email.</p>',
                        textBody: "You requested a password reset for your Glyph account.\n\nReset your password: " . $resetUrl . "\n\nIf you did not request this, you can ignore this email.",
                    );
                } catch (\Throwable) {
                    // Keep generic response.
                }
            }
        }

        $this->forgotPasswordThrottle->recordFailure($email, $request->clientIp());

        return $this->renderForgotPasswordPage('', 'If an account with that email exists, a reset link has been sent.');
    }

    public function showResetPassword(Request $request): Response
    {
        $token = $request->queryTrimmedString('token');
        $isValid = $token !== '' && $this->authenticationManager->isPasswordResetTokenValid($token);

        if (!$isValid) {
            return $this->invalidResetTokenResponse();
        }

        return Response::html($this->renderResetPasswordForm($token, [], null));
    }

    public function resetPassword(Request $request): Response
    {
        if (!$this->csrfTokenManager->validate(self::RESET_FORM_ID, $request->postString('_csrf_token'))) {
            return Response::html($this->renderResetPasswordForm(
                $request->postTrimmedString('token'),
                [],
                'Your session token is invalid. Please try again.',
            ), 400);
        }

        $token = $request->postTrimmedString('token');
        $password = (string) ($request->postString('password') ?? '');
        $passwordConfirmation = (string) ($request->postString('password_confirmation') ?? '');

        $errors = [];

        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if ($password !== $passwordConfirmation) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        if ($token === '' || !$this->authenticationManager->isPasswordResetTokenValid($token)) {
            return $this->invalidResetTokenResponse();
        }

        if ($errors !== []) {
            return Response::html($this->renderResetPasswordForm($token, $errors, null), 400);
        }

        if (!$this->authenticationManager->resetPassword($token, $password)) {
            return Response::html($this->renderResetPasswordForm($token, [], 'This password reset link is invalid or has expired.'), 400);
        }

        return Response::html($this->authPageRenderer()->render(
            title: 'Password updated',
            subtitle: 'Your password has been reset successfully.',
            action: '/login',
            fields: [],
            values: [],
            errors: [],
            csrfToken: $this->csrfTokenManager->token(self::LOGIN_FORM_ID),
            successMessage: 'Your password has been updated. You can sign in now.',
            errorMessage: null,
            submitLabel: 'Log In',
            footerHtml: '<p class="footer-note"><a href="/login">Go to login</a></p>',
            hiddenFields: [],
        ));
    }

    public function logout(Request $request): Response
    {
        $this->authenticationManager->logout();

        return Response::redirect('/login');
    }

    private function renderForgotPasswordPage(string $email = '', ?string $successMessage = null, ?string $errorMessage = null, int $statusCode = 200): Response
    {
        return Response::html($this->authPageRenderer()->render(
            title: 'Forgot your password?',
            subtitle: 'Enter your email address and Glyph will send a password reset link if an account exists.',
            action: '/forgot-password',
            fields: [
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ],
            values: ['email' => $email],
            errors: [],
            csrfToken: $this->csrfTokenManager->token(self::FORGOT_FORM_ID),
            successMessage: $successMessage,
            errorMessage: $errorMessage,
            submitLabel: 'Send Reset Link',
            footerHtml: '<p class="footer-note"><a href="/login">Back to login</a></p>',
            hiddenFields: [],
        ), $statusCode);
    }

    private function invalidResetTokenResponse(): Response
    {
        return Response::html($this->authPageRenderer()->render(
            title: 'Reset password',
            subtitle: 'Password reset links expire and can only be used once.',
            action: '/reset-password',
            fields: [],
            values: [],
            errors: [],
            csrfToken: $this->csrfTokenManager->token(self::RESET_FORM_ID),
            successMessage: null,
            errorMessage: 'This password reset link is invalid or has expired.',
            submitLabel: 'Reset Password',
            footerHtml: '<p class="footer-note"><a href="/forgot-password">Request a new reset link</a></p>',
            hiddenFields: [],
        ), 400);
    }

    private function renderResetPasswordForm(string $token, array $errors, ?string $errorMessage): string
    {
        return $this->authPageRenderer()->render(
            title: 'Reset password',
            subtitle: 'Enter a new password for your account.',
            action: '/reset-password',
            fields: [
                ['name' => 'password', 'label' => 'New Password', 'type' => 'password', 'required' => true],
                ['name' => 'password_confirmation', 'label' => 'Confirm Password', 'type' => 'password', 'required' => true],
            ],
            values: ['password' => '', 'password_confirmation' => ''],
            errors: $errors,
            csrfToken: $this->csrfTokenManager->token(self::RESET_FORM_ID),
            successMessage: null,
            errorMessage: $errorMessage,
            submitLabel: 'Reset Password',
            footerHtml: '<p class="footer-note"><a href="/login">Back to login</a></p>',
            hiddenFields: ['token' => $token],
        );
    }

    private function renderLoginPage(LoginInput $input, LoginValidationResult $validationResult, ?string $loginErrorMessage): Response
    {
        return Response::html(
            (new LoginPageRenderer())->render(
                input: $input,
                validationResult: $validationResult,
                loginErrorMessage: $loginErrorMessage,
                csrfToken: $this->csrfTokenManager->token(self::LOGIN_FORM_ID),
            )
        );
    }

    private function authPageRenderer(): AuthPageRenderer
    {
        return new AuthPageRenderer();
    }

    private function baseUrl(): string
    {
        $siteUrl = $this->siteConfig['site_url'] ?? '';

        if (is_string($siteUrl) && $siteUrl !== '') {
            return rtrim($siteUrl, '/');
        }

        return 'http://localhost';
    }

    private function siteName(): string
    {
        $siteName = $this->siteConfig['site_name'] ?? 'Glyph';

        return is_string($siteName) && $siteName !== '' ? $siteName : 'Glyph';
    }
}
