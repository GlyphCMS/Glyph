<?php

declare(strict_types=1);

use Glyph\services\plugins\PluginLifecycleContext;

return static function (PluginLifecycleContext $plugin): void {
    $plugin->deleteSettings();
};
