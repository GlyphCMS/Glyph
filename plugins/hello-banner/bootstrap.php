<?php

declare(strict_types=1);

use Glyph\adapters\http\Request;
use Glyph\adapters\http\Response;
use Glyph\services\plugins\PluginContext;

return static function (PluginContext $plugin): void {
    $plugin->addSlot('admin.dashboard.after_stats', static function (array $context): string {
        return '<section class="notice notice--success"><p><strong>Hello Banner plugin is active.</strong> This card was injected through the admin.dashboard.after_stats slot.</p></section>';
    });

    $plugin->addSlot('theme.before_footer', static function (array $context) use ($plugin): string {
        $settings = $plugin->settings();
        $message = $settings['footer_message'] ?? 'Rendered by the Hello Banner plugin via the theme.before_footer slot.';

        if (!is_string($message) || trim($message) === '') {
            $message = 'Rendered by the Hello Banner plugin via the theme.before_footer slot.';
        }

        return '<div class="panel"><p class="muted">' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p></div>';
    });

    $plugin->addFilter('document.title', static function (mixed $value, string $bodyClass): mixed {
        if (!is_string($value)) {
            return $value;
        }

        if ($bodyClass === 'theme-admin') {
            return $value . ' · Glyph';
        }

        return $value;
    });

    $plugin->registerAdminPage(
        pageKey: 'hello-banner',
        title: 'Hello Banner Settings',
        description: 'Configure the footer banner message injected by the example plugin.',
        renderer: static function (Request $request) use ($plugin): string|Response {
            $settings = $plugin->settings();
            $currentMessage = $settings['footer_message'] ?? 'Rendered by the Hello Banner plugin via the theme.before_footer slot.';

            if (!is_string($currentMessage)) {
                $currentMessage = 'Rendered by the Hello Banner plugin via the theme.before_footer slot.';
            }

            $notice = '';

            if ($request->method() === 'POST') {
                $submittedToken = $request->postString('_csrf_token');

                if (!$plugin->validateCsrfToken('settings', $submittedToken)) {
                    $notice = '<div class="notice notice--error"><p><strong>Your session token is invalid. Please try again.</strong></p></div>';
                } else {
                    $submittedMessage = $request->postString('footer_message');
                    $footerMessage = is_string($submittedMessage) && trim($submittedMessage) !== ''
                        ? trim($submittedMessage)
                        : 'Rendered by the Hello Banner plugin via the theme.before_footer slot.';

                    $plugin->saveSettings([
                        'footer_message' => $footerMessage,
                    ]);

                    return Response::redirect('/admin/plugin-page?page=hello-banner&saved=1');
                }
            }

            $query = $request->query();
            if (($query['saved'] ?? null) === '1') {
                $notice = '<div class="notice notice--success"><p><strong>Plugin settings saved successfully.</strong></p></div>';
            }

            $body = '<section class="panel stack">';
            $body .= $notice;
            $body .= '<form method="post" action="/admin/plugin-page?page=hello-banner" class="form-grid">';
            $body .= '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($plugin->csrfToken('settings'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
            $body .= '<div class="field">';
            $body .= '<label for="footer_message">Footer Message</label>';
            $body .= '<input id="footer_message" name="footer_message" type="text" value="' . htmlspecialchars($currentMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
            $body .= '</div>';
            $body .= '<div class="field cluster"><button type="submit">Save Plugin Settings</button></div>';
            $body .= '</form>';
            $body .= '</section>';

            return $body;
        },
    );
};
