<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use ITuoiProfessionistiDigitali\Connector\Enums\SubjectType;
use Spatie\LaravelData\Data;

/**
 * A member of another system, as GET /members shows it: no tax code, no email, no external_ref, no system
 * (rules M11 and M12).
 */
final class ListedMemberData extends Data
{
    /**
     * @param  list<string>  $typologies
     */
    public function __construct(
        public string $id,
        public SubjectType $subject_type,
        public string $name,
        public ?string $vat_number,
        public string $municipality,
        public string $province,
        public array $typologies,
    ) {}
}
