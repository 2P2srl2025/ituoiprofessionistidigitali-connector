<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Outbox;

use ITuoiProfessionistiDigitali\Connector\ConnectorConfig;
use ITuoiProfessionistiDigitali\Connector\Contracts\RecordsPlatformTransaction;
use ITuoiProfessionistiDigitali\Connector\Enums\OutboxStatus;
use ITuoiProfessionistiDigitali\Connector\Jobs\SendPlatformTransaction;
use ITuoiProfessionistiDigitali\Connector\Models\PlatformTransactionOutbox;

/**
 * Keeps the last version of every transaction and sends it after the commit, with a new revision
 * only when something changed (rules R7 and R9).
 */
final readonly class TransactionOutbox
{
    public function __construct(private ConnectorConfig $config) {}

    public function record(RecordsPlatformTransaction $model): PlatformTransactionOutbox
    {
        $reference = $model->platformTransactionReference();
        $payload = $model->toPlatformTransaction()->toWire(revision: 0);
        unset($payload['revision']);
        $payload = json_decode((string) json_encode($payload, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        $row = PlatformTransactionOutbox::query()->firstOrNew(['reference' => $reference]);

        if ($row->exists && $row->payload === $payload)
        {
            return $row;
        }

        $row->fill([
            'payload' => $payload,
            'revision' => $row->exists ? $row->revision + 1 : 1,
            'status' => OutboxStatus::Pending,
            'last_error' => null,
        ])->save();

        dispatch(new SendPlatformTransaction($reference))->onQueue($this->config->queue)->afterCommit();

        return $row;
    }

    /**
     * Whether the platform confirmed the current revision.
     */
    public function isRecorded(string $reference): bool
    {
        $row = PlatformTransactionOutbox::query()->where('reference', $reference)->first();

        return $row instanceof PlatformTransactionOutbox && $row->sent_revision === $row->revision;
    }
}
