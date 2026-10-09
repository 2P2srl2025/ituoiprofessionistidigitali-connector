<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Exceptions;

use Throwable;

/**
 * The 422 of POST /transactions/{reference}/selection on the application: unknown, of another transaction or no
 * longer pending, for instance withdrawn meanwhile (rule L4). Not to retry: choose another application.
 */
final class ApplicationNotSelectableException extends PlatformException
{
    /**
     * @param  list<string>  $messages  The messages of the platform on the application.
     */
    public function __construct(public readonly string $reference, public readonly string $applicationId, public readonly array $messages, ?Throwable $previous = null)
    {
        parent::__construct('La candidatura non si può scegliere.', previous: $previous);
    }
}
