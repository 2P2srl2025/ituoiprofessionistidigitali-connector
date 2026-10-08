<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Contracts;

/**
 * A model whose changes change transactions of the register without being one: an activity of a sending,
 * or the assignment that holds the sendings (rule R9).
 *
 * Implement it together with the RecordsAffectedOnPlatform trait: on every save and delete the trait
 * records again each transaction it returns.
 */
interface AffectsPlatformTransactions
{
    /**
     * The transactions to record again after a change of this model, read fresh from the database.
     *
     * @return iterable<RecordsPlatformTransaction>
     */
    public function affectedPlatformTransactions(): iterable;
}
