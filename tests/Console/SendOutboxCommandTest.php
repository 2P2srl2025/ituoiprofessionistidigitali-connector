<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use ITuoiProfessionistiDigitali\Connector\ConnectorConfig;
use ITuoiProfessionistiDigitali\Connector\Enums\OutboxStatus;
use ITuoiProfessionistiDigitali\Connector\Jobs\SendPlatformTransaction;
use ITuoiProfessionistiDigitali\Connector\Models\PlatformTransactionOutbox;

uses(RefreshDatabase::class);

function outboxRow(string $reference, array $state): PlatformTransactionOutbox
{
    return PlatformTransactionOutbox::query()->create([
        'reference' => $reference,
        'revision' => 2,
        'sent_revision' => null,
        'payload' => transaction(),
        'status' => OutboxStatus::Pending,
        ...$state,
    ]);
}

it('queues again only the versions not yet confirmed', function (): void {
    Queue::fake();
    outboxRow('never-sent', []);
    outboxRow('behind', ['sent_revision' => 1]);
    outboxRow('confirmed', ['sent_revision' => 2, 'status' => OutboxStatus::Sent]);
    outboxRow('failed', ['status' => OutboxStatus::Failed]);

    $this->artisan('platform:send-outbox')->expectsOutputToContain('Transazioni rimesse in coda: 2.')->assertSuccessful();

    Queue::assertPushed(SendPlatformTransaction::class, 2);
    Queue::assertPushed(SendPlatformTransaction::class, fn (SendPlatformTransaction $job): bool => $job->reference === 'behind');
});

it('does nothing while the system is not connected', function (): void {
    Queue::fake();
    outboxRow('never-sent', []);
    $this->app->instance(ConnectorConfig::class, new ConnectorConfig);

    $this->artisan('platform:send-outbox')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('runs every five minutes', function (): void {
    $event = collect(resolve(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'platform:send-outbox'));

    expect($event?->expression)->toBe('*/5 * * * *');
});
