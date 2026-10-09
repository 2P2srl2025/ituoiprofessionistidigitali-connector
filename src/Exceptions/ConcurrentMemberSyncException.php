<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Exceptions;

use Throwable;

/**
 * The 422 of PUT /members on the whole list: another request changed the members at the same moment (rule M15).
 * The platform applied nothing (rule M6): send the same list again.
 */
final class ConcurrentMemberSyncException extends PlatformException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct("Un'altra richiesta ha cambiato gli aderenti nello stesso momento: riprova.", previous: $previous);
    }
}
