<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use ITuoiProfessionistiDigitali\Connector\Enums\WithdrawalReason;
use Spatie\LaravelData\Data;

/**
 * The payload of transaction.withdrawn v1: the published transaction the platform withdrew by itself, as
 * GET /transactions returns it at the revision registered plus one, and why (rules L6 and L9). A withdrawal of the
 * system with PUT has no event: handle it once per event_id and per reference.
 */
final class TransactionWithdrawnData extends Data
{
    public function __construct(
        public RecordedTransactionData $transaction,
        public WithdrawalReason $reason,
    ) {}
}
