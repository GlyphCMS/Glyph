<?php

declare(strict_types=1);

namespace Glyph\services\auth;

use Glyph\domain\shared\Text;

final class LoginValidator
{
    private const MAXIMUM_EMAIL_LENGTH = 255;

    public function validate(LoginInput $input): LoginValidationResult
    {
        $fieldErrors = [];

        if ($input->email === '') {
            $fieldErrors['email'] = 'Email is required.';
        } elseif (Text::length($input->email) > self::MAXIMUM_EMAIL_LENGTH) {
            $fieldErrors['email'] = 'Email is too long.';
        } elseif (filter_var($input->email, FILTER_VALIDATE_EMAIL) === false) {
            $fieldErrors['email'] = 'Email must be a valid email address.';
        }

        if ($input->password === '') {
            $fieldErrors['password'] = 'Password is required.';
        }

        return new LoginValidationResult($fieldErrors);
    }
}