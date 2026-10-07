<?php

declare(strict_types=1);

use ITuoiProfessionistiDigitali\Connector\Exceptions\InvalidSignatureException;
use ITuoiProfessionistiDigitali\Connector\Signature\WebhookSignature;

const BODY = '{"event_id":"0199b6f2-6d7e-7c41-9a3e-5f2b8c1d4e60"}';
const NOW = 1_791_380_000;

it('W3: signs with v1 and the HMAC-SHA256 of timestamp.body', function (): void {
    expect(WebhookSignature::sign(BODY, NOW, 'secret'))
        ->toBe('v1='.hash_hmac('sha256', NOW.'.'.BODY, 'secret'));
});

it('W3: signs once per secret during a rotation', function (): void {
    expect(WebhookSignature::sign(BODY, NOW, 'new', 'old'))
        ->toBe(WebhookSignature::sign(BODY, NOW, 'new').','.WebhookSignature::sign(BODY, NOW, 'old'));
});

it('W3: accepts any of the signatures with any of the secrets', function (string $secret): void {
    WebhookSignature::verify(BODY, (string) NOW, WebhookSignature::sign(BODY, NOW, 'new', 'old'), [$secret], NOW);

    expect(true)->toBeTrue();
})->with(['new', 'old']);

it('W4: accepts a timestamp up to five minutes away, in the past or in the future', function (int $offset): void {
    WebhookSignature::verify(BODY, (string) (NOW + $offset), WebhookSignature::sign(BODY, NOW + $offset, 'secret'), ['secret'], NOW);

    expect(true)->toBeTrue();
})->with([-300, 300]);

it('W4: refuses what the platform did not sign', function (?string $timestamp, ?string $signature, array $secrets, string $message): void {
    expect(fn () => WebhookSignature::verify(BODY, $timestamp, $signature, $secrets, NOW))
        ->toThrow(InvalidSignatureException::class, $message);
})->with([
    'no timestamp' => [null, 'v1=abc', ['secret'], 'Timestamp della firma assente o non valido.'],
    'timestamp not a number' => ['yesterday', 'v1=abc', ['secret'], 'Timestamp della firma assente o non valido.'],
    'timestamp too old' => [(string) (NOW - 301), WebhookSignature::sign(BODY, NOW - 301, 'secret'), ['secret'], 'Timestamp della firma fuori tolleranza.'],
    'timestamp in the future' => [(string) (NOW + 301), WebhookSignature::sign(BODY, NOW + 301, 'secret'), ['secret'], 'Timestamp della firma fuori tolleranza.'],
    'no signature' => [(string) NOW, null, ['secret'], 'Firma assente.'],
    'empty signature' => [(string) NOW, '', ['secret'], 'Firma assente.'],
    'no secret configured' => [(string) NOW, WebhookSignature::sign(BODY, NOW, 'secret'), [], 'Firma assente.'],
    'another secret' => [(string) NOW, WebhookSignature::sign(BODY, NOW, 'other'), ['secret'], 'Firma non valida.'],
    'another version' => [(string) NOW, 'v2='.hash_hmac('sha256', NOW.'.'.BODY, 'secret'), ['secret'], 'Firma non valida.'],
    'another body' => [(string) NOW, WebhookSignature::sign('{}', NOW, 'secret'), ['secret'], 'Firma non valida.'],
]);
