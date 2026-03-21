<?php

declare(strict_types=1);

namespace Glyph\ui\auth;

use Glyph\ui\shared\DocumentRenderer;

final class AuthPageRenderer
{
    /**
     * @param array<int, array{name:string,label:string,type:string,required?:bool}> $fields
     * @param array<string, string> $values
     * @param array<string, string> $errors
     * @param array<string, string> $hiddenFields
     */
    public function render(
        string $title,
        string $subtitle,
        string $action,
        array $fields,
        array $values,
        array $errors,
        string $csrfToken,
        ?string $successMessage,
        ?string $errorMessage,
        string $submitLabel,
        string $footerHtml = '',
        array $hiddenFields = [],
    ): string {
        $document = new DocumentRenderer();

        $content = '<main class="centered-shell"><div class="auth-card stack">';
        $content .= '<section class="hero"><p class="hero__eyebrow">Glyph Admin</p><h1 class="hero__title">' . $document->escape($title) . '</h1><p class="hero__text">' . $document->escape($subtitle) . '</p></section>';
        $content .= '<section class="panel stack">';

        if ($successMessage !== null && $successMessage !== '') {
            $content .= '<div class="notice notice--success"><p><strong>' . $document->escape($successMessage) . '</strong></p></div>';
        }

        if ($errorMessage !== null && $errorMessage !== '') {
            $content .= '<div class="notice notice--error"><p><strong>' . $document->escape($errorMessage) . '</strong></p></div>';
        }

        $content .= '<form method="post" action="' . $document->escape($action) . '" class="form-grid">';
        $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($csrfToken) . '">';

        foreach ($hiddenFields as $name => $value) {
            $content .= '<input type="hidden" name="' . $document->escape($name) . '" value="' . $document->escape($value) . '">';
        }

        foreach ($fields as $field) {
            $name = $field['name'];
            $label = $field['label'];
            $type = $field['type'];
            $required = ($field['required'] ?? true) ? ' required' : '';
            $value = $values[$name] ?? '';

            $content .= '<div class="field"><label for="' . $document->escape($name) . '">' . $document->escape($label) . '</label>';
            $content .= '<input id="' . $document->escape($name) . '" name="' . $document->escape($name) . '" type="' . $document->escape($type) . '" value="' . $document->escape($type === 'password' ? '' : $value) . '"' . $required . '>';

            if (isset($errors[$name])) {
                $content .= '<small class="field-error">' . $document->escape($errors[$name]) . '</small>';
            }

            $content .= '</div>';
        }

        $content .= '<div class="cluster"><button type="submit">' . $document->escape($submitLabel) . '</button></div>';
        $content .= '</form>';
        $content .= $footerHtml;
        $content .= '</section></div></main>';

        return $document->render($title, $content, $subtitle, 'theme-auth');
    }
}
