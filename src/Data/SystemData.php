<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use Carbon\CarbonImmutable;
use ITuoiProfessionistiDigitali\Connector\Contract;
use ITuoiProfessionistiDigitali\Connector\Enums\SystemStatus;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

/**
 * The connected system as the platform sees it: GET /system and the answer of PUT /system (rule S6).
 */
final class SystemData extends Data
{
    /**
     * @param  list<int>|null  $contract_versions
     */
    public function __construct(
        public string $id,
        public string $name,
        public SystemStatus $status,
        public ?string $webhook_url,
        public ?array $contract_versions,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        public ?CarbonImmutable $verified_at,
        public ?VerificationData $last_verification,
    ) {}

    public function isActive(): bool
    {
        return $this->status === SystemStatus::Active;
    }
}
