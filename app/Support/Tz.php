<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Display timestamps in the timezone the people reading them live in.
 *
 * APP_TIMEZONE is UTC and every stored timestamp is UTC — job_status_history,
 * job_apply_link_history, link_check_open_items, and the scan's own
 * checked_at. The admin screens were therefore showing UTC with no label,
 * five hours behind the reader, which makes "when did this run?" needlessly
 * hard to answer.
 *
 * WHAT THIS IS AND IS NOT FOR
 * Only for an INSTANT — a moment something happened. Not for a business date
 * such as jobs.delete_at, which is a day boundary rather than a point in time;
 * shifting that by five hours and stamping it "PKT" would imply a precision it
 * does not have, and could print a different day than the one the scheduler
 * acts on.
 *
 * Storage is untouched. This is presentation only.
 */
class Tz
{
    /** Where the people reading these screens are. */
    public const DISPLAY = 'Asia/Karachi';

    /** Shown next to every converted time so no one has to guess. */
    public const LABEL = 'PKT';

    /** How timestamps are stored. */
    public const STORED = 'UTC';

    /**
     * Format a stored UTC instant for display, suffixed with PKT.
     *
     * Accepts a 'Y-m-d H:i:s' string, a unix timestamp, or a DateTime.
     * Returns the fallback for null, empty or unparseable input rather than
     * throwing — a bad timestamp must not take an admin page down.
     */
    public static function pkt($value, string $format = 'd M Y, H:i', string $fallback = '—'): string
    {
        $dt = self::toDateTime($value);
        if (! $dt) {
            return $fallback;
        }

        return $dt->setTimezone(new DateTimeZone(self::DISPLAY))->format($format)
            . ' ' . self::LABEL;
    }

    /** Same, without the label — for places that print the unit themselves. */
    public static function pktRaw($value, string $format = 'd M Y, H:i', string $fallback = '—'): string
    {
        $dt = self::toDateTime($value);
        if (! $dt) {
            return $fallback;
        }

        return $dt->setTimezone(new DateTimeZone(self::DISPLAY))->format($format);
    }

    private static function toDateTime($value): ?DateTimeImmutable
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromFormat('U', (string) $value->getTimestamp())
                ->setTimezone(new DateTimeZone(self::STORED));
        }

        // A unix timestamp is already an absolute instant — no source zone to apply.
        if (is_int($value) || (is_string($value) && ctype_digit($value) && strlen($value) >= 9)) {
            return (new DateTimeImmutable('@' . (int) $value));
        }

        try {
            return new DateTimeImmutable((string) $value, new DateTimeZone(self::STORED));
        } catch (\Throwable $e) {
            return null;
        }
    }
}
