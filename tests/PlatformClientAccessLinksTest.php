<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use ITuoiProfessionistiDigitali\Connector\Data\AccessLinkData;
use ITuoiProfessionistiDigitali\Connector\Exceptions\MemberNotAccessibleException;
use ITuoiProfessionistiDigitali\Connector\Exceptions\PlatformRequestException;
use ITuoiProfessionistiDigitali\Connector\Facades\Platform;

const MEMBER_ID = '0199b6f0-4e2a-7b31-9f6c-2d8a1e5b7c43';
const ACCESS_URL = 'https://platform.test/studio/accesso/s3cr3t-t0k3n';

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['logging.default' => 'null']);
});

function platformGivingAccessLinks(int $status = 201, array $body = []): void
{
    withToken(['platform.test/api/v1/members/*/access-links' => Http::response(
        $status === 201 ? ['data' => ['url' => ACCESS_URL, 'expires_at' => '2026-10-08T15:05:00Z']] : $body,
        $status,
        ['Cache-Control' => 'no-store'],
    )]);
}

it('U1: asks a link for a member of the system, with the key of the name always present', function (): void {
    platformGivingAccessLinks();

    $link = Platform::memberAccessLink(MEMBER_ID, '42');

    expect($link)->toBeInstanceOf(AccessLinkData::class)
        ->and($link->url)->toBe(ACCESS_URL)
        ->and($link->expires_at->equalTo(CarbonImmutable::parse('2026-10-08T15:05:00Z')))->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://platform.test/api/v1/members/'.MEMBER_ID.'/access-links'
        && $request->data() === ['user_ref' => '42', 'user_name' => null]);
});

it('U3: sends the name to show in the area', function (): void {
    platformGivingAccessLinks();

    Platform::memberAccessLink(MEMBER_ID, '42', 'Mario Bianchi');

    Http::assertSent(fn (Request $request): bool => $request->data() === ['user_ref' => '42', 'user_name' => 'Mario Bianchi']);
});

it('U1: turns the 404 of a member of another system, missing or inactive into its own exception', function (): void {
    platformGivingAccessLinks(404, ['message' => 'Aderente non trovato.']);

    try
    {
        Platform::memberAccessLink(MEMBER_ID, '42');
        $this->fail('The member should not be accessible.');
    }
    catch (MemberNotAccessibleException $exception)
    {
        expect($exception->memberId)->toBe(MEMBER_ID);
    }
});

it('keeps the other refusals as they are: a system not active, a field refused, too many requests', function (int $status): void {
    platformGivingAccessLinks($status, ['message' => 'Rifiutata.']);

    expect(fn () => Platform::memberAccessLink(MEMBER_ID, '42'))
        ->toThrow(fn (PlatformRequestException $exception) => expect($exception->status)->toBe($status));
})->with([403, 422, 429]);

it('U3: does not ask a link for a user the platform would refuse', function (string $userRef, ?string $userName, string $field): void {
    Http::fake();

    try
    {
        Platform::memberAccessLink(MEMBER_ID, $userRef, $userName);
        $this->fail('The user should not be valid.');
    }
    catch (ValidationException $exception)
    {
        expect($exception->errors())->toHaveKey($field);
        Http::assertNothingSent();
    }
})->with([
    'empty user_ref' => ['', null, 'user_ref'],
    'long user_ref' => [str_repeat('a', 192), null, 'user_ref'],
    'empty user_name' => ['42', '', 'user_name'],
    'long user_name' => ['42', str_repeat('a', 256), 'user_name'],
]);

it('U4: keeps the link out of the cache and of the logs', function (): void {
    platformGivingAccessLinks();
    $written = [];
    Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$written): void {
        $written[] = serialize($event->value);
    });
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$written): void {
        $written[] = $event->message.json_encode($event->context);
    });

    Platform::memberAccessLink(MEMBER_ID, '42');
    Log::info("Link d'accesso creato.");

    expect($written)->not->toBeEmpty()
        ->and(array_filter($written, static fn (string $entry): bool => str_contains($entry, 's3cr3t-t0k3n')))->toBe([]);
});
