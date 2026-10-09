<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use Spatie\LaravelData\Data;

/**
 * The payload of transaction.counterparty_selected v1: the transaction accepted, as GET /transactions returns it,
 * the selected application and the mobile of the person (rules L4 and L6). It always reaches the system of the
 * principal, also after a selection made with the API: handle it once per event_id and per reference.
 */
final class CounterpartySelectedData extends Data
{
    public function __construct(
        public RecordedTransactionData $transaction,
        public ApplicationData $application,
        public ContactData $contact,
    ) {}
}
