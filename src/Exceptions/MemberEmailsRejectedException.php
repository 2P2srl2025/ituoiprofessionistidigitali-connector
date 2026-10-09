<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Exceptions;

use Throwable;

/**
 * The 422 of PUT /members on the emails only: each is already of another member of the platform, or of a firm
 * registered without a system (rule M15). Not to retry: the email is to settle with the firm.
 */
final class MemberEmailsRejectedException extends PlatformException
{
    /**
     * @param  array<string, list<string>>  $messages  The messages of the platform, by external_ref of the request.
     */
    public function __construct(public readonly array $messages, ?Throwable $previous = null)
    {
        parent::__construct("Il portale ha rifiutato l'email di ".implode(', ', array_keys($messages)).'.', previous: $previous);
    }
}
