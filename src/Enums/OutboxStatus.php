<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Enums;

enum OutboxStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}
