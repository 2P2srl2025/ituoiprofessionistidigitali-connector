<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use ITuoiProfessionistiDigitali\Connector\Concerns\RecordsOnPlatform;
use ITuoiProfessionistiDigitali\Connector\Contracts\RecordsPlatformTransaction;
use ITuoiProfessionistiDigitali\Connector\Data\TransactionData;

/**
 * An external assignment as a system would keep it: every save must reach the register.
 *
 * @property int $id
 * @property string $uuid
 * @property string $status
 * @property int $minutes
 */
final class Assignment extends Model implements RecordsPlatformTransaction
{
    use RecordsOnPlatform;

    public const string PRINCIPAL = '0199b6f0-4e2a-7b31-9f6c-2d8a1e5b7c43';

    protected $guarded = ['id'];

    protected $attributes = ['minutes' => 0];

    public function platformTransactionReference(): string
    {
        return $this->uuid;
    }

    public function toPlatformTransaction(): TransactionData
    {
        return TransactionData::from([
            'kind' => 'person_assignment',
            'principal' => self::PRINCIPAL,
            'counterparty' => ['type' => 'person', 'tax_code' => 'RSSMRA80A01H501U', 'name' => 'Mario Rossi'],
            'typology' => 'commercialisti',
            'status' => $this->status,
            'compensation' => ['form' => 'hourly', 'hourly_rate_cents' => 4500, 'currency' => 'EUR'],
            'minutes_worked' => $this->minutes,
            'invited_at' => '2026-10-06T18:00:00+02:00',
            'responded_at' => $this->status === 'invited' ? null : '2026-10-07T09:00:00+02:00',
            'payload' => ['process' => ['name' => 'Contabilità ordinaria'], 'activities' => [['name' => 'Registrazione fatture']]],
        ]);
    }
}
