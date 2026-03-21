<?php

declare(strict_types=1);

namespace Glyph\ui\admin;

use Glyph\domain\media\MediaRecord;
use Glyph\ui\shared\DateTimeFormatter;
use Glyph\ui\shared\DocumentRenderer;

final class MediaPageRenderer
{
    /**
     * @param list<MediaRecord> $mediaItems
     * @param array<string, mixed> $siteConfig
     */
    public function renderLibrary(array $mediaItems, array $siteConfig, string $uploadCsrfToken, string $deleteCsrfToken, ?string $errorMessage, ?string $successMessage): string
    {
        $document = new DocumentRenderer();
        $dateTimeFormatter = DateTimeFormatter::fromSiteConfig($siteConfig);

        $content = '<main class="page-shell stack">';
        $content .= '<section class="hero">';
        $content .= '<div class="toolbar">';
        $content .= '<h1 class="hero__title">Media Library</h1>';
        $content .= '<div class="cluster"><a class="button button-secondary" href="/admin">Dashboard</a></div>';
        $content .= '</div>';
        $content .= '</section>';

        $content .= '<section class="panel stack">';
        $content .= '<h2 class="page-title">Add An Image</h2>';

        if ($errorMessage !== null && $errorMessage !== '') {
            $content .= '<div class="notice notice--error"><p><strong>' . $document->escape($errorMessage) . '</strong></p></div>';
        }

        if ($successMessage !== null && $successMessage !== '') {
            $content .= '<div class="notice notice--success"><p><strong>' . $document->escape($successMessage) . '</strong></p></div>';
        }

        $content .= '<form id="media-library-upload-form" method="post" action="/admin/media/upload" enctype="multipart/form-data" class="form-grid">';
        $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($uploadCsrfToken) . '">';
        $content .= '<div class="field">';
        $content .= '<label for="media_file">Image File</label>';
        $content .= '<input id="media_file" type="file" name="media_file" accept="image/jpeg,image/png,image/gif,image/webp" required>';
        $content .= '<small class="field-help">Currently Supported: JPEG, PNG, GIF, and WebP</small>';
        $content .= '</div>';
        $content .= '</form>';
        $content .= '</section>';

        $content .= '<section class="panel stack">';
        $content .= '<h2 class="page-title">Available Media</h2>';
        $content .= '<div class="media-library-toolbar">';
        $content .= '<div class="field media-library-search-field">';
        $content .= '<input id="media-library-search" type="search" placeholder="Search filename or path">';
        $content .= '</div>';
        $content .= '<p id="media-library-search-status" class="muted media-library-search-status">' . $document->escape((string) count($mediaItems)) . ' images</p>';
        $content .= '</div>';

        if ($mediaItems === []) {
            $content .= $this->libraryScript();
            $content .= '<div class="notice notice--warning"><p class="empty-state">No media uploaded yet.</p></div>';
            $content .= '</section></main>';

            return $document->render('Media Library', $content, 'Upload and browse media in Glyph.', 'theme-admin');
        }

        $content .= '<div class="media-grid">';

        foreach ($mediaItems as $media) {
            $escapedPath = $document->escape($media->publicPath);
            $escapedName = $document->escape($media->originalName);
            $confirmMessage = $document->escape('Delete ' . $media->originalName . '? This cannot be undone.');
            $metadata = $this->formatBytes($media->sizeBytes);
            if ($media->width > 0 && $media->height > 0) {
                $metadata .= ' | ' . $media->width . 'x' . $media->height;
            }
            $uploadedAt = $dateTimeFormatter->formatDateTime($media->createdAt);

            $content .= '<article class="media-card" data-media-search="' . $document->escape(strtolower($media->originalName . ' ' . $media->publicPath)) . '">';
            $content .= '<div class="media-preview-frame"><img class="media-preview" src="' . $escapedPath . '" alt="' . $escapedName . '"></div>';
            $content .= '<div class="media-card__body">';
            $content .= '<strong class="media-card__title">' . $escapedName . '</strong>';
            $content .= '<div class="code media-card__path">' . $escapedPath . '</div>';
            $content .= '<p class="muted media-card__meta">' . $document->escape($metadata) . '</p>';
            $content .= '<p class="muted media-card__meta">Uploaded ' . $document->escape($uploadedAt) . '</p>';
            $content .= '<div class="cluster media-card__actions">';
            $content .= '<button type="button" class="button button-secondary js-copy-media-path" data-path="' . $escapedPath . '">Path</button>';
            $content .= '<button type="button" class="button button-secondary js-copy-media-img" data-img="&lt;img src=&quot;' . $escapedPath . '&quot; alt=&quot;&quot;&gt;">&lt;img&gt;</button>';
            $content .= '<a class="button button-secondary" href="' . $escapedPath . '" target="_blank" rel="noreferrer noopener">View</a>';
            $content .= '<form method="post" action="/admin/media/delete" class="inline-form js-delete-media-form">';
            $content .= '<input type="hidden" name="_csrf_token" value="' . $document->escape($deleteCsrfToken) . '">';
            $content .= '<input type="hidden" name="id" value="' . $document->escape($media->id) . '">';
            $content .= '<button type="submit" class="button-danger" data-confirm-message="' . $confirmMessage . '">Delete</button>';
            $content .= '</form>';
            $content .= '</div>';
            $content .= '</div>';
            $content .= '</article>';
        }

        $content .= '</div></section>';
        $content .= $this->libraryScript();
        $content .= '</main>';

        return $document->render('Media Library', $content, 'Upload and browse media in Glyph.', 'theme-admin');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $size = $bytes / 1024;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        $precision = $size >= 100 ? 0 : 1;
        $formatted = number_format($size, $precision);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted . ' ' . $units[$unitIndex];
    }

    private function libraryScript(): string
    {
        return '<script>
(() => {
    const copyText = async (value) => {
        try {
            await navigator.clipboard.writeText(value);
        } catch (error) {
            const area = document.createElement("textarea");
            area.value = value;
            document.body.appendChild(area);
            area.select();
            document.execCommand("copy");
            area.remove();
        }
    };

    const mediaUploadForm = document.getElementById("media-library-upload-form");
    const mediaUploadInput = document.getElementById("media_file");
    const mediaSearchInput = document.getElementById("media-library-search");
    const mediaSearchStatus = document.getElementById("media-library-search-status");
    const mediaCards = Array.from(document.querySelectorAll(".media-card"));

    const syncMediaSearch = () => {
        if (!(mediaSearchInput instanceof HTMLInputElement)) {
            return;
        }

        const query = mediaSearchInput.value.trim().toLowerCase();
        let visibleCount = 0;

        mediaCards.forEach((card) => {
            const searchValue = card instanceof HTMLElement ? (card.dataset.mediaSearch || "") : "";
            const isMatch = query === "" || searchValue.includes(query);
            if (card instanceof HTMLElement) {
                card.hidden = !isMatch;
            }
            if (isMatch) {
                visibleCount++;
            }
        });

        if (mediaSearchStatus instanceof HTMLElement) {
            mediaSearchStatus.textContent = query === ""
                ? `${visibleCount} images`
                : `${visibleCount} of ${mediaCards.length} images`;
        }
    };

    if (mediaSearchInput instanceof HTMLInputElement) {
        mediaSearchInput.addEventListener("input", syncMediaSearch);
        syncMediaSearch();
    }

    if (mediaUploadInput instanceof HTMLInputElement && mediaUploadForm instanceof HTMLFormElement) {
        mediaUploadInput.addEventListener("change", () => {
            if (!mediaUploadInput.files || mediaUploadInput.files.length === 0 || mediaUploadForm.dataset.submitting === "true") {
                return;
            }

            mediaUploadForm.dataset.submitting = "true";

            if (typeof mediaUploadForm.requestSubmit === "function") {
                mediaUploadForm.requestSubmit();
                return;
            }

            mediaUploadForm.submit();
        });
    }

    document.querySelectorAll(".js-copy-media-path").forEach((button) => {
        button.addEventListener("click", async () => {
            await copyText(button.dataset.path || "");
            button.textContent = "Copied";
            window.setTimeout(() => { button.textContent = "Path"; }, 1200);
        });
    });

    document.querySelectorAll(".js-copy-media-img").forEach((button) => {
        button.addEventListener("click", async () => {
            const encoded = button.dataset.img || "";
            const container = document.createElement("textarea");
            container.innerHTML = encoded;
            await copyText(container.value);
            button.textContent = "Copied";
            window.setTimeout(() => { button.textContent = "<img>"; }, 1200);
        });
    });

    document.querySelectorAll(".js-delete-media-form").forEach((form) => {
        form.addEventListener("submit", (event) => {
            const button = form.querySelector("[data-confirm-message]");
            const message = button instanceof HTMLElement
                ? (button.getAttribute("data-confirm-message") || "Delete this media item? This cannot be undone.")
                : "Delete this media item? This cannot be undone.";

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
})();
</script>';
    }
}





