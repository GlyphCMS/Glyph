<?php

declare(strict_types=1);

namespace Glyph\services\content;

final class SlugManager
{
    /**
     * @var list<string>
     */
    private const RESERVED_TOP_LEVEL_SEGMENTS = [
        'admin',
        'forgot-password',
        'reset-password',
        'search',
        'categories',
        'install',
        'login',
        'logout',
        'assets',
        'content',
        'system',
        'themes',
        'plugins',
        'uploads',
        'health',
    ];

    public function normalize(string $slug): string
    {
        $normalized = trim(str_replace('\\', '/', $slug));

        if ($normalized === '') {
            return '/';
        }

        $normalized = preg_replace('#/+#', '/', trim($normalized, '/'));

        if (!is_string($normalized) || $normalized === '') {
            return '/';
        }

        $segments = explode('/', $normalized);
        $normalizedSegments = [];

        foreach ($segments as $segment) {
            $normalizedSegment = $this->normalizeSegmentValue($segment);

            if ($normalizedSegment === '') {
                continue;
            }

            $normalizedSegments[] = $normalizedSegment;
        }

        if ($normalizedSegments === []) {
            return '/';
        }

        return '/' . implode('/', $normalizedSegments);
    }

    public function isReserved(string $slug): bool
    {
        $normalized = $this->normalize($slug);

        if ($normalized === '/') {
            return true;
        }

        $segments = explode('/', trim($normalized, '/'));
        $firstSegment = $segments[0] ?? '';

        return in_array($firstSegment, self::RESERVED_TOP_LEVEL_SEGMENTS, true);
    }

    public function isValid(string $slug): bool
    {
        $normalized = $this->normalize($slug);

        if ($normalized === '/') {
            return false;
        }

        return preg_match('#^/[a-z0-9/_-]+$#', $normalized) === 1;
    }

    public function normalizeSegment(string $slug): string
    {
        return $this->normalizeSegmentValue($slug);
    }

    public function isValidSegment(string $slug): bool
    {
        $normalized = $this->normalizeSegment($slug);

        return $normalized !== '' && preg_match('/^[a-z0-9_-]+$/', $normalized) === 1;
    }

    private function normalizeSegmentValue(string $segment): string
    {
        $normalized = strtolower(trim($this->transliterate($segment)));
        $normalized = preg_replace('/[^a-z0-9_-]+/', '-', $normalized);
        $normalized = preg_replace('/-+/', '-', $normalized);

        if (!is_string($normalized)) {
            return '';
        }

        return trim($normalized, '-');
    }

    private function transliterate(string $value): string
    {
        if (!function_exists('iconv')) {
            return $value;
        }

        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if (!is_string($converted) || $converted === '') {
            return $value;
        }

        return $converted;
    }
}

