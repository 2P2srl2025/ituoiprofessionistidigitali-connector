<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Concerns;

use ITuoiProfessionistiDigitali\Connector\Contracts\AffectsPlatformTransactions;
use ITuoiProfessionistiDigitali\Connector\Outbox\TransactionOutbox;
use LogicException;

/**
 * For the models that implement AffectsPlatformTransactions: every save and delete records again
 * the transactions they change. The outbox raises the revision only when the content changed.
 */
trait RecordsAffectedOnPlatform
{
    public static function bootRecordsAffectedOnPlatform(): void
    {
        // Checked on the list of interfaces: the trait must not be used without its contract
        throw_unless(in_array(AffectsPlatformTransactions::class, class_implements(static::class), true), LogicException::class,
            static::class.' usa RecordsAffectedOnPlatform ma non implementa AffectsPlatformTransactions.');

        $record = static function (AffectsPlatformTransactions $model): void {
            foreach ($model->affectedPlatformTransactions() as $transaction)
            {
                resolve(TransactionOutbox::class)->record($transaction);
            }
        };

        static::saved($record);
        static::deleted($record);
    }
}
