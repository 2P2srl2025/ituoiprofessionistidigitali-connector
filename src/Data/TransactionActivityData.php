<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use ITuoiProfessionistiDigitali\Connector\Contract;
use ITuoiProfessionistiDigitali\Connector\Enums\CounterpartyType;
use ITuoiProfessionistiDigitali\Connector\Enums\TransactionActivityStatus;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

/**
 * An activity of a transaction, with its own price and progress (rules R5, R6, R14 and R15).
 */
final class TransactionActivityData extends Data
{
    public function __construct(
        public string $reference,
        public CompensationData $compensation,
        public int $estimated_minutes,
        public TransactionActivityStatus $status,
        public ActivityDescriptionData $description,
        public ?int $minutes_worked = null,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: Contract::DATE_FORMAT)]
        public ?CarbonImmutable $closed_at = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $activity = is_array($context->payload) ? $context->payload : [];
        $status = TransactionActivityStatus::tryFrom(self::value($activity['status'] ?? null));
        $transaction = is_array($context->fullPayload) ? $context->fullPayload : [];
        $counterparty = is_array($transaction['counterparty'] ?? null) ? $transaction['counterparty'] : [];
        $isPerson = self::value($counterparty['type'] ?? null) === CounterpartyType::Person->value;

        return [
            'reference' => ['required', 'string', 'max:191'],
            'estimated_minutes' => ['required', 'integer', 'min:0'],
            // Only when an activity of a person ends: the hours of a firm stay with the firm (rule R5)
            'minutes_worked' => [
                'present',
                Rule::requiredIf($isPerson && $status === TransactionActivityStatus::Completed),
                Rule::prohibitedIf(!$isPerson || $status === TransactionActivityStatus::Open),
                'nullable', 'integer', 'min:0',
            ],
            'closed_at' => ['present', Rule::requiredIf($status?->isClosed() === true), Rule::prohibitedIf($status === TransactionActivityStatus::Open)],
        ];
    }

    /**
     * The backing value of a field given as a string or as an enum.
     */
    public static function value(mixed $value): string
    {
        return match (true)
        {
            $value instanceof BackedEnum => (string) $value->value,
            is_string($value) => $value,
            default => '',
        };
    }

    /**
     * The total of the activity, as the platform computes it (rule R15).
     */
    public function totalCents(): int
    {
        return $this->compensation->totalCents($this->estimated_minutes);
    }

    /**
     * The activity as PUT /transactions/{reference} carries it.
     *
     * @return array<string, mixed>
     */
    public function toWire(): array
    {
        return [
            'reference' => $this->reference,
            'compensation' => $this->compensation->toWire(),
            'estimated_minutes' => $this->estimated_minutes,
            'minutes_worked' => $this->minutes_worked,
            'status' => $this->status->value,
            'closed_at' => $this->closed_at?->format(Contract::DATE_FORMAT),
            'description' => $this->description->toWire(),
        ];
    }
}
