<?php

declare(strict_types=1);

use ITuoiProfessionistiDigitali\Connector\Contract;
use ITuoiProfessionistiDigitali\Connector\Tests\TestCase;

pest()->extend(TestCase::class)->in(__DIR__);

/**
 * @return array<string, mixed>
 */
function envelope(array $overrides = []): array
{
    return [
        'event_id' => '0199b6f2-6d7e-7c41-9a3e-5f2b8c1d4e60',
        'type' => Contract::PING,
        'schema_version' => 1,
        'occurred_at' => '2026-10-07T15:30:00+02:00',
        'sender' => '0199b6f0-4e2a-7b31-9f6c-2d8a1e5b7c43',
        'recipient' => '0199b6f1-5c3a-7e10-8d2b-4a6f9e1c3b55',
        'typology' => 'commercialisti',
        'correlation_id' => null,
        'payload' => ['challenge' => 'a1b2c3d4e5f6a7b8'],
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function member(array $overrides = []): array
{
    return [
        'external_ref' => 'struttura-1',
        'subject_type' => 'organization',
        'name' => 'Studio Rossi e Associati',
        'vat_number' => '01234567897',
        'tax_code' => null,
        'municipality' => 'Bari',
        'province' => 'BA',
        'typologies' => ['commercialisti'],
        'listed' => true,
        ...$overrides,
    ];
}

/**
 * The catalogue as GET /event-types publishes it, with the schemas fixed in the package.
 *
 * @return list<array<string, mixed>>
 */
function catalogue(): array
{
    return array_map(static fn (string $type): array => [
        'name' => $type,
        'description' => null,
        'typologies' => null,
        'versions' => [[
            'version' => 1,
            'supported' => true,
            'schema' => json_decode((string) file_get_contents(Contract::schemaPath($type, 1)), true),
        ]],
    ], [Contract::PING, Contract::PONG]);
}

/**
 * A transaction as a system registers it: an hourly assignment to a person, just invited.
 *
 * @return array<string, mixed>
 */
function transaction(array $overrides = []): array
{
    return [
        'kind' => 'person_assignment',
        'principal' => '0199b6f0-4e2a-7b31-9f6c-2d8a1e5b7c43',
        'counterparty' => ['type' => 'person', 'tax_code' => 'RSSMRA80A01H501U', 'name' => 'Mario Rossi'],
        'typology' => 'commercialisti',
        'status' => 'invited',
        'compensation' => ['form' => 'hourly', 'hourly_rate_cents' => 4500, 'currency' => 'EUR'],
        'minutes_worked' => 0,
        'invited_at' => '2026-10-06T18:00:00+02:00',
        'payload' => ['process' => ['name' => 'Contabilità ordinaria'], 'activities' => [['name' => 'Registrazione fatture']]],
        ...$overrides,
    ];
}

/**
 * A transaction as the register returns it.
 *
 * @return array<string, mixed>
 */
function recordedTransaction(array $overrides = []): array
{
    return [
        'id' => '0199b6f5-0000-7000-8000-000000000001',
        'origin' => 'system',
        'reference' => 'incarico-1',
        'kind' => 'person_assignment',
        'principal' => '0199b6f0-4e2a-7b31-9f6c-2d8a1e5b7c43',
        'counterparty' => ['type' => 'person', 'tax_code' => 'RSSMRA80A01H501U', 'name' => 'Mario Rossi'],
        'typology' => 'commercialisti',
        'status' => 'invited',
        'compensation' => ['form' => 'hourly', 'hourly_rate_cents' => 4500, 'fixed_amount_cents' => null, 'currency' => 'EUR'],
        'estimated_minutes' => null,
        'minutes_worked' => 0,
        'invited_at' => '2026-10-06T16:00:00Z',
        'responded_at' => null,
        'closed_at' => null,
        'revision' => 1,
        'type' => 'assignment',
        'schema_version' => 1,
        'payload' => ['process' => ['name' => 'Contabilità ordinaria'], 'activities' => [['name' => 'Registrazione fatture']]],
        'updated_at' => '2026-10-07T13:30:00Z',
        ...$overrides,
    ];
}
