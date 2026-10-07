<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use ITuoiProfessionistiDigitali\Connector\Enums\OutboxStatus;

/**
 * The last version of a transaction to send to the register, and what the platform answered.
 *
 * @property int $id
 * @property string $reference
 * @property int $revision
 * @property int|null $sent_revision
 * @property array<string, mixed> $payload
 * @property OutboxStatus $status
 * @property int $attempts
 * @property string|null $last_error
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class PlatformTransactionOutbox extends Model
{
    protected $table = 'platform_transaction_outbox';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'sent_revision' => 'integer',
            'payload' => 'array',
            'status' => OutboxStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
