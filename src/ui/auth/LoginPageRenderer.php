<?php

declare(strict_types=1);

namespace Glyph\ui\auth;

use Glyph\services\auth\LoginInput;
use Glyph\services\auth\LoginValidationResult;
use Glyph\ui\shared\DocumentRenderer;

final class LoginPageRenderer
{
    public function render(
        LoginInput $input,
        LoginValidationResult $validationResult,
        ?string $loginErrorMessage,
        string $csrfToken,
    ): string {
        $document = new DocumentRenderer();

        $content = '<main class="centered-shell"><div class="auth-card stack">';
        $content .= '<section class="hero">';
        $content .= '<p class="hero__eyebrow">Glyph Admin</p>';
        $content .= '<h1 class="hero__title">Sign in to manage your site.</h1>';
        $content .= '<p class="hero__text">Write posts, manage pages, upload media, and customize your Glyph installation.</p>';
        $content .= '</section>';

        $content .= '<section class="panel stack">';

        if ($loginErrorMessage !== null && $loginErrorMessage !== '') {
            $content .= '<div class="notice notice--error"><p><strong>' . $document->escape($loginErrorMessage) . '</strong></p></div>';
        }

        $content .= '<form method="post" action="/login" class="form-grid">';
        $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($csrfToken) . '">';
        $content .= $this->renderField('Email', 'email', 'email', $input->email, $validationResult->firstError('email'), $document);
        $content .= $this->renderField('Password', 'password', 'password', '', $validationResult->firstError('password'), $document);
        $content .= '<label class="checkbox-row"><input type="checkbox" name="remember_me" value="1"' . ($input->rememberMe ? ' checked' : '') . '><span>Remember me on this device</span></label>';
        $content .= '<div class="cluster">';
        $content .= '<button type="submit">Log In</button>';
        $content .= '<a class="button button-secondary" href="/forgot-password">Forgot password?</a>';
        $content .= '<span class="footer-note">Sessions are protected with CSRF validation, secure cookies, and login throttling.</span>';
        $content .= '</div>';
        $content .= '</form>';
        $content .= '</section></div></main>';

        return $document->render('Glyph Login', $content, 'Sign in to the Glyph admin panel.', 'theme-auth');
    }

    private function renderField(
        string $label,
        string $name,
        string $type,
        string $value,
        ?string $error,
        DocumentRenderer $document,
    ): string {
        $field = '<div class="field">';
        $field .= '<label for="' . $document->escape($name) . '">' . $document->escape($label) . '</label>';
        $field .= '<input id="' . $document->escape($name) . '" name="' . $document->escape($name) . '" type="' . $document->escape($type) . '" value="' . $document->escape($value) . '" required>';

        if ($error !== null && $error !== '') {
            $field .= '<small class="field-error">' . $document->escape($error) . '</small>';
        }

        $field .= '</div>';

        return $field;
    }
}
