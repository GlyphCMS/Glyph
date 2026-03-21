<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\services\content\ContentAutosaveService;
use Glyph\services\content\ContentInput;

$root = sys_get_temp_dir() . '/glyph-content-autosave-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root);

    $service = new ContentAutosaveService($filesystem, $root);
    $input = new ContentInput(
        type: 'post',
        title: 'Hello',
        slug: '/hello',
        status: 'draft',
        excerpt: 'Excerpt',
        bodyHtml: '<p>Body</p>',
        featuredImage: null,
        parentId: null,
        seoTitle: 'SEO',
        seoDescription: 'Description',
        navigationTitle: 'Nav Label',
        menuOrder: '7',
        showInNavigation: false,
        bypassHtmlSanitization: true,
        seoImage: '/uploads/images/seo.webp',
    );

    $service->save('create_post', $input);
    $loaded = $service->load('create_post');

    if ($loaded === null) {
        return false;
    }

    if ($loaded->input->title !== 'Hello') {
        return false;
    }

    if ($loaded->input->navigationTitle !== 'Nav Label') {
        return false;
    }

    if ($loaded->input->menuOrder !== '7') {
        return false;
    }

    if ($loaded->input->showInNavigation !== false) {
        return false;
    }

    if ($loaded->input->bypassHtmlSanitization !== true) {
        return false;
    }

    if ($loaded->input->seoImage !== '/uploads/images/seo.webp') {
        return false;
    }

    $service->delete('create_post');

    if ($service->load('create_post') !== null) {
        return false;
    }

    return true;
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
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
