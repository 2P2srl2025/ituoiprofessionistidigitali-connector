<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use ITuoiProfessionistiDigitali\Connector\Data\ProfessionalRecordData;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-08T10:20:00+02:00'));
});

it('R18: puts every key on the wire, with the date in RFC 3339', function (): void {
    $record = new ProfessionalRecordData(
        first_name: 'Mario',
        last_name: 'Rossi',
        declared_at: CarbonImmutable::parse('2026-10-08T08:15:00Z'),
    );

    expect($record->toWire())->toBe([
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'email' => null,
        'vat_number' => null,
        'municipality' => null,
        'province' => null,
        'declared_at' => '2026-10-08T08:15:00+00:00',
    ]);
});

it('R18: reads the body it writes', function (): void {
    $record = ProfessionalRecordData::from(professional());

    expect($record->toWire())->toBe(professional())
        ->and($record->declared_at->equalTo(CarbonImmutable::parse('2026-10-08T08:15:00Z')))->toBeTrue();
});

it('R18: checks the tax code of the route and the record', function (): void {
    ProfessionalRecordData::check('RSSMRA80A01H501U', ProfessionalRecordData::from(professional()));

    expect(fn () => ProfessionalRecordData::check('01234567897', ProfessionalRecordData::from(professional())))
        ->toThrow(ValidationException::class)
        ->and(fn () => ProfessionalRecordData::check('RSSMRA80A01H501U', ProfessionalRecordData::from(professional(['province' => 'le']))))
        ->toThrow(ValidationException::class);
});

it('refuses what the platform would refuse', function (array $body, string $field): void {
    try
    {
        ProfessionalRecordData::validateAndCreate($body);
        $this->fail('The record should not be valid.');
    }
    catch (ValidationException $exception)
    {
        expect($exception->errors())->toHaveKey($field);
    }
})->with([
    'without first name' => [professional(['first_name' => null]), 'first_name'],
    'empty last name' => [professional(['last_name' => '']), 'last_name'],
    'last name too long' => [professional(['last_name' => str_repeat('a', 256)]), 'last_name'],
    'wrong email' => [professional(['email' => 'mario']), 'email'],
    'empty email' => [professional(['email' => '']), 'email'],
    'wrong vat number' => [professional(['vat_number' => '01234567890']), 'vat_number'],
    'empty municipality' => [professional(['municipality' => '']), 'municipality'],
    'province in lower case' => [professional(['province' => 'le']), 'province'],
    'without date' => [professional(['declared_at' => null]), 'declared_at'],
    'R19 more than five minutes ahead' => [professional(['declared_at' => '2026-10-08T10:25:01+02:00']), 'declared_at'],
]);

it('R19: accepts a date up to five minutes ahead', function (): void {
    ProfessionalRecordData::validate(professional(['declared_at' => '2026-10-08T10:25:00+02:00']));

    expect(ProfessionalRecordData::validateAndCreate(professional())->last_name)->toBe('Rossi');
});
