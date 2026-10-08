<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Data;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Validation\Rule;
use ITuoiProfessionistiDigitali\Connector\Contract;
use ITuoiProfessionistiDigitali\Connector\Enums\Audience;
use ITuoiProfessionistiDigitali\Connector\Enums\TransactionActivityStatus;
use ITuoiProfessionistiDigitali\Connector\Enums\TransactionStatus;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

/**
 * An assignment of the system as the register of the platform receives it: sent to a counterparty,
 * or published on the platform without one. Both have the same keys, all of them always present;
 * only the values change. The revision is kept by the outbox of the package (rules R3, R5, R8, R14 and R16).
 *
 * The part fixed at the sending (audience, counterparty, typology, texts, prices) is the same at every
 * revision (rule R13): a system builds it from what it kept when it sent the assignment, not from its records now.
 */
final class TransactionData extends Data
{
    public const int MAX_ACTIVITIES = 500;

    public const int MAX_DESCRIPTION_LENGTH = 10000;

    /**
     * @param  list<TransactionActivityData>  $activities
     */
    public function __construct(
        public string $assignment_reference,
        public Audience $audience,
        public string $principal,
        public ?CounterpartyData $counterparty,
        public string $typology,
        public string $title,
        public string $description,
        public TransactionStatus $status,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: Contract::DATE_FORMAT)]
        public CarbonImmutable $sent_at,
        #[DataCollectionOf(TransactionActivityData::class)]
        public array $activities,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: Contract::DATE_FORMAT)]
        public ?CarbonImmutable $expires_at = null,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: Contract::DATE_FORMAT)]
        public ?CarbonImmutable $responded_at = null,
        #[WithCast(DateTimeInterfaceCast::class, format: Contract::DATE_INPUT_FORMATS)]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: Contract::DATE_FORMAT)]
        public ?CarbonImmutable $closed_at = null,
        public string $currency = 'EUR',
        public string $type = 'assignment',
        public int $schema_version = 1,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $payload = is_array($context->payload) ? $context->payload : [];
        $status = TransactionStatus::tryFrom(TransactionActivityData::value($payload['status'] ?? null));
        $isPublication = $status?->isPublication() === true;
        $hasCounterparty = $status !== null && !$isPublication;

        return [
            'assignment_reference' => ['required', 'string', 'max:191'],
            'principal' => ['required', 'uuid'],
            // R16: whom the assignment is for; one sent to a counterparty is for its type, checked on counterparty.type
            'audience' => ['required', Rule::enum(Audience::class), Rule::when($hasCounterparty, [Rule::notIn([Audience::Any->value])])],
            // A published assignment gets its counterparty from the platform, never from the system (rule R3)
            'counterparty' => ['present', Rule::requiredIf($hasCounterparty), Rule::prohibitedIf($isPublication), 'nullable', 'array'],
            'typology' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/'],
            'status' => ['required', Rule::enum(TransactionStatus::class), self::followsActivities($status, $payload['activities'] ?? null)],
            // R16: kept after the platform gives a counterparty to a published assignment; the platform checks the rest
            'expires_at' => ['present', Rule::requiredIf($isPublication), 'nullable', 'after:sent_at'],
            // Texts of the firm, without data of the client: a published assignment shows them on the platform (rule R6)
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:'.self::MAX_DESCRIPTION_LENGTH],
            'responded_at' => ['present', Rule::requiredIf($status?->isAnswered() === true), Rule::prohibitedIf($status === TransactionStatus::Invited || $isPublication)],
            'closed_at' => ['present', Rule::requiredIf($status?->isFinal() === true), Rule::prohibitedIf($status !== null && !$status->isFinal())],
            'currency' => ['sometimes', Rule::in(['EUR'])],
            'activities' => ['required', 'array', 'list', 'max:'.self::MAX_ACTIVITIES],
        ];
    }

    /**
     * The sum of the totals of the activities, as the platform computes it (rule R15).
     */
    public function totalCents(): int
    {
        return array_sum(array_map(static fn (TransactionActivityData $activity): int => $activity->totalCents(), $this->activities));
    }

    /**
     * The body of PUT /transactions/{reference}, with the revision kept by the outbox.
     *
     * @return array<string, mixed>
     */
    public function toWire(int $revision): array
    {
        return [
            'assignment_reference' => $this->assignment_reference,
            'audience' => $this->audience->value,
            'principal' => $this->principal,
            'counterparty' => $this->counterparty?->toWire(),
            'typology' => $this->typology,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'sent_at' => $this->sent_at->format(Contract::DATE_FORMAT),
            'expires_at' => $this->expires_at?->format(Contract::DATE_FORMAT),
            'responded_at' => $this->responded_at?->format(Contract::DATE_FORMAT),
            'closed_at' => $this->closed_at?->format(Contract::DATE_FORMAT),
            'currency' => $this->currency,
            'revision' => $revision,
            'type' => $this->type,
            'schema_version' => $this->schema_version,
            'activities' => array_map(static fn (TransactionActivityData $activity): array => $activity->toWire(), $this->activities),
        ];
    }

    /**
     * R14: the status follows the activities, as the platform checks it.
     */
    private static function followsActivities(?TransactionStatus $status, mixed $activities): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($status, $activities): void {
            $statuses = array_map(
                static fn (mixed $activity): ?TransactionActivityStatus => TransactionActivityStatus::tryFrom(TransactionActivityData::value(is_array($activity) ? $activity['status'] ?? null : null)),
                is_array($activities) ? array_values($activities) : [],
            );
            $hasOpen = in_array(TransactionActivityStatus::Open, $statuses, true);
            $hasClosed = array_any($statuses, static fn (?TransactionActivityStatus $activity): bool => $activity?->isClosed() === true);
            $hasCompleted = in_array(TransactionActivityStatus::Completed, $statuses, true);

            $follows = match (true)
            {
                $status === null => true,
                $status->isBeforeAgreement() => !$hasClosed,
                $status === TransactionStatus::Accepted => $hasOpen,
                $status === TransactionStatus::Completed => !$hasOpen && $hasCompleted,
                default => !$hasOpen && !$hasCompleted,
            };

            if (!$follows)
            {
                $fail("Lo stato {$status->value} non è coerente con lo stato delle attività.");
            }
        };
    }
}
