<?php

declare(strict_types=1);

use Glyph\services\install\InstallInput;
use Glyph\services\install\InstallValidator;

$validator = new InstallValidator();

$validInput = new InstallInput(
    siteName: 'Glyph CMS',
    siteUrl: 'https://example.com',
    adminEmail: 'owner@example.com',
    password: 'correct horse battery staple',
    passwordConfirmation: 'correct horse battery staple',
    cacheDriver: 'file',
);

$validResult = $validator->validate($validInput, false);

if (!$validResult->isValid()) {
    return false;
}

$validApcuInput = new InstallInput(
    siteName: 'Glyph CMS',
    siteUrl: 'https://example.com',
    adminEmail: 'owner@example.com',
    password: 'correct horse battery staple',
    passwordConfirmation: 'correct horse battery staple',
    cacheDriver: 'apcu',
);

if (!$validator->validate($validApcuInput, true)->isValid()) {
    return false;
}

$invalidInput = new InstallInput(
    siteName: '',
    siteUrl: 'ftp://example.com',
    adminEmail: 'not-an-email',
    password: 'short',
    passwordConfirmation: 'different',
    cacheDriver: 'apcu',
);

$invalidResult = $validator->validate($invalidInput, false);
$fieldErrors = $invalidResult->fieldErrors();

if ($invalidResult->isValid()) {
    return false;
}

$expectedFields = [
    'site_name',
    'site_url',
    'admin_email',
    'password',
    'password_confirmation',
    'cache_driver',
];

foreach ($expectedFields as $field) {
    if (!array_key_exists($field, $fieldErrors)) {
        return false;
    }
}

return true;
