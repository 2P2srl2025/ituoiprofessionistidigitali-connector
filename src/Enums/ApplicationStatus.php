<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Enums;

/**
 * Pending, then withdrawn by the professional, selected by the principal, or declined by another selection or by
 * the withdrawal of the assignment (rules L3–L5).
 */
enum ApplicationStatus: string
{
    case Pending = 'pending';
    case Withdrawn = 'withdrawn';
    case Selected = 'selected';
    case Declined = 'declined';
}
