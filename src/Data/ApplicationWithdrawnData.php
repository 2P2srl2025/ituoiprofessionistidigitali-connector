<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use Spatie\LaravelData\Data;

/**
 * The payload of transaction.application_withdrawn v1: an application withdrawn by the professional, or because
 * the account was deleted or deactivated, with its closed_at (rules L3 and L6).
 */
final class ApplicationWithdrawnData extends Data
{
    public function __construct(
        public TransactionReferenceData $transaction,
        public ApplicationData $application,
    ) {}
}
