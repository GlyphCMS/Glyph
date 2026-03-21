<?php

declare(strict_types=1);

namespace Glyph\services\content;

use Glyph\adapters\filesystem\LocalFilesystem;

final class ContentAutosaveService
{
    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly string $autosavePath,
    ) {
    }

    public function save(string $scopeKey, ContentInput $input): void
    {
        $this->assertScopeKey($scopeKey);

        $payload = [
            'saved_at' => gmdate('c'),
            'content' => [
                'type' => $input->type,
                'title' => $input->title,
                'slug' => $input->slug,
                'status' => $input->status,
                'excerpt' => $input->excerpt,
                'body_html' => $input->bodyHtml,
                'featured_image' => $input->featuredImage,
                'parent_id' => $input->parentId,
                'seo_title' => $input->seoTitle,
                'seo_description' => $input->seoDescription,
                'seo_image' => $input->seoImage,
                'navigation_title' => $input->navigationTitle,
                'menu_order' => $input->menuOrder,
                'show_in_navigation' => $input->showInNavigation,
                'bypass_html_sanitization' => $input->bypassHtmlSanitization,
            ],
        ];

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($encoded) || $encoded === '') {
            throw new \RuntimeException('Failed to encode autosave payload.');
        }

        $this->filesystem->writeFile($this->filePath($scopeKey), $encoded . PHP_EOL);
    }

    public function load(string $scopeKey): ?ContentAutosaveRecord
    {
        $this->assertScopeKey($scopeKey);
        $path = $this->filePath($scopeKey);

        if (!$this->filesystem->isFile($path)) {
            return null;
        }

        $decoded = json_decode($this->filesystem->readFile($path), true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid autosave payload.');
        }

        $savedAt = isset($decoded['saved_at']) && is_string($decoded['saved_at'])
            ? $decoded['saved_at']
            : null;

        $content = isset($decoded['content']) && is_array($decoded['content'])
            ? $decoded['content']
            : [];

        return new ContentAutosaveRecord(
            scopeKey: $scopeKey,
            savedAt: $savedAt,
            input: ContentInput::fromPost($content),
        );
    }

    public function delete(string $scopeKey): void
    {
        $this->assertScopeKey($scopeKey);
        $this->filesystem->deleteFile($this->filePath($scopeKey));
    }

    private function filePath(string $scopeKey): string
    {
        return $this->autosavePath . '/' . $scopeKey . '.json';
    }

    private function assertScopeKey(string $scopeKey): void
    {
        if (preg_match('/^[a-z0-9_-]+$/', $scopeKey) !== 1) {
            throw new \RuntimeException('Invalid autosave scope key.');
        }
    }
}
