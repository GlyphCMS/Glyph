<?php

declare(strict_types=1);

use Glyph\services\auth\LoginInput;
use Glyph\services\auth\LoginValidator;

$validator = new LoginValidator();

$validInput = new LoginInput(
    email: 'owner@example.com',
    password: 'correct horse battery staple',
    rememberMe: true,
);

$validResult = $validator->validate($validInput);

if (!$validResult->isValid()) {
    return false;
}

$invalidInput = new LoginInput(
    email: 'not-an-email',
    password: '',
    rememberMe: false,
);

$invalidResult = $validator->validate($invalidInput);

if ($invalidResult->isValid()) {
    return false;
}

$fieldErrors = $invalidResult->fieldErrors();

if (!array_key_exists('email', $fieldErrors)) {
    return false;
}

if (!array_key_exists('password', $fieldErrors)) {
    return false;
}

return true;