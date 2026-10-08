<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use Carbon\CarbonImmutable;
use ITuoiProfessionistiDigitali\Connector\Contract;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

/**
 * A link that opens the area of the firm on the platform, as POST /members/{id}/access-links answers it (rules U1–U4).
 * The access is for the firm: whoever follows the link enters the area of the member.
 * It opens the area once, within five minutes. It is a secret: redirect the browser to it right away, and never
 * cache it, log it or keep it. When a log needs a reference, use expires_at only.
 */
final class AccessLinkData extends Data
{
    public function __construct(
        public string $url,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        public CarbonImmutable $expires_at,
    ) {}
}
