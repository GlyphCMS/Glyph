<?php

declare(strict_types=1);

namespace Glyph\adapters\storage;

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\domain\users\UserRecord;

final class UserFileRepository
{
    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly string $usersPath,
    ) {
    }

    public function findById(string $id): ?UserRecord
    {
        $path = $this->pathForId($id);

        if (!$this->filesystem->isFile($path)) {
            return null;
        }

        return $this->readUserFile($path);
    }

    public function findByEmail(string $email): ?UserRecord
    {
        foreach ($this->all() as $user) {
            if (strcasecmp($user->email, $email) === 0) {
                return $user;
            }
        }

        return null;
    }

    public function findByRememberSelector(string $selector): ?UserRecord
    {
        foreach ($this->all() as $user) {
            foreach ($user->rememberTokens as $token) {
                if ($token['selector'] === $selector) {
                    return $user;
                }
            }
        }

        return null;
    }

    public function findByPasswordResetSelector(string $selector): ?UserRecord
    {
        foreach ($this->all() as $user) {
            foreach ($user->passwordResetTokens as $token) {
                if ($token['selector'] === $selector) {
                    return $user;
                }
            }
        }

        return null;
    }

    /**
     * @return list<UserRecord>
     */
    public function all(): array
    {
        $users = [];

        foreach ($this->allFiles() as $filePath) {
            $users[] = $this->readUserFile($filePath);
        }

        usort($users, static fn (UserRecord $a, UserRecord $b): int => strcmp($a->createdAt, $b->createdAt));

        return $users;
    }

    public function save(UserRecord $user): void
    {
        $json = json_encode($user->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($json) || $json === '') {
            throw new \RuntimeException('Failed to encode user record.');
        }

        $this->filesystem->writeFile($this->pathForId($user->id), $json . PHP_EOL);
    }

    private function pathForId(string $id): string
    {
        return $this->usersPath . '/' . $id . '.json';
    }

    /**
     * @return list<string>
     */
    private function allFiles(): array
    {
        $files = glob($this->usersPath . '/*.json');

        if ($files === false) {
            throw new \RuntimeException('Failed to read user records.');
        }

        $normalizedFiles = [];

        foreach ($files as $file) {
            if (is_string($file)) {
                $normalizedFiles[] = $file;
            }
        }

        sort($normalizedFiles);

        return $normalizedFiles;
    }

    private function readUserFile(string $path): UserRecord
    {
        $contents = $this->filesystem->readFile($path);
        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException(sprintf('Invalid user file JSON: %s', $path));
        }

        /** @var array<string, mixed> $decoded */
        return UserRecord::fromArray($decoded);
    }
}
