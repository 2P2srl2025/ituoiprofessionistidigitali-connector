<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use Illuminate\Validation\Rule;
use ITuoiProfessionistiDigitali\Connector\Enums\CounterpartyType;
use ITuoiProfessionistiDigitali\Connector\Validation\Rules\ItalianTaxCode;
use ITuoiProfessionistiDigitali\Connector\Validation\Rules\ItalianVatNumber;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * Who receives the work: a professional with the details the platform creates or updates by tax code,
 * or a member of the same system by its id (rule R3).
 */
final class CounterpartyData extends Data
{
    /**
     * The fields of a person: none of them belongs to a member.
     */
    public const array PERSON_FIELDS = ['tax_code', 'first_name', 'last_name', 'email', 'vat_number', 'municipality', 'province'];

    public function __construct(
        public CounterpartyType $type,
        public ?string $tax_code = null,
        public ?string $first_name = null,
        public ?string $last_name = null,
        public ?string $email = null,
        public ?string $vat_number = null,
        public ?string $municipality = null,
        public ?string $province = null,
        public ?string $id = null,
    ) {}

    public static function person(
        string $taxCode,
        string $firstName,
        string $lastName,
        ?string $email = null,
        ?string $vatNumber = null,
        ?string $municipality = null,
        ?string $province = null,
    ): self {
        return new self(
            CounterpartyType::Person,
            tax_code: $taxCode,
            first_name: $firstName,
            last_name: $lastName,
            email: $email,
            vat_number: $vatNumber,
            municipality: $municipality,
            province: $province,
        );
    }

    public static function member(string $memberId): self
    {
        return new self(CounterpartyType::Member, id: $memberId);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $counterparty = is_array($context->payload) ? $context->payload : [];
        $isPerson = TransactionActivityData::value($counterparty['type'] ?? null) === CounterpartyType::Person->value;
        // An optional field of a person given empty is an error, not a missing value
        $person = static fn (string $field, mixed ...$rules): array => self::personRules($counterparty, $isPerson, $field, $rules);

        return [
            'type' => ['required', Rule::enum(CounterpartyType::class)],
            'tax_code' => [Rule::requiredIf($isPerson), ...$person('tax_code', 'string', new ItalianTaxCode(personOnly: true))],
            'first_name' => [Rule::requiredIf($isPerson), ...$person('first_name', 'string', 'max:255')],
            'last_name' => [Rule::requiredIf($isPerson), ...$person('last_name', 'string', 'max:255')],
            'email' => $person('email', 'string', 'email', 'max:255'),
            'vat_number' => $person('vat_number', 'string', new ItalianVatNumber),
            'municipality' => $person('municipality', 'string', 'max:255'),
            'province' => $person('province', 'string', 'regex:/^[A-Z]{2}$/'),
            'id' => [Rule::requiredIf(!$isPerson), Rule::prohibitedIf($isPerson), 'nullable', 'uuid'],
        ];
    }

    /**
     * The counterparty as PUT /transactions/{reference} carries it: only the keys of its type.
     *
     * @return array<string, string|null>
     */
    public function toWire(): array
    {
        return $this->type === CounterpartyType::Person
            ? [
                'type' => $this->type->value,
                'tax_code' => $this->tax_code,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->email,
                'vat_number' => $this->vat_number,
                'municipality' => $this->municipality,
                'province' => $this->province,
            ]
            : ['type' => $this->type->value, 'id' => $this->id];
    }

    /**
     * The rules of a field of a person: forbidden for a member, and an error when given empty.
     *
     * @param  array<array-key, mixed>  $counterparty
     * @param  array<array-key, mixed>  $rules
     * @return list<mixed>
     */
    private static function personRules(array $counterparty, bool $isPerson, string $field, array $rules): array
    {
        return [Rule::requiredIf(($counterparty[$field] ?? null) === ''), Rule::prohibitedIf(!$isPerson), 'nullable', ...array_values($rules)];
    }
}
