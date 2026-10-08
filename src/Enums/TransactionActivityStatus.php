<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Enums;

/**
 * Where an activity of a transaction stands: still to do, done or taken back (rule R14).
 */
enum TransactionActivityStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Revoked = 'revoked';

    public function isClosed(): bool
    {
        return $this !== self::Open;
    }
}
