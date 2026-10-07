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
}
