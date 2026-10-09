<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use Carbon\CarbonImmutable;
use ITuoiProfessionistiDigitali\Connector\Contract;
use ITuoiProfessionistiDigitali\Connector\Enums\TransactionActivityStatus;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

/**
 * An activity of a transaction as the register returns it, with the total the platform computed (rule R15).
 */
final class RecordedTransactionActivityData extends Data
{
    /**
     * @param  array<string, mixed>  $compensation
     * @param  array<string, mixed>  $description
     */
    public function __construct(
        public string $reference,
        public array $compensation,
        public int $estimated_minutes,
        public ?int $minutes_worked,
        public TransactionActivityStatus $status,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        public ?CarbonImmutable $closed_at,
        public int $total_cents,
        public array $description,
    ) {}

    /**
     * The activity as the system sends it again, without the total the platform computes.
     */
    public function toActivity(): TransactionActivityData
    {
        return new TransactionActivityData(
            reference: $this->reference,
            compensation: CompensationData::from($this->compensation),
            estimated_minutes: $this->estimated_minutes,
            status: $this->status,
            description: ActivityDescriptionData::from($this->description),
            minutes_worked: $this->minutes_worked,
            closed_at: $this->closed_at,
        );
    }
}
