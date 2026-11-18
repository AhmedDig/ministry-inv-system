<?php
namespace Ministry\Utils;

class PhoneNormalizer
{
    /**
     * Normalize a phone number to canonical Palestinian format: 05XXXXXXXX
     * Returns null if the number cannot be normalized.
     *
     * Accepts:
     *   0599123456          (10 digits, local)
     *   +970599123456       (country code)
     *   00970599123456      (country code with 00 prefix)
     *   970599123456        (country code no prefix)
     *   599123456           (9 digits, missing leading 0)
     */
    public static function normalize(string $raw): ?string
    {
        // Strip everything except digits
        $digits = preg_replace('/\D+/', '', $raw);

        if ($digits === '') {
            return null;
        }

        // Strip country code
        if (str_starts_with($digits, '00970')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '970')) {
            $digits = substr($digits, 3);
        }

        // Now we should have a 9-digit number (5XXXXXXXX) or 10-digit (05XXXXXXXX)
        if (strlen($digits) === 9 && $digits[0] === '5') {
            $digits = '0' . $digits;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '05')) {
            return $digits;
        }

        return null; // Not a valid Palestinian mobile number
    }

    /**
     * Human‑readable display form: 0599 123 456
     */
    public static function format(string $canonical): string
    {
        if (strlen($canonical) !== 10) {
            return $canonical;
        }
        return substr($canonical, 0, 4) . ' ' .
            substr($canonical, 4, 3) . ' ' .
            substr($canonical, 7, 3);
    }
}