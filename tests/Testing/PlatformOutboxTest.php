<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use ITuoiProfessionistiDigitali\Connector\Models\PlatformTransactionOutbox;
use ITuoiProfessionistiDigitali\Connector\Testing\PlatformOutbox;
use ITuoiProfessionistiDigitali\Connector\Tests\Fixtures\Assignment;
use PHPUnit\Framework\AssertionFailedError;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
});

it('R9: passes when the outbox has the current version, and only then', function (): void {
    $assignment = sentAssignment();

    PlatformOutbox::assertRecorded($assignment);

    PlatformTransactionOutbox::query()->update(['payload' => transaction()]);

    expect(fn () => PlatformOutbox::assertRecorded($assignment))->toThrow(AssertionFailedError::class, 'versione corrente');

    PlatformTransactionOutbox::query()->delete();

    expect(fn () => PlatformOutbox::assertRecorded($assignment))->toThrow(AssertionFailedError::class, 'non è nella outbox')
        ->and(fn () => PlatformOutbox::assertRecorded(new Assignment(['status' => 'invited'])))->toThrow(AssertionFailedError::class, 'bozza');
});

it('R9: tells that a draft never reached the outbox', function (): void {
    $draft = Assignment::query()->create(['status' => 'invited']);

    PlatformOutbox::assertNotRecorded($draft);

    $sent = sentAssignment();

    expect(fn () => PlatformOutbox::assertNotRecorded($sent))->toThrow(AssertionFailedError::class, 'è nella outbox');
});
