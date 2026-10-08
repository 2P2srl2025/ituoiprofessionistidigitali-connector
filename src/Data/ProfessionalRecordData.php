<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use ITuoiProfessionistiDigitali\Connector\Contract;
use ITuoiProfessionistiDigitali\Connector\Validation\Rules\ItalianTaxCode;
use ITuoiProfessionistiDigitali\Connector\Validation\Rules\ItalianVatNumber;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

/**
 * The record of a professional as the system declares it when it changes, dated by the change (rules R18 and R19).
 * It is always complete: an optional field left null removes the data on the platform.
 * The email is kept in lower case, as the platform stores and returns it (T6).
 */
final class ProfessionalRecordData extends Data
{
    public function __construct(
        public string $first_name,
        public string $last_name,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: Contract::DATE_FORMAT)]
        public CarbonImmutable $declared_at,
        public string $email,
        public ?string $vat_number = null,
        public ?string $municipality = null,
        public ?string $province = null,
    ) {
        $this->email = mb_strtolower($email);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $record = is_array($context->payload) ? $context->payload : [];
        // An optional field given empty is an error, not a missing value
        $optional = static fn (string $field, mixed ...$rules): array => [Rule::requiredIf(($record[$field] ?? null) === ''), 'nullable', ...array_values($rules)];

        $details = self::detailRules();

        return [
            'first_name' => ['required', ...$details['first_name']],
            'last_name' => ['required', ...$details['last_name']],
            'email' => ['required', ...$details['email']],
            'vat_number' => $optional('vat_number', ...$details['vat_number']),
            'municipality' => $optional('municipality', ...$details['municipality']),
            'province' => $optional('province', ...$details['province']),
            'declared_at' => ['required', 'date', 'before_or_equal:'.CarbonImmutable::now()->addSeconds(Contract::DECLARATION_TOLERANCE_SECONDS)->format(Contract::DATE_FORMAT)],
        ];
    }

    /**
     * The form of the details of a person, the same in the record and in the counterparty of a transaction
     * (rules R3 and R18). Whether a field is required is up to each of them.
     *
     * @internal
     *
     * @return array{first_name: list<mixed>, last_name: list<mixed>, email: list<mixed>, vat_number: list<mixed>, municipality: list<mixed>, province: list<mixed>}
     */
    public static function detailRules(): array
    {
        return [
            'first_name' => ['string', 'max:255'],
            'last_name' => ['string', 'max:255'],
            'email' => ['string', 'email', 'max:255'],
            'vat_number' => ['string', new ItalianVatNumber],
            'municipality' => ['string', 'max:255'],
            'province' => ['string', 'regex:/^[A-Z]{2}$/'],
        ];
    }

    /**
     * What the platform checks in PUT /professionals/{tax_code}: the tax code of a person in the route, and the record.
     *
     * @throws ValidationException
     */
    public static function check(string $taxCode, self $record): void
    {
        validator(['tax_code' => $taxCode], ['tax_code' => ['required', 'string', new ItalianTaxCode(personOnly: true)]])->validate();

        self::validate($record->toWire());
    }

    /**
     * The body of PUT /professionals/{tax_code}, with every key.
     *
     * @return array{first_name: string, last_name: string, email: string, vat_number: string|null, municipality: string|null, province: string|null, declared_at: string}
     */
    public function toWire(): array
    {
        return [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'vat_number' => $this->vat_number,
            'municipality' => $this->municipality,
            'province' => $this->province,
            'declared_at' => $this->declared_at->format(Contract::DATE_FORMAT),
        ];
    }
}
