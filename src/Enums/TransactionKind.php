<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Enums;

enum TransactionKind: string
{
    case PersonAssignment = 'person_assignment';
    case FirmAssignment = 'firm_assignment';
}
