<?php

declare(strict_types=1);

use ITuoiProfessionistiDigitali\Connector\Contract;
use Opis\JsonSchema\Validator;

it('C4: fixes a valid JSON Schema 2020-12 for every event type version', function (string $type): void {
    $path = Contract::schemaPath($type, 1);
    $schema = json_decode((string) file_get_contents($path));

    expect($schema->{'$schema'})->toBe('https://json-schema.org/draft/2020-12/schema')
        ->and($schema->{'$id'})->toBe("https://ituoiprofessionistidigitali.it/contract/event-types/{$type}/1.json")
        ->and(new Validator()->validate((object) ['challenge' => 'a1b2c3d4e5f6a7b8'], $schema)->isValid())->toBeTrue();
})->with([Contract::PING, Contract::PONG]);

it('C5: keeps the challenge of ping and pong between 16 and 128 safe characters, with nothing else', function (string $type, mixed $payload, bool $isValid): void {
    $schema = json_decode((string) file_get_contents(Contract::schemaPath($type, 1)));

    expect(new Validator()->validate($payload, $schema)->isValid())->toBe($isValid);
})->with([Contract::PING, Contract::PONG])->with([
    'sixteen characters' => [(object) ['challenge' => str_repeat('a', 16)], true],
    'one hundred twenty-eight characters' => [(object) ['challenge' => str_repeat('A', 128)], true],
    'dash and underscore' => [(object) ['challenge' => 'abc-def_ghi-jkl_'], true],
    'too short' => [(object) ['challenge' => str_repeat('a', 15)], false],
    'too long' => [(object) ['challenge' => str_repeat('a', 129)], false],
    'other characters' => [(object) ['challenge' => 'a1b2c3d4e5f6a7b8!'], false],
    'missing' => [(object) [], false],
    'one more field' => [(object) ['challenge' => 'a1b2c3d4e5f6a7b8', 'extra' => 1], false],
]);
