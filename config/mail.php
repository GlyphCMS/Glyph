<?php

declare(strict_types=1);

return [
    'transport' => 'php_mail',
    'from_name' => 'Glyph',
    'from_email' => '',
    'smtp' => [
        'host' => '',
        'port' => 587,
        'encryption' => 'tls',
        'username' => '',
        'password' => '',
        'timeout_seconds' => 15,
    ],
];
