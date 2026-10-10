<?php

declare(strict_types=1);

use ITuoiProfessionistiDigitali\Connector\Data\TransactionWithdrawnData;
use ITuoiProfessionistiDigitali\Connector\Enums\Audience;
use ITuoiProfessionistiDigitali\Connector\Enums\TransactionActivityStatus;
use ITuoiProfessionistiDigitali\Connector\Enums\TransactionStatus;
use ITuoiProfessionistiDigitali\Connector\Enums\WithdrawalReason;
use Spatie\LaravelData\Exceptions\CannotCastEnum;

it('L9: reads the withdrawal of the platform, with the transaction withdrawn and why', function (): void {
    $withdrawal = TransactionWithdrawnData::from(transactionWithdrawn(['settlements' => []]));

    expect($withdrawal->reason)->toBe(WithdrawalReason::Expired)
        ->and($withdrawal->transaction)
        ->status->toBe(TransactionStatus::Withdrawn)
        ->audience->toBe(Audience::Any)
        ->counterparty->toBeNull()
        ->responded_at->toBeNull()
        ->revision->toBe(2)
        ->and($withdrawal->transaction->closed_at?->toIso8601ZuluString())->toBe('2026-11-30T23:00:00Z')
        ->and($withdrawal->transaction->activities[0]->status)->toBe(TransactionActivityStatus::Open);
});

it('L9: refuses a reason the contract does not know', function (): void {
    TransactionWithdrawnData::from([...transactionWithdrawn(), 'reason' => 'operator']);
})->throws(CannotCastEnum::class);
