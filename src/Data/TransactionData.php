<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use ITuoiProfessionistiDigitali\Connector\Contract;
use ITuoiProfessionistiDigitali\Connector\Enums\CompensationForm;
use ITuoiProfessionistiDigitali\Connector\Enums\CounterpartyType;
use ITuoiProfessionistiDigitali\Connector\Enums\TransactionKind;
use ITuoiProfessionistiDigitali\Connector\Enums\TransactionStatus;
use ITuoiProfessionistiDigitali\Connector\Validation\Rules\ItalianTaxCode;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use stdClass;

/**
 * A transaction of the system as the register of the platform receives it, without its revision,
 * which the outbox of the package keeps (rules R3, R5, R6 and R8).
 */
final class TransactionData extends Data
{
    /**
     * @param  array{type: string, tax_code?: string|null, name?: string|null, id?: string|null}  $counterparty
     * @param  array{form: string, hourly_rate_cents?: int|null, fixed_amount_cents?: int|null, currency: string}  $compensation
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public TransactionKind $kind,
        public string $principal,
        public array $counterparty,
        public string $typology,
        public TransactionStatus $status,
        public array $compensation,
        public int $minutes_worked,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: Contract::DATE_FORMAT)]
        public CarbonImmutable $invited_at,
        public array $payload,
        public ?int $estimated_minutes = null,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: Contract::DATE_FORMAT)]
        public ?CarbonImmutable $responded_at = null,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: Contract::DATE_FORMAT)]
        public ?CarbonImmutable $closed_at = null,
        public string $type = 'assignment',
        public int $schema_version = 1,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $payload = is_array($context->payload) ? $context->payload : [];
        $status = self::status($payload['status'] ?? null);
        $counterparty = is_array($payload['counterparty'] ?? null) ? $payload['counterparty'] : [];
        $compensation = is_array($payload['compensation'] ?? null) ? $payload['compensation'] : [];
        $isPerson = ($counterparty['type'] ?? null) === CounterpartyType::Person->value;
        $isHourly = ($compensation['form'] ?? null) === CompensationForm::Hourly->value;
        $isAnswered = in_array($status, [TransactionStatus::Accepted, TransactionStatus::Declined, TransactionStatus::Completed], true);
        $isFinal = in_array($status, [TransactionStatus::Completed, TransactionStatus::Revoked], true);

        return [
            'principal' => ['required', 'uuid'],
            'counterparty' => ['required', 'array'],
            'counterparty.type' => ['required', Rule::enum(CounterpartyType::class)],
            'counterparty.tax_code' => [Rule::requiredIf($isPerson), Rule::prohibitedIf(!$isPerson), 'nullable', 'string', new ItalianTaxCode(personOnly: true)],
            'counterparty.name' => [Rule::requiredIf($isPerson), Rule::prohibitedIf(!$isPerson), 'nullable', 'string', 'max:255'],
            'counterparty.id' => [Rule::requiredIf(!$isPerson), Rule::prohibitedIf($isPerson), 'nullable', 'uuid'],
            'typology' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/'],
            'compensation' => ['required', 'array'],
            'compensation.form' => ['required', Rule::enum(CompensationForm::class)],
            'compensation.hourly_rate_cents' => [Rule::requiredIf($isHourly), Rule::prohibitedIf(!$isHourly), 'nullable', 'integer', 'min:0'],
            'compensation.fixed_amount_cents' => [Rule::requiredIf(!$isHourly), Rule::prohibitedIf($isHourly), 'nullable', 'integer', 'min:0'],
            'compensation.currency' => ['required', Rule::in(['EUR'])],
            'estimated_minutes' => ['nullable', 'integer', 'min:0'],
            'minutes_worked' => ['required', 'integer', 'min:0'],
            'responded_at' => [Rule::requiredIf($isAnswered), Rule::prohibitedIf($status === TransactionStatus::Invited)],
            'closed_at' => [Rule::requiredIf($isFinal), Rule::prohibitedIf($status !== null && !$isFinal)],
            'payload' => ['present', 'array'],
        ];
    }

    /**
     * The body of PUT /transactions/{reference}, with the revision kept by the outbox.
     *
     * @return array<string, mixed>
     */
    public function toWire(int $revision): array
    {
        /** @var array<string, mixed> $wire */
        $wire = $this->toArray();
        $wire['compensation'] = [
            'form' => $this->compensation['form'],
            'hourly_rate_cents' => $this->compensation['hourly_rate_cents'] ?? null,
            'fixed_amount_cents' => $this->compensation['fixed_amount_cents'] ?? null,
            'currency' => $this->compensation['currency'],
        ];
        $wire['payload'] = $this->payload === [] ? new stdClass : $this->payload;
        $wire['revision'] = $revision;

        return $wire;
    }

    private static function status(mixed $value): ?TransactionStatus
    {
        if ($value instanceof TransactionStatus)
        {
            return $value;
        }

        return is_string($value) ? TransactionStatus::tryFrom($value) : null;
    }
}
