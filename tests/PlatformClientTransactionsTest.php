<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use ITuoiProfessionistiDigitali\Connector\ConnectorConfig;
use ITuoiProfessionistiDigitali\Connector\Contract;
use ITuoiProfessionistiDigitali\Connector\Data\TransactionData;
use ITuoiProfessionistiDigitali\Connector\Enums\TransactionResult;
use ITuoiProfessionistiDigitali\Connector\Enums\TransactionStatus;
use ITuoiProfessionistiDigitali\Connector\Exceptions\PlatformRequestException;
use ITuoiProfessionistiDigitali\Connector\Facades\Platform;
use ITuoiProfessionistiDigitali\Connector\PlatformClient;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

function withToken(array $routes): void
{
    Http::fake(['platform.test/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]), ...$routes]);
}

it('R7: registers a transaction with its revision under its reference', function (): void {
    withToken(['platform.test/api/v1/transactions/*' => Http::response(['data' => recordedTransaction()], 201)]);

    $recorded = Platform::recordTransaction('incarico 1/a', TransactionData::from(transaction()), 2);

    expect($recorded->status)->toBe(TransactionStatus::Invited)->and($recorded->id)->toBe(recordedTransaction()['id']);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://platform.test/api/v1/transactions/incarico%201%2Fa'
        && $request->data()['revision'] === 2
        && $request->data()['principal'] === transaction()['principal']);
});

it('R6: does not send a payload that breaks the schema fixed in the package', function (): void {
    Http::fake();

    try
    {
        Platform::recordTransaction('incarico-1', TransactionData::from(transaction(['payload' => ['process' => ['name' => 'x', 'client' => 'Rossi'], 'activities' => []]])), 1);
        $this->fail('The payload should be refused.');
    }
    catch (PlatformRequestException $exception)
    {
        expect($exception->errors)->toHaveKey('payload.process');
        Http::assertNothingSent();
    }
});

it('R6: leaves the payload to the platform when the local validation is off or the schema is unknown', function (array $config, array $overrides): void {
    $this->app->instance(ConnectorConfig::class, ConnectorConfig::fromArray([...config('platform'), ...$config]));
    $this->app->forgetInstance(PlatformClient::class);
    Platform::clearResolvedInstances();
    withToken(['platform.test/api/v1/transactions/*' => Http::response(['data' => recordedTransaction()], 201)]);

    Platform::recordTransaction('incarico-1', TransactionData::from(transaction($overrides)), 1);

    Http::assertSentCount(2);
})->with([
    'validation off' => [['validate_payloads' => false], ['payload' => ['anything' => true]]],
    'schema not in the package' => [[], ['type' => 'loan', 'payload' => ['anything' => true]]],
]);

it('R7: reads the transaction already registered from a conflict', function (): void {
    withToken(['platform.test/api/v1/transactions/*' => Http::response(['message' => 'Revisione già registrata.', 'data' => recordedTransaction(['minutes_worked' => 30])], 409)]);

    try
    {
        Platform::recordTransaction('incarico-1', TransactionData::from(transaction()), 1);
        $this->fail('The platform should answer 409.');
    }
    catch (PlatformRequestException $exception)
    {
        expect($exception->isConflict())->toBeTrue()
            ->and($exception->existingTransaction?->minutes_worked)->toBe(30)
            ->and($exception->existingEvent)->toBeNull();
    }
});

it('R10: loads the history and reads each outcome', function (): void {
    withToken(['platform.test/api/v1/transactions/batch' => Http::response(['data' => [
        ['reference' => 'a', 'result' => 'created', 'transaction' => recordedTransaction(['reference' => 'a']), 'errors' => null],
        ['reference' => 'b', 'result' => 'invalid', 'transaction' => null, 'errors' => ['principal' => ['Non è un aderente.']]],
    ]])]);

    $outcomes = Platform::recordTransactions([
        ['reference' => 'a', 'revision' => 1, 'transaction' => TransactionData::from(transaction())],
        ['reference' => 'b', 'revision' => 1, 'transaction' => TransactionData::from(transaction())],
    ]);

    expect($outcomes[0]->result)->toBe(TransactionResult::Created)
        ->and($outcomes[0]->transaction?->reference)->toBe('a')
        ->and($outcomes[1]->result)->toBe(TransactionResult::Invalid)
        ->and($outcomes[1]->errors)->toBe(['principal' => ['Non è un aderente.']]);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/transactions/batch')
        && $request->data()['transactions'][1]['reference'] === 'b'
        && $request->data()['transactions'][1]['revision'] === 1);
});

it('R10: does not send more than 500 transactions', function (): void {
    Http::fake();
    $item = ['reference' => 'a', 'revision' => 1, 'transaction' => TransactionData::from(transaction())];

    expect(fn () => Platform::recordTransactions(array_fill(0, Contract::MAX_TRANSACTIONS + 1, $item)))
        ->toThrow(PlatformRequestException::class);
    Http::assertNothingSent();
});

it('R11: reads the transactions of the system with filters and cursor', function (): void {
    withToken(['platform.test/api/v1/transactions*' => Http::response([
        'data' => [recordedTransaction()],
        'meta' => ['next_cursor' => 'abc'],
    ])]);

    $page = Platform::transactions(status: 'accepted', kind: 'person_assignment', updatedSince: new DateTimeImmutable('2026-10-07T12:00:00+02:00'), perPage: 10, cursor: 'start');

    expect($page->transactions[0]->reference)->toBe('incarico-1')
        ->and($page->nextCursor)->toBe('abc')
        ->and($page->hasMore())->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://platform.test/api/v1/transactions?status=accepted&kind=person_assignment&updated_since=2026-10-07T12%3A00%3A00%2B02%3A00&per_page=10&cursor=start');
});

it('R11: tells when the last page is read', function (): void {
    withToken(['platform.test/api/v1/transactions*' => Http::response(['data' => [], 'meta' => ['next_cursor' => null]])]);

    expect(Platform::transactions()->hasMore())->toBeFalse();
});
