<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use ITuoiProfessionistiDigitali\Connector\Enums\OutboxStatus;
use ITuoiProfessionistiDigitali\Connector\Jobs\SendPlatformTransaction;
use ITuoiProfessionistiDigitali\Connector\Models\PlatformTransactionOutbox;
use ITuoiProfessionistiDigitali\Connector\Tests\Fixtures\Assignment;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
});

it('R9: writes every save of the model in the outbox and queues it, with a new revision only on changes', function (): void {
    $assignment = Assignment::query()->create(['uuid' => (string) Str::uuid7(), 'status' => 'invited']);

    $row = PlatformTransactionOutbox::query()->sole();

    expect($row->reference)->toBe($assignment->uuid)
        ->and($row->revision)->toBe(1)
        ->and($row->status)->toBe(OutboxStatus::Pending)
        ->and($row->payload['status'])->toBe('invited')
        ->and($row->payload)->not->toHaveKey('revision');
    Queue::assertPushed(SendPlatformTransaction::class, fn (SendPlatformTransaction $job): bool => $job->reference === $assignment->uuid);

    $assignment->touch();

    expect($row->refresh()->revision)->toBe(1);
    Queue::assertPushed(SendPlatformTransaction::class, 1);

    $assignment->update(['status' => 'accepted', 'minutes' => 30]);

    expect($row->refresh()->revision)->toBe(2)->and($row->payload['minutes_worked'])->toBe(30);
    Queue::assertPushed(SendPlatformTransaction::class, 2);
});

it('R9: tells whether the platform confirmed the current version', function (): void {
    $assignment = Assignment::query()->create(['uuid' => (string) Str::uuid7(), 'status' => 'invited']);

    expect($assignment->isRecordedOnPlatform())->toBeFalse();

    PlatformTransactionOutbox::query()->update(['sent_revision' => 1]);

    expect($assignment->isRecordedOnPlatform())->toBeTrue();

    $assignment->update(['minutes' => 10]);

    expect($assignment->isRecordedOnPlatform())->toBeFalse()
        ->and(new Assignment(['uuid' => 'never-saved'])->isRecordedOnPlatform())->toBeFalse();
});
