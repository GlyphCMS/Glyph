<?php

declare(strict_types=1);

use Glyph\domain\media\MediaRecord;
use Glyph\ui\admin\MediaPageRenderer;

$renderer = new MediaPageRenderer();
$media = [
    new MediaRecord(
        id: 'media-1',
        originalName: 'hero.png',
        storagePath: 'uploads/images/2026/03/hero.png',
        publicPath: '/uploads/images/2026/03/hero.png',
        mimeType: 'image/png',
        sizeBytes: 67654,
        width: 300,
        height: 300,
        uploadedBy: 'ryan',
        createdAt: '2026-03-11T16:26:19+00:00',
    ),
];

$html = $renderer->renderLibrary(
    mediaItems: $media,
    siteConfig: [
        'date_format' => 'F jS, Y',
        'time_format' => 'g:i A',
        'timezone' => 'America/New_York',
    ],
    uploadCsrfToken: 'upload-token',
    deleteCsrfToken: 'delete-token',
    errorMessage: null,
    successMessage: null,
);

return str_contains($html, 'class="media-grid"')
    && str_contains($html, '66.1 KB | 300x300')
    && str_contains($html, 'Uploaded March 11th, 2026 12:26 PM')
    && str_contains($html, 'class="code media-card__path"')
    && str_contains($html, 'id="media-library-upload-form"')
    && !str_contains($html, 'id="media-library-upload-submit"')
    && str_contains($html, 'id="media-library-search"')
    && str_contains($html, 'id="media-library-search-status"')
    && str_contains($html, 'href="/uploads/images/2026/03/hero.png" target="_blank" rel="noreferrer noopener">View Image</a>')
    && str_contains($html, 'data-media-search="hero.png /uploads/images/2026/03/hero.png"')
    && str_contains($html, 'mediaUploadForm.dataset.submitting = "true"')
    && str_contains($html, 'syncMediaSearch')
    && str_contains($html, 'class="cluster media-card__actions"');
