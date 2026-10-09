<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Exceptions;

use Throwable;

/**
 * The 422 of POST /transactions/{reference}/selection on the status: the transaction is no longer published, because
 * withdrawn or already accepted with another application (rule L4). Not to retry: read the transaction again.
 */
final class TransactionNotPublishedException extends PlatformException
{
    /**
     * @param  list<string>  $messages  The messages of the platform on the status.
     */
    public function __construct(public readonly string $reference, public readonly array $messages, ?Throwable $previous = null)
    {
        parent::__construct("L'incarico non è più pubblicato.", previous: $previous);
    }
}
