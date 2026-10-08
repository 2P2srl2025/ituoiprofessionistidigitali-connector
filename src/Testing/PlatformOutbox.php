<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Testing;

use ITuoiProfessionistiDigitali\Connector\Contracts\RecordsPlatformTransaction;
use ITuoiProfessionistiDigitali\Connector\Models\PlatformTransactionOutbox;
use ITuoiProfessionistiDigitali\Connector\Outbox\TransactionOutbox;
use PHPUnit\Framework\Assert;

/**
 * Assertions for the tests of a system: every action that changes a transaction leaves its current version in the outbox.
 */
final class PlatformOutbox
{
    /**
     * The current version of the model is in the outbox, ready to be sent.
     */
    public static function assertRecorded(RecordsPlatformTransaction $model): void
    {
        $reference = $model->platformTransactionReference();
        Assert::assertNotNull($reference, 'La transazione non ha un riferimento: è una bozza, che non si registra.');

        $row = PlatformTransactionOutbox::query()->where('reference', $reference)->first();
        Assert::assertInstanceOf(PlatformTransactionOutbox::class, $row, "La transazione {$reference} non è nella outbox.");
        Assert::assertSame(resolve(TransactionOutbox::class)->payload($model), $row->payload, "La outbox non ha la versione corrente della transazione {$reference}.");
    }

    /**
     * Nothing of the model reached the outbox: a draft, never sent or published.
     */
    public static function assertNotRecorded(RecordsPlatformTransaction $model): void
    {
        $reference = $model->platformTransactionReference();

        Assert::assertTrue(
            $reference === null || !PlatformTransactionOutbox::query()->where('reference', $reference)->exists(),
            "La transazione {$reference} è nella outbox.",
        );
    }
}
