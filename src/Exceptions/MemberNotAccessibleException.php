<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Exceptions;

/**
 * The 404 of POST /members/{id}/access-links: the member is not of the system, does not exist or is not active
 * (rule U1). The platform does not tell which.
 */
final class MemberNotAccessibleException extends PlatformException
{
    public function __construct(public readonly string $memberId)
    {
        parent::__construct("L'aderente non è del sistema, non esiste o non è attivo.");
    }
}
