<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use ITuoiProfessionistiDigitali\Connector\Concerns\RecordsAffectedOnPlatform;
use ITuoiProfessionistiDigitali\Connector\Concerns\RecordsOnPlatform;
use ITuoiProfessionistiDigitali\Connector\Data\ProfessionalRecordData;
use ITuoiProfessionistiDigitali\Connector\Data\TransactionData;
use ITuoiProfessionistiDigitali\Connector\Enums\OutboxStatus;
use ITuoiProfessionistiDigitali\Connector\Facades\Platform;
use ITuoiProfessionistiDigitali\Connector\Jobs\SendPlatformTransaction;
use ITuoiProfessionistiDigitali\Connector\Models\PlatformTransactionOutbox;
use ITuoiProfessionistiDigitali\Connector\Outbox\ProfessionalOutbox;
use ITuoiProfessionistiDigitali\Connector\Outbox\TransactionOutbox;
use ITuoiProfessionistiDigitali\Connector\Tests\Fixtures\Assignment;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
});

it('R9: keeps a draft out of the register', function (): void {
    $assignment = Assignment::query()->create(['status' => 'invited']);
    $assignment->activities()->create();

    expect(PlatformTransactionOutbox::query()->count())->toBe(0)
        ->and($assignment->isRecordedOnPlatform())->toBeFalse();
    Queue::assertNothingPushed();
});

it('R9: writes every save of the model in the outbox and queues it, with a new revision only on changes', function (): void {
    $assignment = sentAssignment();

    $row = PlatformTransactionOutbox::query()->sole();

    expect($row->reference)->toBe($assignment->uuid)
        ->and($row->revision)->toBe(1)
        ->and($row->status)->toBe(OutboxStatus::Pending)
        ->and($row->payload['status'])->toBe('invited')
        ->and($row->payload['activities'])->toHaveCount(1)
        ->and($row->payload)->not->toHaveKey('revision');
    Queue::assertPushed(SendPlatformTransaction::class, fn (SendPlatformTransaction $job): bool => $job->reference === $assignment->uuid);

    $assignment->touch();

    expect($row->refresh()->revision)->toBe(1);
    Queue::assertPushed(SendPlatformTransaction::class, 1);

    $assignment->update(['status' => 'accepted']);

    expect($row->refresh()->revision)->toBe(2)->and($row->payload['status'])->toBe('accepted');
    Queue::assertPushed(SendPlatformTransaction::class, 2);
});

it('R9: records the transaction again when an activity changes or goes, without saving the transaction', function (): void {
    $assignment = sentAssignment();
    $assignment->update(['status' => 'accepted']);
    $activity = $assignment->activities()->sole();
    $row = PlatformTransactionOutbox::query()->sole();

    $activity->update(['status' => 'completed', 'minutes' => 90]);

    expect($row->refresh()->revision)->toBe(3)
        ->and($row->payload['activities'][0]['status'])->toBe('completed')
        ->and($row->payload['activities'][0]['minutes_worked'])->toBe(90);

    $activity->touch();

    expect($row->refresh()->revision)->toBe(3);

    $activity->delete();

    expect($row->refresh()->revision)->toBe(4)->and($row->payload['activities'])->toBe([]);
});

it('R9: tells whether the platform confirmed the current version', function (): void {
    $assignment = sentAssignment();

    expect($assignment->isRecordedOnPlatform())->toBeFalse();

    PlatformTransactionOutbox::query()->update(['sent_revision' => 1]);

    expect($assignment->isRecordedOnPlatform())->toBeTrue();

    $assignment->update(['status' => 'accepted']);

    expect($assignment->isRecordedOnPlatform())->toBeFalse()
        ->and(new Assignment(['uuid' => (string) Str::uuid7()])->isRecordedOnPlatform())->toBeFalse();
});

it('refuses the traits on a model without their interface', function (string $trait): void {
    $model = match ($trait)
    {
        'records' => fn (): Model => new class extends Model {
            use RecordsOnPlatform;

            public function platformTransactionReference(): ?string
            {
                return null;
            }
        },
        default => fn (): Model => new class extends Model {
            use RecordsAffectedOnPlatform;
        },
    };

    expect($model)->toThrow(LogicException::class, 'non implementa');
})->with(['records', 'affects']);

it('R10 and R18: keeps the history registered in batch as confirmed, so the outbox knows every person assigned', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'platform.test/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
        'platform.test/api/v1/transactions/batch' => Http::response(['data' => array_map(
            static fn (string $reference, string $result): array => ['reference' => $reference, 'result' => $result, 'transaction' => $result === 'invalid' ? null : recordedTransaction(['reference' => $reference]), 'errors' => null],
            ['created', 'updated', 'unchanged', 'stale', 'invalid', 'kept'],
            ['created', 'updated', 'unchanged', 'stale', 'invalid', 'updated'],
        )]),
    ]);
    PlatformTransactionOutbox::query()->create(['reference' => 'kept', 'revision' => 5, 'payload' => [], 'status' => OutboxStatus::Pending]);
    $transaction = TransactionData::from(transaction());

    Platform::recordTransactions(array_map(
        static fn (string $reference): array => ['reference' => $reference, 'revision' => 2, 'transaction' => $transaction],
        ['created', 'updated', 'unchanged', 'stale', 'invalid', 'kept'],
    ));

    $rows = PlatformTransactionOutbox::query()->orderBy('reference')->get()->keyBy('reference');

    expect($rows->keys()->all())->toBe(['created', 'kept', 'unchanged', 'updated'])
        ->and($rows['created'])
        ->revision->toBe(2)
        ->sent_revision->toBe(2)
        ->status->toBe(OutboxStatus::Sent)
        ->sent_at->not->toBeNull()
        ->payload->toBe(resolve(TransactionOutbox::class)->payloadOf($transaction))
        ->and($rows['kept'])->revision->toBe(5)->status->toBe(OutboxStatus::Pending)
        ->and(resolve(ProfessionalOutbox::class)->declare('RSSMRA80A01H501U', ProfessionalRecordData::from(professional(['declared_at' => '2026-10-06T18:00:00+02:00']))))->not->toBeNull();
    Queue::assertNotPushed(SendPlatformTransaction::class);
});
