<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\security\CsrfTokenManager;
use Glyph\adapters\security\SecretGenerator;
use Glyph\adapters\session\SessionManager;
use Glyph\adapters\storage\PhpConfigWriter;
use Glyph\services\plugins\HookManager;
use Glyph\services\plugins\PluginLoader;
use Glyph\services\plugins\PluginResolver;
use Glyph\services\plugins\PluginSettingsStore;

$root = sys_get_temp_dir() . '/glyph-plugin-loader-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root . '/hello');
    $filesystem->ensureDirectoryExists($root . '/sessions');
    file_put_contents($root . '/hello/plugin.json', json_encode(['name' => 'Hello'], JSON_PRETTY_PRINT));
    file_put_contents($root . '/hello/bootstrap.php', <<<'PHP'
<?php

declare(strict_types=1);

use Glyph\services\plugins\PluginContext;

return static function (PluginContext $plugin): void {
    $plugin->addFilter('demo.title', static function (mixed $value): mixed {
        return is_string($value) ? $value . ' world' : $value;
    });

    $plugin->registerAdminPage(
        'hello',
        'Hello',
        static fn (): string => '<p>Admin page</p>'
    );
};
PHP);

    $sessionManager = new SessionManager([
        'session_name' => 'glyph_test_session',
        'session_cookie_name' => 'glyph_test_cookie',
        'remember_cookie_name' => 'glyph_test_remember',
        'cookie_lifetime' => 0,
        'session_lifetime_seconds' => 7200,
        'remember_lifetime' => 1209600,
        'is_secure_cookie' => false,
        'is_http_only_cookie' => true,
        'session_cookie_same_site' => 'Lax',
    ], $root . '/sessions');
    $sessionManager->start();

    $hooks = new HookManager();
    $resolver = new PluginResolver($filesystem, $root, ['enabled' => ['hello']]);
    $store = new PluginSettingsStore(new PhpConfigWriter($filesystem), $root . '/plugin-settings.php');
    $loader = new PluginLoader($resolver, $hooks, $store, new CsrfTokenManager($sessionManager, new SecretGenerator()));
    $loader->loadEnabledPlugins();

    if ($hooks->applyFilters('demo.title', 'hello') !== 'hello world') {
        return false;
    }

    if ($hooks->findAdminPage('hello') === null) {
        return false;
    }

    return true;
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($root);
    }
}
