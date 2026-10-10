<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Enums;

/**
 * Why the platform withdrew a published transaction by itself: expired, without a selection, for longer than the
 * days its operator configured (rule L9).
 */
enum WithdrawalReason: string
{
    case Expired = 'expired';
}
