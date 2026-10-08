<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Outbox;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use ITuoiProfessionistiDigitali\Connector\ConnectorConfig;
use ITuoiProfessionistiDigitali\Connector\Data\ProfessionalRecordData;
use ITuoiProfessionistiDigitali\Connector\Enums\OutboxStatus;
use ITuoiProfessionistiDigitali\Connector\Jobs\SendPlatformProfessional;
use ITuoiProfessionistiDigitali\Connector\Models\PlatformProfessionalOutbox;
use ITuoiProfessionistiDigitali\Connector\Models\PlatformTransactionOutbox;

/**
 * Keeps the latest declaration of the record of every professional and sends it after the commit (rules R18 and R19).
 *
 * The platform answers 404 until it has a transaction of the system to the person. A first registration carries
 * the record of its sending, older than the declaration: so a 404 waits while the outbox of the transactions has
 * some to the person never confirmed, and the declaration leaves again when one is.
 */
final readonly class ProfessionalOutbox
{
    public function __construct(private ConnectorConfig $config) {}

    /**
     * Keeps the declaration unless the outbox has a newer one, or the same record: only the date would change.
     *
     * @throws ValidationException
     */
    public function declare(string $taxCode, ProfessionalRecordData $record): PlatformProfessionalOutbox
    {
        ProfessionalRecordData::check($taxCode, $record);

        $payload = $record->toWire();
        $row = PlatformProfessionalOutbox::query()->firstOrNew(['tax_code' => $taxCode]);

        if ($row->exists && ($this->isNewer($row, $record) || $this->withoutDate($row->payload) === $this->withoutDate($payload)))
        {
            return $row;
        }

        $row->fill([
            'payload' => $payload,
            'revision' => $row->exists ? $row->revision + 1 : 1,
            'status' => OutboxStatus::Pending,
            'last_error' => null,
        ])->save();

        $this->dispatch($taxCode);

        return $row;
    }

    /**
     * Sends again the declaration waiting for the person, if any: a transaction to it was just confirmed.
     */
    public function resume(string $taxCode): void
    {
        if (PlatformProfessionalOutbox::query()->where('tax_code', $taxCode)->where('status', OutboxStatus::Pending)->exists())
        {
            $this->dispatch($taxCode);
        }
    }

    /**
     * Whether the outbox of the transactions has some to the person that the platform never confirmed and still
     * may: a failed one does not count.
     */
    public function hasUnregisteredTransactions(string $taxCode): bool
    {
        return PlatformTransactionOutbox::query()
            ->where('status', OutboxStatus::Pending)
            ->whereNull('sent_revision')
            ->where('payload->counterparty->tax_code', $taxCode)
            ->exists();
    }

    private function dispatch(string $taxCode): void
    {
        dispatch(new SendPlatformProfessional($taxCode))->onQueue($this->config->queue)->afterCommit();
    }

    private function isNewer(PlatformProfessionalOutbox $row, ProfessionalRecordData $record): bool
    {
        $kept = $row->payload['declared_at'] ?? null;

        return is_string($kept) && CarbonImmutable::parse($kept)->greaterThan($record->declared_at);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withoutDate(array $payload): array
    {
        unset($payload['declared_at']);

        return $payload;
    }
}
