<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use ITuoiProfessionistiDigitali\Connector\Data\MemberData;
use ITuoiProfessionistiDigitali\Connector\Exceptions\ConcurrentMemberSyncException;
use ITuoiProfessionistiDigitali\Connector\Exceptions\MemberEmailsRejectedException;
use ITuoiProfessionistiDigitali\Connector\Exceptions\PlatformRequestException;
use ITuoiProfessionistiDigitali\Connector\Facades\Platform;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

/**
 * The platform refusing PUT /members with these errors, after the token.
 *
 * @param  array<string, list<string>>  $errors
 */
function platformRefusingMembers(array $errors, int $status = 422): void
{
    withToken(['platform.test/api/v1/members' => Http::response(['message' => 'Dati non validi.', 'errors' => $errors], $status)]);
}

/**
 * @return list<MemberData>
 */
function twoMembers(): array
{
    return [
        MemberData::validateAndCreate(member()),
        MemberData::validateAndCreate(member(['external_ref' => 'struttura-2', 'email' => 'info@studiobianchi.example'])),
    ];
}

it('M15: turns the refusal of the whole list, another request at the same moment, into its own exception to retry', function (): void {
    platformRefusingMembers(['members' => ['Un’altra richiesta ha cambiato gli aderenti nello stesso momento: riprova.']]);

    try
    {
        Platform::syncMembers(twoMembers());
        $this->fail('The request should meet another one.');
    }
    catch (ConcurrentMemberSyncException $exception)
    {
        expect($exception->getPrevious())->toBeInstanceOf(PlatformRequestException::class)
            ->and($exception->getPrevious()?->status)->toBe(422)
            ->and($exception->getPrevious()?->errors)->toHaveKey('members');
    }
});

it('M15: turns the refused emails into their own exception, by external_ref of the request', function (): void {
    platformRefusingMembers([
        'members.1.email' => ['L’email di struttura-2 è già di un altro aderente.'],
        'members.0.email' => ['L’email di struttura-1 è di uno studio registrato sul sito.'],
    ]);

    try
    {
        Platform::syncMembers(twoMembers());
        $this->fail('The emails should be refused.');
    }
    catch (MemberEmailsRejectedException $exception)
    {
        expect($exception->messages)->toBe([
            'struttura-2' => ['L’email di struttura-2 è già di un altro aderente.'],
            'struttura-1' => ['L’email di struttura-1 è di uno studio registrato sul sito.'],
        ])
            ->and($exception->getPrevious())->toBeInstanceOf(PlatformRequestException::class)
            ->and($exception->getPrevious()?->status)->toBe(422);
    }
});

it('keeps the other refusals of the members as they are', function (array $errors, int $status): void {
    platformRefusingMembers($errors, $status);

    expect(fn () => Platform::syncMembers(twoMembers()))
        ->toThrow(fn (PlatformRequestException $exception) => expect($exception->status)->toBe($status)
            ->and($exception->errors)->toBe($errors));
})->with([
    'an email and another field' => [['members.0.email' => ['Già usata.'], 'members.0.typologies.0' => ['Tipologia non attiva.']], 422],
    'the whole list and an email' => [['members' => ['Riprova.'], 'members.1.email' => ['Già usata.']], 422],
    'an email of a member not in the request' => [['members.2.email' => ['Già usata.']], 422],
    'another field only' => [['members.0.typologies.0' => ['Tipologia non attiva.']], 422],
    'no errors' => [[], 422],
    'the whole list, not as a contract violation' => [['members' => ['Riprova.']], 400],
]);
