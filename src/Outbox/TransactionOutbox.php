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
 * only when something changed (rules R7 and R9). Only the last version is needed: every PUT is the
 * complete picture, so the platform accepts a first record in any status consistent with its dates
 * and activities.
 */
final readonly class TransactionOutbox
{
    public function __construct(private ConnectorConfig $config) {}

    /**
     * Null for a transaction never sent or published: a draft does not reach the register.
     */
    public function record(RecordsPlatformTransaction $model): ?PlatformTransactionOutbox
    {
        $reference = $model->platformTransactionReference();

        if ($reference === null)
        {
            return null;
        }

        $payload = $this->payload($model);
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
     * The current version of the model as the outbox keeps it: the body of PUT without the revision.
     *
     * @return array<string, mixed>
     */
    public function payload(RecordsPlatformTransaction $model): array
    {
        $payload = $model->toPlatformTransaction()->toWire(revision: 0);
        unset($payload['revision']);

        /** @var array<string, mixed> */
        return json_decode((string) json_encode($payload, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
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
