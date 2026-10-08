<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Testing;

use ITuoiProfessionistiDigitali\Connector\Contracts\RecordsPlatformTransaction;
use ITuoiProfessionistiDigitali\Connector\Data\ProfessionalRecordData;
use ITuoiProfessionistiDigitali\Connector\Models\PlatformProfessionalOutbox;
use ITuoiProfessionistiDigitali\Connector\Models\PlatformTransactionOutbox;
use ITuoiProfessionistiDigitali\Connector\Outbox\TransactionOutbox;
use PHPUnit\Framework\Assert;

/**
 * Assertions for the tests of a system: every action that changes a transaction leaves its current version in the outbox,
 * and every change of the record of a professional leaves its declaration.
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
     * The record is the latest declaration in the outbox for the tax code (rule R18).
     */
    public static function assertDeclared(string $taxCode, ProfessionalRecordData $record): void
    {
        $row = PlatformProfessionalOutbox::query()->where('tax_code', $taxCode)->first();
        Assert::assertInstanceOf(PlatformProfessionalOutbox::class, $row, 'La dichiarazione dell\'anagrafica non è nella outbox.');
        Assert::assertSame($record->toWire(), $row->payload, 'La outbox non ha questa anagrafica come ultima dichiarazione.');
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
