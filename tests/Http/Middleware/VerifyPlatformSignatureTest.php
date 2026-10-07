<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use ITuoiProfessionistiDigitali\Connector\ConnectorConfig;
use ITuoiProfessionistiDigitali\Connector\Contract;
use ITuoiProfessionistiDigitali\Connector\Events\PlatformEventReceived;
use ITuoiProfessionistiDigitali\Connector\Signature\WebhookSignature;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-07 15:30:00');
    Event::fake();
});

it('W4: lets through a webhook signed with the signing secret', function (): void {
    $body = json_encode(envelope());
    $timestamp = CarbonImmutable::now()->getTimestamp();

    $this->call('POST', '/platform/webhook', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_'.str_replace('-', '_', mb_strtoupper(Contract::HEADER_TIMESTAMP)) => (string) $timestamp,
        'HTTP_'.str_replace('-', '_', mb_strtoupper(Contract::HEADER_SIGNATURE)) => WebhookSignature::sign($body, $timestamp, 'signing-secret'),
    ], content: $body)->assertNoContent();

    Event::assertDispatched(PlatformEventReceived::class);
});

it('W4: refuses with 401 a webhook signed with another secret, before reading it', function (): void {
    $body = json_encode(envelope());
    $timestamp = CarbonImmutable::now()->getTimestamp();

    $this->call('POST', '/platform/webhook', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PLATFORM_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_PLATFORM_SIGNATURE' => WebhookSignature::sign($body, $timestamp, 'another-secret'),
    ], content: $body)
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Firma non valida.']);

    Event::assertNotDispatched(PlatformEventReceived::class);
});

it('W4: refuses every webhook while no signing secret is configured', function (): void {
    $this->app->instance(ConnectorConfig::class, new ConnectorConfig(signingSecret: null));
    $body = json_encode(envelope());
    $timestamp = CarbonImmutable::now()->getTimestamp();

    $this->call('POST', '/platform/webhook', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PLATFORM_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_PLATFORM_SIGNATURE' => WebhookSignature::sign($body, $timestamp, 'signing-secret'),
    ], content: $body)->assertUnauthorized();
});
