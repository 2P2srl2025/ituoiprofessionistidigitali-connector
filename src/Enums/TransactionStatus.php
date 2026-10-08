<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Enums;

/**
 * Published on the platform or sent to a counterparty (rule R8).
 */
enum TransactionStatus: string
{
    case Invited = 'invited';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Revoked = 'revoked';
    case Completed = 'completed';
    case Published = 'published';
    case Withdrawn = 'withdrawn';

    /**
     * The statuses of an assignment published on the platform, still without a counterparty (rule R3).
     */
    public function isPublication(): bool
    {
        return in_array($this, [self::Published, self::Withdrawn], true);
    }

    /**
     * The statuses that end the transaction with a closed_at: done, revoked or withdrawn.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Revoked, self::Withdrawn], true);
    }

    /**
     * The statuses after an answer of the counterparty, with a responded_at.
     */
    public function isAnswered(): bool
    {
        return in_array($this, [self::Accepted, self::Declined, self::Completed], true);
    }

    /**
     * The statuses before any activity is entrusted: every activity is still open (rule R14).
     */
    public function isBeforeAgreement(): bool
    {
        return in_array($this, [self::Published, self::Withdrawn, self::Invited, self::Declined], true);
    }
}
