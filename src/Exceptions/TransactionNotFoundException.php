<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Exceptions;

/**
 * The 404 of the applications and of the selection: the reference is not of a transaction of the system (rule L4).
 */
final class TransactionNotFoundException extends PlatformException
{
    public function __construct(public readonly string $reference)
    {
        parent::__construct('La transazione non è del sistema.');
    }
}
