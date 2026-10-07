<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Concerns;

use ITuoiProfessionistiDigitali\Connector\Contracts\RecordsPlatformTransaction;
use ITuoiProfessionistiDigitali\Connector\Outbox\TransactionOutbox;

/**
 * For the models that implement RecordsPlatformTransaction: every save goes to the outbox.
 *
 * Mass updates (`Model::query()->update()`) skip the models and the outbox: use them never on these tables.
 */
trait RecordsOnPlatform
{
    public static function bootRecordsOnPlatform(): void
    {
        static::saved(static function (RecordsPlatformTransaction $model): void {
            resolve(TransactionOutbox::class)->record($model);
        });
    }

    /**
     * Whether the platform confirmed the current version of the transaction: only then is it operational.
     */
    public function isRecordedOnPlatform(): bool
    {
        return resolve(TransactionOutbox::class)->isRecorded($this->platformTransactionReference());
    }
}
