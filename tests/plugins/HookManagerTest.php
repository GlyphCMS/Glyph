<?php

declare(strict_types=1);

use Glyph\services\plugins\HookManager;
use Glyph\services\plugins\PluginAdminPage;

$hooks = new HookManager();
$captured = [];

$hooks->addAction('demo.action', static function (string $value) use (&$captured): void {
    $captured[] = $value;
});

$hooks->doAction('demo.action', 'hello');

if ($captured !== ['hello']) {
    return false;
}

$hooks->addFilter('demo.filter', static function (mixed $value): mixed {
    return is_string($value) ? strtoupper($value) : $value;
});

if ($hooks->applyFilters('demo.filter', 'glyph') !== 'GLYPH') {
    return false;
}

$hooks->addSlot('demo.slot', static function (array $context): string {
    return '<p>' . ($context['message'] ?? '') . '</p>';
});

if ($hooks->renderSlot('demo.slot', ['message' => 'Hi']) !== '<p>Hi</p>') {
    return false;
}

$hooks->registerAdminPage(
    new PluginAdminPage(
        pluginDirectoryName: 'hello-banner',
        pageKey: 'hello-banner',
        title: 'Hello Banner',
        description: 'Settings page',
        renderer: static fn (): string => '<p>Hello</p>',
    )
);

if ($hooks->findAdminPage('hello-banner') === null) {
    return false;
}

if (count($hooks->adminPagesForPlugin('hello-banner')) !== 1) {
    return false;
}

return true;
