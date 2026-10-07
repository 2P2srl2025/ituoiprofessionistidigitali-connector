<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use ITuoiProfessionistiDigitali\Connector\ConnectorConfig;
use ITuoiProfessionistiDigitali\Connector\Enums\OutboxStatus;
use ITuoiProfessionistiDigitali\Connector\Exceptions\PlatformRequestException;
use ITuoiProfessionistiDigitali\Connector\Jobs\SendPlatformTransaction;
use ITuoiProfessionistiDigitali\Connector\Models\PlatformTransactionOutbox;
use ITuoiProfessionistiDigitali\Connector\Tests\Fixtures\Assignment;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
    Http::preventStrayRequests();
    $this->assignment = Assignment::query()->create(['uuid' => (string) Str::uuid7(), 'status' => 'invited']);
    $this->row = PlatformTransactionOutbox::query()->sole();
});

function send(string $reference): void
{
    resolve(SendPlatformTransaction::class, ['reference' => $reference])->handle(
        resolve(ITuoiProfessionistiDigitali\Connector\PlatformClient::class),
        resolve(ConnectorConfig::class),
    );
}

function platformAnsweringTransactions(int $status, array $body = []): void
{
    Http::fake([
        'platform.test/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
        'platform.test/api/v1/transactions/*' => Http::response($body, $status),
    ]);
}

it('R7: sends the last version with its revision and marks it confirmed', function (): void {
    platformAnsweringTransactions(201, ['data' => recordedTransaction()]);

    send($this->assignment->uuid);

    expect($this->row->refresh())
        ->status->toBe(OutboxStatus::Sent)
        ->sent_revision->toBe(1)
        ->attempts->toBe(1)
        ->last_error->toBeNull()
        ->sent_at->not->toBeNull()
        ->and($this->assignment->isRecordedOnPlatform())->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && $request->data()['revision'] === 1);
});

it('R7: leaves pending a newer revision that arrived while sending', function (): void {
    Http::fake([
        'platform.test/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
        'platform.test/api/v1/transactions/*' => function () {
            PlatformTransactionOutbox::query()->update(['revision' => 2]);

            return Http::response(['data' => recordedTransaction()], 201);
        },
    ]);

    send($this->assignment->uuid);

    expect($this->row->refresh())->sent_revision->toBe(1)->status->toBe(OutboxStatus::Pending);
});

it('stops on a contract violation or a conflict, keeping the error', function (int $status): void {
    platformAnsweringTransactions($status, ['message' => 'Rifiutata.', 'errors' => ['principal' => ['Non è un aderente.']]]);

    send($this->assignment->uuid);

    expect($this->row->refresh())
        ->status->toBe(OutboxStatus::Failed)
        ->sent_revision->toBeNull()
        ->last_error->toContain('Rifiutata.')
        ->last_error->toContain('Non è un aderente.');
})->with([422, 409]);

it('retries a server error or a system not yet active', function (int $status): void {
    platformAnsweringTransactions($status, ['message' => 'Non ora.']);

    expect(fn () => send($this->assignment->uuid))->toThrow(PlatformRequestException::class);
    expect($this->row->refresh())->status->toBe(OutboxStatus::Pending)->last_error->toContain('Non ora.');
})->with([503, 403]);

it('waits while the system is not connected', function (): void {
    $this->app->instance(ConnectorConfig::class, new ConnectorConfig);
    Http::fake();

    send($this->assignment->uuid);

    expect($this->row->refresh())->status->toBe(OutboxStatus::Pending)->last_error->not->toBeNull();
    Http::assertNothingSent();
});

it('does nothing for a version already confirmed or a reference it does not know', function (): void {
    $this->row->update(['sent_revision' => 1]);
    Http::fake();

    send($this->assignment->uuid);
    send('unknown');

    Http::assertNothingSent();
});

it('retries ten times with a growing backoff', function (): void {
    $job = new SendPlatformTransaction('x');

    expect($job->tries)->toBe(10)->and($job->backoff())->toBe([60, 300, 900, 1800, 3600]);
});
