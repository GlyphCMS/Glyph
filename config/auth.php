<?php

declare(strict_types=1);

return [
    'session_cookie_name' => 'glyph_session',
    'remember_cookie_name' => 'glyph_remember',
    'session_lifetime_seconds' => 7200,
    'remember_lifetime_seconds' => 2592000,
    'login_rate_limit_max_attempts' => 5,
    'login_rate_limit_window_seconds' => 900,
    'password_reset_lifetime_seconds' => 3600,
    'session_cookie_same_site' => 'Lax',
];