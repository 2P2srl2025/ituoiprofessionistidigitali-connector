<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Enums;

enum TransactionResult: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Unchanged = 'unchanged';
    case Stale = 'stale';
    case Conflict = 'conflict';
    case Invalid = 'invalid';

    /**
     * Whether the platform has the transaction as it was sent.
     */
    public function isRecorded(): bool
    {
        return in_array($this, [self::Created, self::Updated, self::Unchanged], true);
    }
}
