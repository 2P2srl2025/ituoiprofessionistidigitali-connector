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
 * An activity as a system sends it: open, by the hour, 120 minutes at 45 euro.
 *
 * @return array<string, mixed>
 */
function activity(array $overrides = []): array
{
    return [
        'reference' => 'riga-1',
        'compensation' => ['form' => 'hourly', 'hourly_rate_cents' => 4500, 'fixed_amount_cents' => null],
        'estimated_minutes' => 120,
        'minutes_worked' => null,
        'status' => 'open',
        'closed_at' => null,
        'description' => ['process' => ['name' => 'Contabilità ordinaria'], 'activity' => ['name' => 'Registrazione fatture'], 'deadline' => '2026-11-30'],
        ...$overrides,
    ];
}

/**
 * A transaction as a system registers it: an assignment to a person, just sent, with one activity and every key.
 *
 * @return array<string, mixed>
 */
function transaction(array $overrides = []): array
{
    return [
        'assignment_reference' => 'incarico-1',
        'audience' => 'person',
        'principal' => '0199b6f0-4e2a-7b31-9f6c-2d8a1e5b7c43',
        'counterparty' => ['type' => 'person', 'member_id' => null, 'tax_code' => 'RSSMRA80A01H501U', 'first_name' => 'Mario', 'last_name' => 'Rossi', 'email' => 'mario.rossi@example.com', 'vat_number' => null, 'municipality' => 'Bari', 'province' => 'BA'],
        'typology' => 'commercialisti',
        'title' => 'Contabilità ordinaria 2026',
        'description' => 'Registrazione delle fatture del 2026.',
        'status' => 'invited',
        'sent_at' => '2026-10-06T18:00:00+02:00',
        'expires_at' => null,
        'responded_at' => null,
        'closed_at' => null,
        'activities' => [activity()],
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
        'reference' => 'invio-1',
        'assignment_reference' => 'incarico-1',
        'audience' => 'person',
        'principal' => '0199b6f0-4e2a-7b31-9f6c-2d8a1e5b7c43',
        'counterparty' => ['type' => 'person', 'member_id' => null, 'tax_code' => 'RSSMRA80A01H501U', 'first_name' => 'Mario', 'last_name' => 'Rossi', 'email' => 'mario.rossi@example.com', 'vat_number' => null, 'municipality' => 'Bari', 'province' => 'BA'],
        'typology' => 'commercialisti',
        'title' => 'Contabilità ordinaria 2026',
        'description' => 'Registrazione delle fatture del 2026.',
        'status' => 'invited',
        'sent_at' => '2026-10-06T16:00:00Z',
        'expires_at' => null,
        'responded_at' => null,
        'closed_at' => null,
        'currency' => 'EUR',
        'total_cents' => 9000,
        'revision' => 1,
        'type' => 'assignment',
        'schema_version' => 1,
        'activities' => [[...activity(), 'total_cents' => 9000]],
        'updated_at' => '2026-10-07T13:30:00Z',
        ...$overrides,
    ];
}

/**
 * A proposal prepared as a draft with one activity, then sent: the outbox has its revision 1.
 */
function sentAssignment(): ITuoiProfessionistiDigitali\Connector\Tests\Fixtures\Assignment
{
    $assignment = ITuoiProfessionistiDigitali\Connector\Tests\Fixtures\Assignment::query()->create(['status' => 'invited']);
    $assignment->activities()->create();
    $assignment->update(['uuid' => (string) Illuminate\Support\Str::uuid7()]);

    return $assignment;
}

/**
 * The record of a professional as a system declares it with PUT /professionals/{tax_code}.
 *
 * @return array<string, mixed>
 */
function professional(array $overrides = []): array
{
    return [
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'email' => null,
        'vat_number' => '01234567897',
        'municipality' => 'Lecce',
        'province' => 'LE',
        'declared_at' => '2026-10-08T10:15:00+02:00',
        ...$overrides,
    ];
}
