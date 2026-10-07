<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use Spatie\LaravelData\Data;

/**
 * An entry of the catalogue of professions: the code is stable, never renamed nor reused (rule C2).
 */
final class TypologyData extends Data
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $description,
    ) {}
}
