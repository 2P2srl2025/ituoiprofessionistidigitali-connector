<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Enums;

enum TransactionStatus: string
{
    case Invited = 'invited';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Revoked = 'revoked';
    case Completed = 'completed';
}
