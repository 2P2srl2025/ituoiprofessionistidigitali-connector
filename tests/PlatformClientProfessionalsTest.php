<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use ITuoiProfessionistiDigitali\Connector\Data\ProfessionalRecordData;
use ITuoiProfessionistiDigitali\Connector\Exceptions\PlatformRequestException;
use ITuoiProfessionistiDigitali\Connector\Exceptions\ProfessionalNotAssignedException;
use ITuoiProfessionistiDigitali\Connector\Facades\Platform;

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->travelTo(CarbonImmutable::parse('2026-10-08T10:20:00+02:00'));
});

it('R18: declares the complete record under the tax code', function (): void {
    platformAnsweringProfessionals(204);

    Platform::declareProfessional('RSSMRA80A01H501U', ProfessionalRecordData::from(professional()));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://platform.test/api/v1/professionals/RSSMRA80A01H501U'
        && $request->data() === professional());
});

it('T6: declares the email in lower case', function (): void {
    platformAnsweringProfessionals(204);

    Platform::declareProfessional('RSSMRA80A01H501U', ProfessionalRecordData::from(professional(['email' => 'Mario.Rossi@Example.com'])));

    Http::assertSent(fn (Request $request): bool => $request->data() === professional());
});

it('R18: tells a professional the system never assigned from the other errors', function (): void {
    platformAnsweringProfessionals(404, ['message' => 'Professionista non trovato.']);

    try
    {
        Platform::declareProfessional('RSSMRA80A01H501U', ProfessionalRecordData::from(professional()));
        $this->fail('The declaration should not succeed.');
    }
    catch (ProfessionalNotAssignedException $exception)
    {
        expect($exception->taxCode)->toBe('RSSMRA80A01H501U');
    }
});

it('throws the other refusals of the platform as they are', function (): void {
    platformAnsweringProfessionals(403, ['message' => 'Sistema sospeso.', 'reason' => 'suspended']);

    expect(fn () => Platform::declareProfessional('RSSMRA80A01H501U', ProfessionalRecordData::from(professional())))
        ->toThrow(PlatformRequestException::class, 'Sistema sospeso.');
});

it('does not send what the platform would refuse', function (string $taxCode, array $record): void {
    Http::fake();

    expect(fn () => Platform::declareProfessional($taxCode, ProfessionalRecordData::from($record)))->toThrow(ValidationException::class);

    Http::assertNothingSent();
})->with([
    'the tax code of an organization' => ['01234567897', professional()],
    'a wrong check character' => ['RSSMRA80A01H501A', professional()],
    'a wrong vat number' => ['RSSMRA80A01H501U', professional(['vat_number' => '01234567890'])],
]);
