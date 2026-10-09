<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use Spatie\LaravelData\Data;

/**
 * The published transaction an application event is about: its id on the platform and its reference in the system.
 */
final class TransactionReferenceData extends Data
{
    public function __construct(
        public string $id,
        public string $reference,
    ) {}
}
