<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use ITuoiProfessionistiDigitali\Connector\Data\RecordedTransactionData;
use ITuoiProfessionistiDigitali\Connector\Enums\ApplicationStatus;
use ITuoiProfessionistiDigitali\Connector\Enums\OutboxStatus;
use ITuoiProfessionistiDigitali\Connector\Enums\TransactionStatus;
use ITuoiProfessionistiDigitali\Connector\Exceptions\ApplicationNotSelectableException;
use ITuoiProfessionistiDigitali\Connector\Exceptions\PlatformRequestException;
use ITuoiProfessionistiDigitali\Connector\Exceptions\TransactionNotFoundException;
use ITuoiProfessionistiDigitali\Connector\Exceptions\TransactionNotPublishedException;
use ITuoiProfessionistiDigitali\Connector\Facades\Platform;
use ITuoiProfessionistiDigitali\Connector\Models\PlatformTransactionOutbox;
use ITuoiProfessionistiDigitali\Connector\Outbox\TransactionOutbox;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Queue::fake();
});

/**
 * The platform answering POST /transactions/{reference}/selection with this status and body, after the token.
 */
function platformSelecting(int $status, array $body): void
{
    withToken(['platform.test/api/v1/transactions/*/selection' => Http::response($body, $status)]);
}

it('L7: reads the applications of a transaction of the system, with filter and cursor', function (): void {
    withToken(['platform.test/api/v1/transactions/*/applications*' => Http::response([
        'data' => [application(), application(['id' => '0199b6f9-0000-7000-8000-000000000002', 'applicant' => [...application()['applicant'], 'tax_code_verified' => true]])],
        'links' => [],
        'meta' => ['next_cursor' => 'abc', 'per_page' => 2],
    ])]);

    $page = Platform::applications('invio 1/a', ApplicationStatus::Pending, perPage: 2, cursor: 'xyz');

    expect($page->applications)->toHaveCount(2)
        ->and($page->applications[1]->applicant->tax_code_verified)->toBeTrue()
        ->and($page->nextCursor)->toBe('abc')
        ->and($page->hasMore())->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://platform.test/api/v1/transactions/invio%201%2Fa/applications?status=pending&per_page=2&cursor=xyz');
});

it('L7: reads the last page of applications, without filters', function (): void {
    withToken(['platform.test/api/v1/transactions/invio-1/applications' => Http::response(['data' => [], 'meta' => ['next_cursor' => null]])]);

    $page = Platform::applications('invio-1');

    expect($page->applications)->toBe([])
        ->and($page->hasMore())->toBeFalse();
});

it('L4: turns the 404 of a reference that is not of the system into its own exception', function (Closure $call): void {
    withToken(['platform.test/api/v1/transactions/*' => Http::response(['message' => 'Not found.'], 404)]);

    try
    {
        $call();
        $this->fail('The request should not succeed.');
    }
    catch (TransactionNotFoundException $exception)
    {
        expect($exception->reference)->toBe('invio-1');
    }
})->with([
    'applications' => [fn () => Platform::applications('invio-1')],
    'selection' => [fn () => Platform::selectApplication('invio-1', application()['id'])],
]);

it('keeps the other refusals of the applications as they are', function (): void {
    withToken(['platform.test/api/v1/transactions/*' => Http::response(['message' => 'Sistema in attesa.', 'reason' => 'pending'], 403)]);

    Platform::applications('invio-1');
})->throws(PlatformRequestException::class, 'Sistema in attesa.');

it('L4: selects an application and answers with the transaction accepted at the revision of the platform', function (): void {
    platformSelecting(200, ['data' => selectedTransaction()]);

    $transaction = Platform::selectApplication('invio 1/a', application()['id']);

    expect($transaction)
        ->status->toBe(TransactionStatus::Accepted)
        ->revision->toBe(2)
        ->and($transaction->counterparty?->email)->toBe('mario.rossi@example.com');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://platform.test/api/v1/transactions/invio%201%2Fa/selection'
        && $request->data() === ['application' => application()['id']]);
});

it('L4: keeps the selection in the outbox, so the next change of the model goes out with a higher revision', function (): void {
    $assignment = confirmedAssignment();
    platformSelecting(200, ['data' => selectedTransaction(['reference' => $assignment->uuid])]);

    Platform::selectApplication((string) $assignment->uuid, application()['id']);

    expect(PlatformTransactionOutbox::query()->sole())
        ->revision->toBe(2)
        ->sent_revision->toBe(2)
        ->status->toBe(OutboxStatus::Sent)
        ->payload->toBe(resolve(TransactionOutbox::class)->payloadOf(RecordedTransactionData::from(selectedTransaction())->toTransaction()));

    $assignment->update(['status' => 'accepted']);

    expect(PlatformTransactionOutbox::query()->sole())->revision->toBe(3)->sent_revision->toBe(2)->status->toBe(OutboxStatus::Pending);
});

it('L4: turns the refusals of the selection into their own exceptions, with the messages of the platform', function (string $field, string $class): void {
    platformSelecting(422, ['message' => 'Dati non validi.', 'errors' => [$field => ['Non si può.']]]);

    try
    {
        Platform::selectApplication('invio-1', application()['id']);
        $this->fail('The selection should not succeed.');
    }
    catch (ApplicationNotSelectableException|TransactionNotPublishedException $exception)
    {
        expect($exception)->toBeInstanceOf($class)
            ->and($exception->reference)->toBe('invio-1')
            ->and($exception->messages)->toBe(['Non si può.'])
            ->and($exception->getPrevious())->toBeInstanceOf(PlatformRequestException::class);
    }
})->with([
    'application not pending' => ['application', ApplicationNotSelectableException::class],
    'transaction withdrawn' => ['status', TransactionNotPublishedException::class],
]);

it('L4: names the application that cannot be selected', function (): void {
    platformSelecting(422, ['message' => 'Dati non validi.', 'errors' => ['application' => ['Non è in attesa.']]]);

    expect(fn () => Platform::selectApplication('invio-1', 'altra'))
        ->toThrow(fn (ApplicationNotSelectableException $exception) => expect($exception->applicationId)->toBe('altra'));
});

it('keeps the other refusals of the selection as they are', function (int $status, array $errors): void {
    platformSelecting($status, ['message' => 'Rifiutata.', 'errors' => $errors]);

    expect(fn () => Platform::selectApplication('invio-1', application()['id']))->toThrow(PlatformRequestException::class, 'Rifiutata.');
})->with([
    'two fields' => [422, ['application' => ['x'], 'status' => ['y']]],
    'another field' => [422, ['reference' => ['x']]],
    'forbidden' => [403, []],
]);
