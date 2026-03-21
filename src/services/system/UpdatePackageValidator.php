<?php

declare(strict_types=1);

namespace Glyph\services\system;

use Glyph\adapters\filesystem\UploadedZipArchive;

final class UpdatePackageValidator
{
    private const MAX_UPLOAD_BYTES = 52428800;
    private const REQUIRED_ENTRIES = ['bootstrap/app.php', 'bootstrap/config.php', 'config/app.php', 'src', 'themes'];
    private const PRESERVED_PATHS = ['content', 'data', 'uploads'];

    public function __construct(
        private readonly UploadedZipArchive $uploadedZipArchive,
    ) {
    }

    /**
     * @param array<string, mixed> $uploadedFile
     */
    public function validate(array $uploadedFile): UpdatePackageValidationResult
    {
        return $this->uploadedZipArchive->withStagedUpload(
            $uploadedFile,
            self::MAX_UPLOAD_BYTES,
            'update',
            function (array $file, string $temporaryZipPath, \ZipArchive $zip, array $entryNames): UpdatePackageValidationResult {
                $entries = $this->normalizedEntries($entryNames);
                $topLevelEntries = $this->topLevelEntries($entries);
                $glyphRoot = $this->detectGlyphRoot($entries, $topLevelEntries);
                $requiredFound = $glyphRoot === null ? [] : $this->requiredEntriesFound($entries, $glyphRoot);

                return new UpdatePackageValidationResult(
                    packageName: $file['original_name'],
                    isValid: $glyphRoot !== null,
                    glyphRoot: $glyphRoot,
                    detectedTopLevelEntries: $topLevelEntries,
                    requiredEntriesFound: $requiredFound,
                    warnings: $this->warnings($entries),
                );
            }
        );
    }

    /**
     * @param list<string> $entryNames
     * @return list<string>
     */
    private function normalizedEntries(array $entryNames): array
    {
        return array_values(array_map(
            static fn (string $entryName): string => str_replace('\\', '/', trim($entryName, '/')),
            $entryNames
        ));
    }

    /**
     * @param list<string> $entries
     * @return list<string>
     */
    private function topLevelEntries(array $entries): array
    {
        $topLevelMap = [];

        foreach ($entries as $entry) {
            if ($entry === '') {
                continue;
            }

            $topLevelMap[explode('/', $entry)[0]] = true;
        }

        $topLevelEntries = array_values(array_keys($topLevelMap));
        sort($topLevelEntries);

        return $topLevelEntries;
    }

    /**
     * @param list<string> $entries
     * @param list<string> $topLevelEntries
     */
    private function detectGlyphRoot(array $entries, array $topLevelEntries): ?string
    {
        foreach (array_merge([''], $topLevelEntries) as $candidate) {
            $prefix = $candidate === '' ? '' : $candidate . '/';
            $allFound = true;

            foreach (self::REQUIRED_ENTRIES as $requiredEntry) {
                if (!$this->entryExists($entries, $prefix . $requiredEntry)) {
                    $allFound = false;
                    break;
                }
            }

            if ($allFound) {
                return $candidate === '' ? '.' : $candidate;
            }
        }

        return null;
    }

    /**
     * @param list<string> $entries
     * @return list<string>
     */
    private function requiredEntriesFound(array $entries, string $glyphRoot): array
    {
        $prefix = $glyphRoot === '.' ? '' : $glyphRoot . '/';
        $requiredFound = [];

        foreach (self::REQUIRED_ENTRIES as $requiredEntry) {
            if ($this->entryExists($entries, $prefix . $requiredEntry)) {
                $requiredFound[] = $requiredEntry;
            }
        }

        return $requiredFound;
    }

    /**
     * @param list<string> $entries
     * @return list<string>
     */
    private function warnings(array $entries): array
    {
        $warnings = [];

        foreach (self::PRESERVED_PATHS as $preservedPath) {
            if ($this->entryExists($entries, $preservedPath)) {
                $warnings[] = sprintf(
                    'Package contains %s/, which should normally be preserved from the existing install.',
                    $preservedPath
                );
            }
        }

        return array_values(array_unique($warnings));
    }

    /**
     * @param list<string> $entries
     */
    private function entryExists(array $entries, string $path): bool
    {
        foreach ($entries as $entry) {
            if ($entry === $path || str_starts_with($entry, rtrim($path, '/') . '/')) {
                return true;
            }
        }

        return false;
    }
}
