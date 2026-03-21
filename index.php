<?php

declare(strict_types=1);

use Glyph\adapters\http\Response;

require __DIR__ . '/bootstrap/autoload.php';

/** @var Response $response */
$response = require __DIR__ . '/bootstrap/app.php';
$response->send();
