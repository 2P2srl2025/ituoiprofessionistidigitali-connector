<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use ITuoiProfessionistiDigitali\Connector\Enums\TransactionResult;
use Spatie\LaravelData\Data;

/**
 * The outcome of one transaction of POST /transactions/batch (rule R10).
 */
final class TransactionOutcomeData extends Data
{
    /**
     * @param  array<string, list<string>>|null  $errors
     */
    public function __construct(
        public mixed $reference,
        public TransactionResult $result,
        public ?RecordedTransactionData $transaction,
        public ?array $errors,
    ) {}
}
