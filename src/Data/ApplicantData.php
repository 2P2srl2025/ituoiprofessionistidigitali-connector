<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use ITuoiProfessionistiDigitali\Connector\Enums\CounterpartyType;
use Spatie\LaravelData\Data;

/**
 * Who applied, as the principal sees it before the selection: no tax code, email, VAT number or mobile, which come
 * only with the selection (rule L7). Today always a person.
 */
final class ApplicantData extends Data
{
    /**
     * @param  bool  $tax_code_verified  Whether the tax code was verified; otherwise it is the one declared by the
     *                                   professional, with the formal check only.
     */
    public function __construct(
        public CounterpartyType $type,
        public string $first_name,
        public string $last_name,
        public string $municipality,
        public string $province,
        public bool $tax_code_verified,
    ) {}
}
