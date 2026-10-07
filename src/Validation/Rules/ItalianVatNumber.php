<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An Italian partita IVA, or the numeric codice fiscale of an organization: 11 digits with a valid check digit (rule M4).
 */
final class ItalianVatNumber implements ValidationRule
{
    public static function isValid(string $value): bool
    {
        if (preg_match('/^\d{11}$/', $value) !== 1)
        {
            return false;
        }

        $sum = 0;

        foreach (mb_str_split(mb_substr($value, 0, 10)) as $position => $character)
        {
            $digit = (int) $character;

            if ($position % 2 === 1)
            {
                $digit *= 2;
                $digit = $digit > 9 ? $digit - 9 : $digit;
            }

            $sum += $digit;
        }

        return (10 - $sum % 10) % 10 === (int) $value[10];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !self::isValid($value))
        {
            $fail('Il campo :attribute non è una partita IVA valida.');
        }
    }
}
