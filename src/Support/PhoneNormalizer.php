<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Support;

use InvalidArgumentException;

/**
 * Normalizes a phone string to the canonical format used by the portal in v2
 * tickets. Two shapes are accepted:
 *
 *   - Domestic: digits only, optionally separated by spaces / dashes / parens
 *               (e.g. `159-1234-0001` → `15912340001`).
 *   - International: `+<country> <local>` (e.g. `+852 91234567`). Extra inner
 *               whitespace is collapsed to a single space between country code
 *               and local number; the local number itself is digits-only.
 *
 * Resolvers should pass both the inbound `phone` claim AND the locally-stored
 * column through this helper before comparing, so the two ends agree on
 * format regardless of how operators typed the number originally.
 */
class PhoneNormalizer
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '+')) {
            return self::normalizeInternational($value);
        }

        $digits = preg_replace('/[\s\-()]+/', '', $value);

        if (! is_string($digits) || preg_match('/^\d{6,20}$/', $digits) !== 1) {
            throw new InvalidArgumentException('Invalid phone number.');
        }

        return $digits;
    }

    private static function normalizeInternational(string $value): string
    {
        $cleaned = preg_replace('/[\-()]+/', ' ', $value);
        $cleaned = is_string($cleaned) ? preg_replace('/\s+/', ' ', trim($cleaned)) : null;

        if (! is_string($cleaned) || preg_match('/^\+(\d{1,4})\s+(.+)$/', $cleaned, $matches) !== 1) {
            throw new InvalidArgumentException('Invalid phone number.');
        }

        $local = preg_replace('/\s+/', '', $matches[2]);

        if (! is_string($local) || preg_match('/^\d{3,20}$/', $local) !== 1) {
            throw new InvalidArgumentException('Invalid phone number.');
        }

        return '+'.$matches[1].' '.$local;
    }
}
