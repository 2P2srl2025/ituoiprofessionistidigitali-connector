<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use Illuminate\Validation\Rule;
use ITuoiProfessionistiDigitali\Connector\Enums\CompensationForm;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * The price of an activity: one amount, the one of its form, in cents of the currency of the transaction (rule R5).
 */
final class CompensationData extends Data
{
    public function __construct(
        public CompensationForm $form,
        public ?int $hourly_rate_cents = null,
        public ?int $fixed_amount_cents = null,
    ) {}

    public static function hourly(int $rateCents): self
    {
        return new self(CompensationForm::Hourly, hourly_rate_cents: $rateCents);
    }

    public static function fixed(int $amountCents): self
    {
        return new self(CompensationForm::Fixed, fixed_amount_cents: $amountCents);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $compensation = is_array($context->payload) ? $context->payload : [];
        $isHourly = TransactionActivityData::value($compensation['form'] ?? null) === CompensationForm::Hourly->value;

        return [
            'form' => ['required', Rule::enum(CompensationForm::class)],
            'hourly_rate_cents' => [Rule::requiredIf($isHourly), Rule::prohibitedIf(!$isHourly), 'nullable', 'integer', 'min:0'],
            'fixed_amount_cents' => [Rule::requiredIf(!$isHourly), Rule::prohibitedIf($isHourly), 'nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * The fixed amount, or the minutes at the hourly rate rounded to the cent with the half up,
     * as the platform computes it (rule R15).
     */
    public function totalCents(int $minutes): int
    {
        return $this->hourly_rate_cents === null
            ? $this->fixed_amount_cents ?? 0
            : intdiv($minutes * $this->hourly_rate_cents + 30, 60);
    }

    /**
     * @return array{form: string, hourly_rate_cents: int|null, fixed_amount_cents: int|null}
     */
    public function toWire(): array
    {
        return ['form' => $this->form->value, 'hourly_rate_cents' => $this->hourly_rate_cents, 'fixed_amount_cents' => $this->fixed_amount_cents];
    }
}
