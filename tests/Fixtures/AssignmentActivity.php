<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ITuoiProfessionistiDigitali\Connector\Concerns\RecordsAffectedOnPlatform;
use ITuoiProfessionistiDigitali\Connector\Contracts\AffectsPlatformTransactions;

/**
 * An activity of a proposal: it changes the transaction of its proposal without being one.
 *
 * @property int $id
 * @property int $assignment_id
 * @property string $status
 * @property int|null $minutes
 */
final class AssignmentActivity extends Model implements AffectsPlatformTransactions
{
    use RecordsAffectedOnPlatform;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * @return iterable<Assignment>
     */
    public function affectedPlatformTransactions(): iterable
    {
        return Assignment::query()->whereKey($this->assignment_id)->get();
    }
}
