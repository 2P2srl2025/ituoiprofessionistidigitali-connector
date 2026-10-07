<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use ITuoiProfessionistiDigitali\Connector\ConnectorConfig;
use ITuoiProfessionistiDigitali\Connector\Data\TransactionData;
use ITuoiProfessionistiDigitali\Connector\Enums\OutboxStatus;
use ITuoiProfessionistiDigitali\Connector\Exceptions\PlatformRequestException;
use ITuoiProfessionistiDigitali\Connector\Models\PlatformTransactionOutbox;
use ITuoiProfessionistiDigitali\Connector\PlatformClient;

/**
 * Sends the last version of a transaction to the register. Network errors and a system not yet active
 * are retried; a contract violation or a conflict stop it, with the error kept in the outbox.
 */
final class SendPlatformTransaction implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public function __construct(public string $reference) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600];
    }

    public function handle(PlatformClient $client, ConnectorConfig $config): void
    {
        $row = PlatformTransactionOutbox::query()->where('reference', $this->reference)->first();

        if (!$row instanceof PlatformTransactionOutbox || $row->sent_revision === $row->revision)
        {
            return;
        }

        if (!$config->isConnected())
        {
            $row->update(['last_error' => __('Il sistema non è collegato al portale.')]);

            return;
        }

        $revision = $row->revision;
        $row->update(['attempts' => $row->attempts + 1]);

        try
        {
            $client->recordTransaction($this->reference, TransactionData::from($row->payload), $revision);
        }
        catch (PlatformRequestException $exception)
        {
            $isFinal = $exception->isContractViolation() || $exception->isConflict();

            $row->update([
                'status' => $isFinal ? OutboxStatus::Failed : OutboxStatus::Pending,
                'last_error' => mb_trim($exception->getMessage().' '.json_encode($exception->errors, JSON_UNESCAPED_UNICODE)),
            ]);

            if ($isFinal)
            {
                return;
            }

            throw $exception;
        }

        // Another change may have arrived meanwhile: it has its own job and its own revision
        PlatformTransactionOutbox::query()->whereKey($row->id)->update([
            'sent_revision' => $revision,
            'sent_at' => CarbonImmutable::now(),
            'last_error' => null,
        ]);
        PlatformTransactionOutbox::query()->whereKey($row->id)->where('revision', $revision)->update(['status' => OutboxStatus::Sent]);
    }
}
