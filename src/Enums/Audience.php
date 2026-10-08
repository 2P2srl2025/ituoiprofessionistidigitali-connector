<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Enums;

/**
 * Whom an assignment is for: the type of its counterparty when the system gives one, or whom a publication is
 * open to (rules R3 and R16). It does not change after the first registration.
 */
enum Audience: string
{
    case Person = 'person';
    case Member = 'member';
    case Any = 'any';

    /**
     * Whether a counterparty of this type may receive the assignment: anyone admits both.
     */
    public function admits(CounterpartyType $type): bool
    {
        return $this === self::Any || $this->value === $type->value;
    }
}
