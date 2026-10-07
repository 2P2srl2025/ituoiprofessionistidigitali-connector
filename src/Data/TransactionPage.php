<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

/**
 * A page of GET /transactions: pass nextCursor to read on.
 */
final readonly class TransactionPage
{
    /**
     * @param  list<RecordedTransactionData>  $transactions
     */
    public function __construct(
        public array $transactions,
        public ?string $nextCursor,
    ) {}

    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }
}
