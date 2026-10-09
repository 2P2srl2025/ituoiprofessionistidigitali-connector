<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use Spatie\LaravelData\Data;

/**
 * The payload of transaction.application_received v1: a new application, pending (rule L6).
 */
final class ApplicationReceivedData extends Data
{
    public function __construct(
        public TransactionReferenceData $transaction,
        public ApplicationData $application,
    ) {}
}
