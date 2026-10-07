<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Contracts;

use ITuoiProfessionistiDigitali\Connector\Data\TransactionData;

/**
 * A model whose every change must reach the register of the platform (rule R9).
 *
 * Implement it together with the RecordsOnPlatform trait: the trait writes each change in the outbox,
 * in the same database transaction, and the package sends it.
 */
interface RecordsPlatformTransaction
{
    /**
     * The stable identifier of the transaction in this system, never reused: it becomes the {reference} of the route.
     */
    public function platformTransactionReference(): string;

    /**
     * How the model looks in the register now. The revision is kept by the outbox.
     */
    public function toPlatformTransaction(): TransactionData;
}
