<?php

declare(strict_types=1);

use Glyph\ui\shared\DateTimeFormatter;

$formatter = DateTimeFormatter::fromSiteConfig([
    'timezone' => 'America/New_York',
    'date_format' => 'm/d/Y',
    'time_format' => 'g:i A',
]);

return $formatter->formatDateTime('2026-03-10T22:47:52+00:00') === '03/10/2026 6:47 PM'
    && $formatter->formatDateTime('not-a-date') === 'not-a-date';
