<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use ITuoiProfessionistiDigitali\Connector\ConnectorConfig;
use ITuoiProfessionistiDigitali\Connector\Data\ProfessionalRecordData;
use ITuoiProfessionistiDigitali\Connector\Enums\OutboxStatus;
use ITuoiProfessionistiDigitali\Connector\Exceptions\PlatformRequestException;
use ITuoiProfessionistiDigitali\Connector\Exceptions\ProfessionalNotAssignedException;
use ITuoiProfessionistiDigitali\Connector\Models\PlatformProfessionalOutbox;
use ITuoiProfessionistiDigitali\Connector\Outbox\ProfessionalOutbox;
use ITuoiProfessionistiDigitali\Connector\PlatformClient;

/**
 * Sends the latest declaration of the record of a professional (rule R18). A 404 waits while transactions to
 * the person wait to be registered, and is discarded otherwise; network errors and a system not yet active
 * are retried; a contract violation stops it, with the error kept in the outbox.
 */
final class SendPlatformProfessional implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public function __construct(public string $taxCode) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600];
    }

    public function handle(PlatformClient $client, ConnectorConfig $config, ProfessionalOutbox $outbox): void
    {
        $row = PlatformProfessionalOutbox::query()->where('tax_code', $this->taxCode)->first();

        if (!$row instanceof PlatformProfessionalOutbox || $row->status !== OutboxStatus::Pending)
        {
            return;
        }

        if (!$config->isConnected())
        {
            $row->update(['last_error' => __('Il sistema non è collegato al portale.')]);

            return;
        }

        $revision = $row->revision;
        // Read before the request: a transaction confirmed after it sends the declaration again
        $isAwaited = $outbox->hasUnregisteredTransactions($this->taxCode);
        $row->update(['attempts' => $row->attempts + 1]);

        try
        {
            $client->declareProfessional($this->taxCode, ProfessionalRecordData::from($row->payload));
        }
        catch (ProfessionalNotAssignedException)
        {
            if ($isAwaited)
            {
                $row->update(['last_error' => __('In attesa della registrazione delle transazioni al professionista.')]);

                return;
            }

            PlatformProfessionalOutbox::query()->whereKey($row->id)->where('revision', $revision)->update([
                'status' => OutboxStatus::Discarded,
                'last_error' => __('Il sistema non ha transazioni registrate al professionista.'),
            ]);
            Log::info('Dichiarazione dell\'anagrafica scartata: il portale non ha transazioni del sistema al professionista.', ['outbox_id' => $row->id]);

            return;
        }
        catch (PlatformRequestException $exception)
        {
            $isFinal = $exception->isContractViolation();

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

        // A newer declaration may have arrived meanwhile: it has its own job and its own revision
        PlatformProfessionalOutbox::query()->whereKey($row->id)->update([
            'sent_revision' => $revision,
            'sent_at' => CarbonImmutable::now(),
            'last_error' => null,
        ]);
        PlatformProfessionalOutbox::query()->whereKey($row->id)->where('revision', $revision)->update(['status' => OutboxStatus::Sent]);
    }
}
