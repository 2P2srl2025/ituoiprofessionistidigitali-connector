<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

/**
 * A page of GET /members: pass nextCursor to the next search to read on.
 */
final readonly class MemberPage
{
    /**
     * @param  list<ListedMemberData>  $members
     */
    public function __construct(
        public array $members,
        public ?string $nextCursor,
        public ?string $previousCursor,
    ) {}

    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }
}
