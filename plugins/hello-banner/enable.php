<?php

declare(strict_types=1);

use Glyph\services\plugins\PluginLifecycleContext;

return static function (PluginLifecycleContext $plugin): void {
    $settings = $plugin->settings();

    if (($settings['footer_message'] ?? null) === null) {
        $plugin->saveSettings([
            'footer_message' => 'Rendered by the Hello Banner plugin via the theme.before_footer slot.',
        ]);
    }
};
