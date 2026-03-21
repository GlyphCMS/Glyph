<?php

declare(strict_types=1);

namespace Glyph\ui\shared;

use DateTimeImmutable;
use DateTimeZone;

final class DateTimeFormatter
{
    public function __construct(
        private readonly string $dateFormat = 'F j, Y',
        private readonly string $timeFormat = 'g:i A',
        private readonly string $timezone = 'UTC',
    ) {
    }

    /**
     * @param array<string, mixed> $siteConfig
     */
    public static function fromSiteConfig(array $siteConfig): self
    {
        $dateFormat = $siteConfig['date_format'] ?? 'F j, Y';
        $timeFormat = $siteConfig['time_format'] ?? 'g:i A';
        $timezone = $siteConfig['timezone'] ?? 'UTC';

        return new self(
            is_string($dateFormat) && $dateFormat !== '' ? $dateFormat : 'F j, Y',
            is_string($timeFormat) && $timeFormat !== '' ? $timeFormat : 'g:i A',
            is_string($timezone) && $timezone !== '' ? $timezone : 'UTC',
        );
    }

    public function formatDate(string $isoDateTime): string
    {
        return $this->format($isoDateTime, $this->dateFormat);
    }

    public function formatTime(string $isoDateTime): string
    {
        return $this->format($isoDateTime, $this->timeFormat);
    }

    public function formatDateTime(string $isoDateTime): string
    {
        return $this->format($isoDateTime, $this->dateFormat . ' ' . $this->timeFormat);
    }

    private function format(string $isoDateTime, string $format): string
    {
        try {
            return (new DateTimeImmutable($isoDateTime))
                ->setTimezone(new DateTimeZone($this->timezone))
                ->format($format);
        } catch (\Throwable) {
            return $isoDateTime;
        }
    }
}
