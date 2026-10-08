<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use ITuoiProfessionistiDigitali\Connector\ConnectorConfig;
use ITuoiProfessionistiDigitali\Connector\Contract;
use ITuoiProfessionistiDigitali\Connector\Data\EnvelopeData;
use ITuoiProfessionistiDigitali\Connector\Events\PlatformEventReceived;
use ITuoiProfessionistiDigitali\Connector\Exceptions\PlatformRequestException;
use ITuoiProfessionistiDigitali\Connector\Listeners\ReplyToPing;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

/**
 * The platform answering POST /events with the given response.
 */
function platformAnswering(int $status, array $body = []): void
{
    withToken([
        'platform.test/api/v1/event-types' => Http::response(['data' => catalogue()]),
        'platform.test/api/v1/events' => Http::response($body, $status),
    ]);
}

it('P1: answers a ping from a member with the inverted pong', function (): void {
    platformAnswering(202, ['data' => ['event_id' => 'x', 'type' => Contract::PONG, 'received_at' => '2026-10-07T13:30:00Z', 'status' => 'queued']]);

    event(new PlatformEventReceived(EnvelopeData::from(envelope())));

    Http::assertSent(function (Request $request): bool {
        $pong = $request->data();

        return $request->url() === 'https://platform.test/api/v1/events'
            && $pong['type'] === Contract::PONG
            && $pong['sender'] === envelope()['recipient']
            && $pong['recipient'] === envelope()['sender']
            && $pong['typology'] === 'commercialisti'
            && $pong['correlation_id'] === envelope()['event_id']
            && $pong['payload'] === ['challenge' => 'a1b2c3d4e5f6a7b8'];
    });
});

it('W9: gives the same pong to a ping delivered twice', function (): void {
    platformAnswering(202, ['data' => ['event_id' => 'x', 'type' => Contract::PONG, 'received_at' => '2026-10-07T13:30:00Z', 'status' => 'queued']]);

    event(new PlatformEventReceived(EnvelopeData::from(envelope())));
    event(new PlatformEventReceived(EnvelopeData::from(envelope())));

    $ids = Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/events'))
        ->map(fn (array $pair): mixed => $pair[0]->data()['event_id'])
        ->unique();

    expect($ids)->toHaveCount(1);
});

it('W9: considers a pong already sent when the platform answers 409', function (): void {
    platformAnswering(409, ['message' => 'Evento già ricevuto con un contenuto diverso.']);

    resolve(ReplyToPing::class)->handle(new PlatformEventReceived(EnvelopeData::from(envelope())));

    Http::assertSentCount(3);
});

it('P1: lets the queue retry when the pong is refused for another reason', function (): void {
    platformAnswering(500);

    resolve(ReplyToPing::class)->handle(new PlatformEventReceived(EnvelopeData::from(envelope())));
})->throws(PlatformRequestException::class);

it('P1: does not answer pongs, other events or the verification ping', function (array $overrides): void {
    $listener = resolve(ReplyToPing::class);
    $event = new PlatformEventReceived(EnvelopeData::from(envelope($overrides)));

    $listener->handle($event);

    expect($listener->shouldQueue($event))->toBeFalse();
    Http::assertNothingSent();
})->with([
    'pong' => [['type' => Contract::PONG]],
    'business event' => [['type' => 'coworking.request']],
    'verification' => [['sender' => null, 'recipient' => null, 'typology' => null]],
]);

it('P1: runs on the configured queue', function (): void {
    $this->app->instance(ConnectorConfig::class, new ConnectorConfig(queue: 'platform'));

    expect(resolve(ReplyToPing::class)->viaQueue())->toBe('platform');
});
