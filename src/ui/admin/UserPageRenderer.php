<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\domain\users\UserRecord;
use Glyph\services\users\UserFormInput;
use Glyph\services\users\UserFormValidationResult;
use Glyph\ui\shared\DateTimeFormatter;
use Glyph\ui\shared\DocumentRenderer;

final class UserPageRenderer
{
    private readonly DateTimeFormatter $dateTimeFormatter;

    public function __construct(?DateTimeFormatter $dateTimeFormatter = null)
    {
        $this->dateTimeFormatter = $dateTimeFormatter ?? new DateTimeFormatter();
    }

    /** @param list<UserRecord> $users */
    public function renderList(array $users): string
    {
        $document = new DocumentRenderer();

        $content = '<main class="page-shell stack">';
        $content .= '<section class="hero"><div class="toolbar"><div><p class="hero__eyebrow">Glyph Users</p><h1 class="hero__title">Manage users</h1><p class="hero__text">Create users, assign roles, deactivate accounts, update passwords, and control display names.</p></div><div class="cluster"><a class="button button-secondary" href="/admin">Dashboard</a><a class="button" href="/admin/users/create">New User</a></div></div></section>';
        $content .= '<section class="panel stack"><div><p class="kicker">Accounts</p><h2 class="page-title">All users</h2></div>';

        if ($users === []) {
            $content .= '<div class="notice notice--warning"><p class="empty-state">No users found.</p></div>';
        } else {
            $content .= '<div class="table-card"><div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Last Login</th><th>Actions</th></tr></thead><tbody>';
            foreach ($users as $user) {
                $content .= '<tr>';
                $content .= '<td><strong>' . $document->escape($user->displayNameOrFallback()) . '</strong></td>';
                $content .= '<td>' . $document->escape($user->email) . '</td>';
                $content .= '<td><span class="badge">' . $document->escape($user->role) . '</span></td>';
                $content .= '<td><span class="badge badge--status-' . $document->escape($user->isActive ? 'published' : 'draft') . '">' . $document->escape($user->isActive ? 'Active' : 'Inactive') . '</span></td>';
                $content .= '<td>' . $document->escape($this->dateTimeFormatter->formatDateTime($user->createdAt)) . '</td>';
                $content .= '<td>' . $document->escape($user->lastLoginAt !== null ? $this->dateTimeFormatter->formatDateTime($user->lastLoginAt) : 'Never') . '</td>';
                $content .= '<td><a class="button button-secondary button--compact" href="/admin/users/edit?id=' . rawurlencode($user->id) . '">Edit</a></td>';
                $content .= '</tr>';
            }
            $content .= '</tbody></table></div></div>';
        }

        $content .= '</section></main>';
        return $document->render('Glyph Users', $content, 'Manage users in Glyph.', 'theme-admin');
    }

    /** @param list<string> $roleOptions */
    public function renderForm(string $mode, UserFormInput $input, UserFormValidationResult $validation, array $roleOptions, string $csrfToken, ?string $userId, ?string $successMessage, ?string $errorMessage): string
    {
        $document = new DocumentRenderer();
        $isEdit = $mode === 'edit';
        $title = $isEdit ? 'Edit User' : 'Create User';
        $action = $isEdit ? '/admin/users/edit' : '/admin/users/create';
        $passwordAutocomplete = $isEdit ? 'new-password' : 'new-password';

        $content = '<main class="page-shell stack">';
        $content .= '<section class="hero"><div class="toolbar"><div><p class="hero__eyebrow">Glyph Users</p><h1 class="hero__title">' . $document->escape($title) . '</h1><p class="hero__text">Manage account identity, role, active state, display name, and password.</p></div><a class="button button-secondary" href="/admin/users">Back to users</a></div></section>';

        if ($successMessage) {
            $content .= '<div class="notice notice--success"><p><strong>' . $document->escape($successMessage) . '</strong></p></div>';
        }
        if ($errorMessage) {
            $content .= '<div class="notice notice--error"><p><strong>' . $document->escape($errorMessage) . '</strong></p></div>';
        }

        $content .= '<section class="panel stack"><form method="post" action="' . $document->escape($action) . '" class="form-grid form-grid--two" autocomplete="off">';
        $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($csrfToken) . '">';
        if ($userId !== null) {
            $content .= '<input type="hidden" name="id" value="' . $document->escape($userId) . '">';
        }

        $content .= $this->renderField('Display Name', 'display_name', 'text', $input->displayName, $validation->firstError('display_name'), $document);
        $content .= $this->renderField('Email', 'email', 'email', $input->email, $validation->firstError('email'), $document);
        $content .= $this->renderRoleSelect($input->role, $roleOptions, $validation->firstError('role'), $document);
        $content .= '<div class="field"><label for="is_active">Account Status</label><select id="is_active" name="is_active"><option value="1"' . ($input->isActive ? ' selected' : '') . '>Active</option><option value="0"' . (!$input->isActive ? ' selected' : '') . '>Inactive</option></select>';
        if (($err = $validation->firstError('is_active')) !== null) {
            $content .= '<small class="field-error">' . $document->escape($err) . '</small>';
        }
        $content .= '</div>';
        $content .= $this->renderField('Password' . ($isEdit ? ' (leave blank to keep current)' : ''), 'password', 'password', '', $validation->firstError('password'), $document, $passwordAutocomplete, true);
        $content .= $this->renderField('Confirm Password', 'password_confirmation', 'password', '', $validation->firstError('password_confirmation'), $document, $passwordAutocomplete, true);
        $content .= '<div class="field field--full cluster"><button type="submit">' . $document->escape($isEdit ? 'Save User' : 'Create User') . '</button></div>';
        $content .= '</form></section></main>';

        return $document->render($title, $content, 'Manage Glyph users.', 'theme-admin');
    }

    private function renderField(string $label, string $name, string $type, string $value, ?string $error, DocumentRenderer $document, ?string $autocomplete = null, bool $ignorePasswordManagers = false): string
    {
        $attributes = '';
        if ($autocomplete !== null && $autocomplete !== '') {
            $attributes .= ' autocomplete="' . $document->escape($autocomplete) . '"';
        }
        if ($ignorePasswordManagers) {
            $attributes .= ' data-lpignore="true" data-1p-ignore="true"';
        }

        $content = '<div class="field"><label for="' . $document->escape($name) . '">' . $document->escape($label) . '</label><input id="' . $document->escape($name) . '" name="' . $document->escape($name) . '" type="' . $document->escape($type) . '" value="' . $document->escape($value) . '"' . $attributes . '>';
        if ($error) {
            $content .= '<small class="field-error">' . $document->escape($error) . '</small>';
        }
        return $content . '</div>';
    }

    /** @param list<string> $roleOptions */
    private function renderRoleSelect(string $currentValue, array $roleOptions, ?string $error, DocumentRenderer $document): string
    {
        $content = '<div class="field"><label for="role">Role</label><select id="role" name="role">';
        foreach ($roleOptions as $role) {
            $content .= '<option value="' . $document->escape($role) . '"' . ($role === $currentValue ? ' selected' : '') . '>' . $document->escape(ucfirst($role)) . '</option>';
        }
        $content .= '</select>';
        if ($error) {
            $content .= '<small class="field-error">' . $document->escape($error) . '</small>';
        }
        return $content . '</div>';
    }
}
