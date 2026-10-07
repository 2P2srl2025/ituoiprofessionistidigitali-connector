<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An Italian codice fiscale with a valid check character (rule M4).
 *
 * 16 upper case characters for a person, omocodia included; 11 digits for an organization,
 * unless the rule is built for persons only.
 */
final readonly class ItalianTaxCode implements ValidationRule
{
    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private const string PERSON_PATTERN = '/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/';

    /**
     * Values of the characters in odd positions (1st, 3rd, ...); digits count as the letter in the same place.
     */
    private const array ODD_VALUES = [
        'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9, 'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19,
        'J' => 21, 'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11, 'P' => 3, 'Q' => 6, 'R' => 8,
        'S' => 12, 'T' => 14, 'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
    ];

    public function __construct(private bool $personOnly = false) {}

    public static function isValidForPerson(string $value): bool
    {
        if (preg_match(self::PERSON_PATTERN, $value) !== 1)
        {
            return false;
        }

        $sum = 0;

        foreach (mb_str_split(mb_substr($value, 0, 15)) as $position => $character)
        {
            $letter = ctype_digit($character) ? self::ALPHABET[(int) $character] : $character;
            $sum += $position % 2 === 0 ? self::ODD_VALUES[$letter] : (int) mb_strpos(self::ALPHABET, $letter);
        }

        return self::ALPHABET[$sum % 26] === $value[15];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isValid = is_string($value) && (
            self::isValidForPerson($value)
            || (!$this->personOnly && ItalianVatNumber::isValid($value))
        );

        if (!$isValid)
        {
            $fail('Il campo :attribute non è un codice fiscale valido.');
        }
    }
}
