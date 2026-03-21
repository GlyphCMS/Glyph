<?php

declare(strict_types=1);

namespace Glyph\services\auth;

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\domain\shared\Text;

final class LoginThrottle
{
    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly string $throttlePath,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
        private readonly string $scope = 'login',
    ) {
    }

    public function isBlocked(string $email, string $clientIp): bool
    {
        $attemptData = $this->readAttempts($email, $clientIp);
        $attempts = $this->activeAttempts($attemptData['attempts'] ?? []);

        return count($attempts) >= $this->maxAttempts;
    }

    public function recordFailure(string $email, string $clientIp): void
    {
        $attemptData = $this->readAttempts($email, $clientIp);
        $attempts = $this->activeAttempts($attemptData['attempts'] ?? []);
        $attempts[] = time();

        $this->writeAttempts($email, $clientIp, $attempts);
    }

    public function clear(string $email, string $clientIp): void
    {
        $this->writeAttempts($email, $clientIp, []);
    }

    /**
     * @param mixed $attempts
     * @return list<int>
     */
    private function activeAttempts(mixed $attempts): array
    {
        if (!is_array($attempts)) {
            return [];
        }

        $cutoff = time() - $this->windowSeconds;
        $activeAttempts = [];

        foreach ($attempts as $attemptTimestamp) {
            if (is_int($attemptTimestamp) && $attemptTimestamp >= $cutoff) {
                $activeAttempts[] = $attemptTimestamp;
            }
        }

        return $activeAttempts;
    }

    /**
     * @return array{attempts:list<int>}
     */
    private function readAttempts(string $email, string $clientIp): array
    {
        $filePath = $this->pathForIdentity($email, $clientIp);

        if (!$this->filesystem->isFile($filePath)) {
            return ['attempts' => []];
        }

        $contents = $this->filesystem->readFile($filePath);
        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            return ['attempts' => []];
        }

        return [
            'attempts' => $this->activeAttempts($decoded['attempts'] ?? []),
        ];
    }

    /**
     * @param list<int> $attempts
     */
    private function writeAttempts(string $email, string $clientIp, array $attempts): void
    {
        $json = json_encode(['attempts' => $attempts], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($json) || $json === '') {
            throw new \RuntimeException('Failed to encode throttle record.');
        }

        $this->filesystem->writeFile($this->pathForIdentity($email, $clientIp), $json . PHP_EOL);
    }

    private function pathForIdentity(string $email, string $clientIp): string
    {
        $identityHash = sha1($this->scope . '|' . Text::lower($email) . '|' . $clientIp);

        return $this->throttlePath . '/' . $this->scope . '-' . $identityHash . '.json';
    }
}
