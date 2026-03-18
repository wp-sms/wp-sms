<?php

namespace WSms\Campaign;

defined('ABSPATH') || exit;

class QuietHoursGuard
{
    /**
     * Check if the current time falls within quiet hours.
     */
    public function isQuietNow(array $quietHours, ?string $recipientTimezone = null): bool
    {
        $tz = new \DateTimeZone($recipientTimezone ?? $quietHours['timezone'] ?? 'UTC');
        $now = new \DateTimeImmutable('now', $tz);

        return $this->isTimeInQuietWindow($now, $quietHours);
    }

    /**
     * Get the next time a message can be sent after quiet hours end.
     */
    public function nextSendableTime(array $quietHours, ?string $recipientTimezone = null): \DateTimeImmutable
    {
        $tz = new \DateTimeZone($recipientTimezone ?? $quietHours['timezone'] ?? 'UTC');
        $now = new \DateTimeImmutable('now', $tz);
        $end = $quietHours['end'] ?? '08:00';

        [$endHour, $endMinute] = explode(':', $end);
        $nextSendable = $now->setTime((int) $endHour, (int) $endMinute, 0);

        // If end time is already past today, it's tomorrow's end time
        if ($nextSendable <= $now) {
            $nextSendable = $nextSendable->modify('+1 day');
        }

        return $nextSendable;
    }

    private function isTimeInQuietWindow(\DateTimeImmutable $time, array $quietHours): bool
    {
        $start = $quietHours['start'] ?? '21:00';
        $end = $quietHours['end'] ?? '08:00';

        $currentTime = $time->format('H:i');

        // Midnight-crossing: e.g., 21:00 - 08:00
        if ($start > $end) {
            return $currentTime >= $start || $currentTime < $end;
        }

        // Same-day: e.g., 13:00 - 15:00
        return $currentTime >= $start && $currentTime < $end;
    }
}
