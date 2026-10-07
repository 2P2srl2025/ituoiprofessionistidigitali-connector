<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Validation;

use ITuoiProfessionistiDigitali\Connector\Data\EnvelopeData;
use ITuoiProfessionistiDigitali\Connector\Data\EventTypeData;
use ITuoiProfessionistiDigitali\Connector\Exceptions\PlatformRequestException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use stdClass;

/**
 * Checks an outgoing envelope against the catalogue before it leaves, as POST /events would (rules E5, E6 and E7).
 *
 * Whether sender and recipient have the typology is known to the platform only.
 */
final class PayloadValidator
{
    /**
     * @param  list<EventTypeData>  $catalogue
     *
     * @throws PlatformRequestException
     */
    public static function validate(EnvelopeData $envelope, array $catalogue): void
    {
        $eventType = array_find($catalogue, fn (EventTypeData $candidate): bool => $candidate->name === $envelope->type);

        if ($eventType === null)
        {
            self::fail(['type' => ["Il tipo di evento {$envelope->type} non è nel catalogo."]]);
        }

        $version = $eventType->version($envelope->schema_version);

        if ($version === null || !$version->supported)
        {
            self::fail(['schema_version' => ["La versione {$envelope->schema_version} di {$envelope->type} non è supportata."]]);
        }

        if ($envelope->typology !== null && !$eventType->allowsTypology($envelope->typology))
        {
            self::fail(['typology' => ["La tipologia {$envelope->typology} non è ammessa per {$envelope->type}."]]);
        }

        self::validateSchema($version->schema, $envelope->payload);
    }

    /**
     * Checks a payload against a JSON Schema, with the keys the platform uses (`payload.activities.0`).
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $payload
     *
     * @throws PlatformRequestException
     */
    public static function validateSchema(array $schema, array $payload): void
    {
        $error = new Validator()->validate(
            $payload === [] ? new stdClass : Helper::toJSON($payload),
            json_encode($schema, JSON_THROW_ON_ERROR),
        )->error();

        if ($error === null)
        {
            return;
        }

        $errors = [];

        foreach (new ErrorFormatter()->format($error) as $pointer => $messages)
        {
            $errors[self::key((string) $pointer)] = array_values(array_filter((array) $messages, is_string(...)));
        }

        self::fail($errors);
    }

    /**
     * A JSON pointer of the payload (`/items/0/name`) as the key POST /events uses (`payload.items.0.name`).
     */
    private static function key(string $pointer): string
    {
        return mb_rtrim('payload.'.str_replace('/', '.', mb_ltrim($pointer, '/')), '.');
    }

    /**
     * @param  array<string, list<string>>  $errors
     *
     * @throws PlatformRequestException
     */
    private static function fail(array $errors): never
    {
        throw new PlatformRequestException(
            message: 'L\'evento non rispetta il catalogo del portale.',
            status: 422,
            errors: $errors,
        );
    }
}
