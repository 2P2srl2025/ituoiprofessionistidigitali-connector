<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Exceptions;

/**
 * The 404 of PUT /professionals/{tax_code}: the platform has no transaction of the system to a person with this
 * tax code. Not an error when the system never assigned the person; to repeat when its transactions to the
 * person are not registered yet (rule R18).
 */
final class ProfessionalNotAssignedException extends PlatformException
{
    public function __construct(public readonly string $taxCode)
    {
        parent::__construct('Il portale non ha transazioni del sistema a questo professionista.');
    }
}
