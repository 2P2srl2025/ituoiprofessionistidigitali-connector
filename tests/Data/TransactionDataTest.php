<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use ITuoiProfessionistiDigitali\Connector\Data\TransactionData;

it('R3, R5 and R8: accepts a valid transaction and sends it with its revision', function (): void {
    $wire = TransactionData::validateAndCreate(transaction())->toWire(revision: 3);

    expect($wire['revision'])->toBe(3)
        ->and($wire['invited_at'])->toBe('2026-10-06T18:00:00+02:00')
        ->and($wire['compensation'])->toBe(['form' => 'hourly', 'hourly_rate_cents' => 4500, 'fixed_amount_cents' => null, 'currency' => 'EUR'])
        ->and($wire['type'])->toBe('assignment')
        ->and($wire['schema_version'])->toBe(1);
});

it('sends an empty payload as a JSON object', function (): void {
    expect(json_encode(TransactionData::from(transaction(['payload' => []]))->toWire(1)['payload']))->toBe('{}');
});

it('R3, R5 and R8: refuses what the register would refuse', function (array $overrides, string $field): void {
    try
    {
        TransactionData::validateAndCreate(transaction($overrides));
        $this->fail('The transaction should not be valid.');
    }
    catch (ValidationException $exception)
    {
        expect($exception->errors())->toHaveKey($field);
    }
})->with([
    'member for a person, without id' => [['counterparty' => ['type' => 'member']], 'counterparty.id'],
    'person with an id' => [['counterparty' => ['type' => 'person', 'tax_code' => 'RSSMRA80A01H501U', 'name' => 'Mario Rossi', 'id' => '0199b6f0-4e2a-7b31-9f6c-2d8a1e5b7c43']], 'counterparty.id'],
    'wrong tax code' => [['counterparty' => ['type' => 'person', 'tax_code' => 'RSSMRA80A01H501A', 'name' => 'Mario Rossi']], 'counterparty.tax_code'],
    'both amounts' => [['compensation' => ['form' => 'hourly', 'hourly_rate_cents' => 4500, 'fixed_amount_cents' => 1, 'currency' => 'EUR']], 'compensation.fixed_amount_cents'],
    'fixed without amount' => [['compensation' => ['form' => 'fixed', 'currency' => 'EUR']], 'compensation.fixed_amount_cents'],
    'another currency' => [['compensation' => ['form' => 'hourly', 'hourly_rate_cents' => 4500, 'currency' => 'USD']], 'compensation.currency'],
    'negative minutes' => [['minutes_worked' => -1], 'minutes_worked'],
    'accepted without answer' => [['status' => 'accepted'], 'responded_at'],
    'completed without closing' => [['status' => 'completed', 'responded_at' => '2026-10-07T09:00:00Z'], 'closed_at'],
    'invited with an answer' => [['responded_at' => '2026-10-07T09:00:00Z'], 'responded_at'],
    'typology in upper case' => [['typology' => 'Commercialisti'], 'typology'],
]);

it('R8: checks the dates of an already built status', function (): void {
    TransactionData::validate(TransactionData::from(transaction(['status' => 'accepted', 'responded_at' => '2026-10-07T09:00:00Z']))->toArray());

    expect(true)->toBeTrue();
});

it('R8: reads the status also when it is given as an enum', function (): void {
    expect(fn () => TransactionData::validateAndCreate(transaction(['status' => ITuoiProfessionistiDigitali\Connector\Enums\TransactionStatus::Accepted])))
        ->toThrow(ValidationException::class);
});
