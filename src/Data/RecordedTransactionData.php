<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use Carbon\CarbonImmutable;
use ITuoiProfessionistiDigitali\Connector\Contract;
use ITuoiProfessionistiDigitali\Connector\Enums\Audience;
use ITuoiProfessionistiDigitali\Connector\Enums\TransactionStatus;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

/**
 * A transaction as the register of the platform returns it (rule R11).
 */
final class RecordedTransactionData extends Data
{
    /**
     * @param  list<RecordedTransactionActivityData>  $activities
     */
    public function __construct(
        public string $id,
        public string $origin,
        public ?string $reference,
        public ?string $assignment_reference,
        public Audience $audience,
        public string $principal,
        public ?CounterpartyData $counterparty,
        public string $typology,
        public string $title,
        public string $description,
        public TransactionStatus $status,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        public CarbonImmutable $sent_at,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        public ?CarbonImmutable $expires_at,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        public ?CarbonImmutable $responded_at,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        public ?CarbonImmutable $signed_at,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        public ?CarbonImmutable $closed_at,
        public string $currency,
        public int $total_cents,
        public int $revision,
        public string $type,
        public int $schema_version,
        #[DataCollectionOf(RecordedTransactionActivityData::class)]
        public array $activities,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        public ?CarbonImmutable $updated_at,
    ) {}

    /**
     * The transaction as the system sends it again, for instance after the selection of the counterparty on the
     * platform: the same counterparty, texts, prices and dates (rules R13 and L4).
     */
    public function toTransaction(): TransactionData
    {
        return new TransactionData(
            assignment_reference: (string) $this->assignment_reference,
            audience: $this->audience,
            principal: $this->principal,
            counterparty: $this->counterparty,
            typology: $this->typology,
            title: $this->title,
            description: $this->description,
            status: $this->status,
            sent_at: $this->sent_at,
            activities: array_map(static fn (RecordedTransactionActivityData $activity): TransactionActivityData => $activity->toActivity(), $this->activities),
            expires_at: $this->expires_at,
            responded_at: $this->responded_at,
            signed_at: $this->signed_at,
            closed_at: $this->closed_at,
            currency: $this->currency,
            type: $this->type,
            schema_version: $this->schema_version,
        );
    }
}
