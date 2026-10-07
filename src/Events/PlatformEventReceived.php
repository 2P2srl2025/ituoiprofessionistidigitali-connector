<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Events;

use ITuoiProfessionistiDigitali\Connector\Data\EnvelopeData;

/**
 * An event delivered by the platform to the webhook, signature already verified.
 *
 * The same event can arrive more than once and in any order (rule W9): listeners are idempotent
 * on `event_id` and queue their work, because the platform waits ten seconds at most.
 */
final readonly class PlatformEventReceived
{
    public function __construct(public EnvelopeData $envelope) {}
}
