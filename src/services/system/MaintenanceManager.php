<?php

declare(strict_types=1);

namespace Glyph\services\system;

use Glyph\adapters\storage\PhpConfigWriter;

final class MaintenanceManager
{
    public function __construct(
        private readonly PhpConfigWriter $configWriter,
        private readonly string $systemPath,
    ) {
    }

    /**
     * @param array<string, mixed> $post
     */
    public function inputFromPost(array $post): MaintenanceSettings
    {
        $message = isset($post['message']) && is_string($post['message'])
            ? trim($post['message'])
            : '';

        return new MaintenanceSettings(
            enabled: isset($post['enabled']) && $post['enabled'] === '1',
            message: $message !== '' ? $message : 'Glyph is currently undergoing maintenance. Please check back soon.',
        );
    }

    public function save(MaintenanceSettings $settings): void
    {
        $this->configWriter->write($this->systemPath . '/maintenance.php', [
            'enabled' => $settings->enabled,
            'message' => $settings->message,
        ]);
    }
}
