<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use ITuoiProfessionistiDigitali\Connector\Contract;
use ITuoiProfessionistiDigitali\Connector\Events\PlatformEventReceived;
use ITuoiProfessionistiDigitali\Connector\Signature\WebhookSignature;
use ITuoiProfessionistiDigitali\Connector\Tests\TestCase;

beforeEach(function (): void {
    Event::fake();
});

/**
 * Posts a body to the webhook as the platform does, signed with the configured secret.
 */
function deliver(TestCase $test, string $body): TestResponse
{
    $timestamp = CarbonImmutable::now()->getTimestamp();

    return $test->call('POST', route('platform.webhook'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PLATFORM_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_PLATFORM_SIGNATURE' => WebhookSignature::sign($body, $timestamp, 'signing-secret'),
    ], content: $body);
}

it('S7: answers the verification ping with its challenge', function (): void {
    deliver($this, json_encode(envelope(['sender' => null, 'recipient' => null, 'typology' => null])))
        ->assertOk()
        ->assertExactJson(['challenge' => 'a1b2c3d4e5f6a7b8']);

    Event::assertNotDispatched(PlatformEventReceived::class);
});

it('W5: hands an event to the application and answers 2xx', function (): void {
    deliver($this, json_encode(envelope(['type' => 'coworking.request', 'payload' => ['unknown' => true]])))
        ->assertNoContent();

    Event::assertDispatched(
        PlatformEventReceived::class,
        fn (PlatformEventReceived $event): bool => $event->envelope->type === 'coworking.request',
    );
});

it('W1: answers 400 to a body that is not an envelope', function (string $body): void {
    deliver($this, $body)->assertBadRequest();

    Event::assertNotDispatched(PlatformEventReceived::class);
})->with([
    'not json' => ['not json'],
    'missing fields' => [json_encode(['event_id' => 'x'])],
    'wrong type' => [json_encode(envelope(['schema_version' => 'one']))],
    'event without sender' => [json_encode(envelope(['type' => Contract::PONG, 'sender' => null]))],
]);
