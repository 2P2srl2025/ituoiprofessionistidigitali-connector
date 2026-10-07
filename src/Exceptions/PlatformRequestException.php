<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Exceptions;

use ITuoiProfessionistiDigitali\Connector\Data\AcceptedEventData;
use ITuoiProfessionistiDigitali\Connector\Data\RecordedTransactionData;
use ITuoiProfessionistiDigitali\Connector\Enums\SystemStatus;

/**
 * A request the platform refused, or that the package refused before sending it.
 *
 * Rely on the status, on the keys of `errors` and on `reason`, never on the message.
 */
final class PlatformRequestException extends PlatformException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly array $errors = [],
        public readonly ?SystemStatus $reason = null,
        public readonly ?AcceptedEventData $existingEvent = null,
        public readonly ?RecordedTransactionData $existingTransaction = null,
    ) {
        parent::__construct($message);
    }

    public function isContractViolation(): bool
    {
        return $this->status === 422;
    }

    public function isConflict(): bool
    {
        return $this->status === 409;
    }
}
