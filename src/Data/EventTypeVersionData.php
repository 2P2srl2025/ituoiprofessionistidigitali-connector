<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use Spatie\LaravelData\Data;

/**
 * A version of an event type, with the JSON Schema of its payload. A published version never changes (rule C5).
 */
final class EventTypeVersionData extends Data
{
    /**
     * @param  array<string, mixed>  $schema
     */
    public function __construct(
        public int $version,
        public bool $supported,
        public array $schema,
    ) {}
}
