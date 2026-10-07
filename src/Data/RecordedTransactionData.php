<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use Carbon\CarbonImmutable;
use ITuoiProfessionistiDigitali\Connector\Contract;
use ITuoiProfessionistiDigitali\Connector\Enums\TransactionKind;
use ITuoiProfessionistiDigitali\Connector\Enums\TransactionStatus;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

/**
 * A transaction as the register of the platform returns it (rule R11).
 */
final class RecordedTransactionData extends Data
{
    /**
     * @param  array<string, mixed>  $counterparty
     * @param  array<string, mixed>  $compensation
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $id,
        public string $origin,
        public ?string $reference,
        public TransactionKind $kind,
        public string $principal,
        public array $counterparty,
        public string $typology,
        public TransactionStatus $status,
        public array $compensation,
        public ?int $estimated_minutes,
        public int $minutes_worked,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        public CarbonImmutable $invited_at,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        public ?CarbonImmutable $responded_at,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        public ?CarbonImmutable $closed_at,
        public int $revision,
        public string $type,
        public int $schema_version,
        public array $payload,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        public ?CarbonImmutable $updated_at,
    ) {}
}
