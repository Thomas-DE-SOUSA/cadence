<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Model;

use Cadence\Coaching\Domain\Enum\MessageRole;
use Cadence\Coaching\Domain\ValueObject\WeeklyReviewId;
use Cadence\Shared\Domain\TenantId;

/**
 * A cross-modal coaching thread about one Mon–Sun week (running + strength).
 * Its own aggregate — keyed by the week, not tied to a training program — so
 * the per-day {@see Conversation} stays untouched.
 *
 * @phpstan-import-type MessageSnapshot from Message
 *
 * @phpstan-type WeeklyReviewSnapshot array{id:string,tenant_id:string,week_start:string,messages:list<MessageSnapshot>,version:int}
 */
final class WeeklyReview
{
    /**
     * @param list<Message> $messages
     */
    private function __construct(
        private readonly WeeklyReviewId $id,
        private readonly TenantId $tenant,
        private readonly string $weekStart,
        private array $messages,
        private int $version,
    ) {
    }

    public static function start(WeeklyReviewId $id, TenantId $tenant, string $weekStart): self
    {
        return new self($id, $tenant, $weekStart, [], version: 1);
    }

    public function addAthleteMessage(string $messageId, string $text, string $occurredAt): void
    {
        $this->messages[] = new Message($messageId, MessageRole::ATHLETE, $text, $occurredAt, null);
        $this->version++;
    }

    public function addCoachMessage(string $messageId, string $text, string $occurredAt): void
    {
        // No proposals in the weekly verdict: running/muscu changes are advice.
        $this->messages[] = new Message($messageId, MessageRole::COACH, $text, $occurredAt, null);
        $this->version++;
    }

    public function id(): WeeklyReviewId
    {
        return $this->id;
    }

    public function weekStart(): string
    {
        return $this->weekStart;
    }

    /** @return list<Message> */
    public function messages(): array
    {
        return $this->messages;
    }

    /** @return WeeklyReviewSnapshot */
    public function toSnapshot(): array
    {
        return [
            'id' => $this->id->value,
            'tenant_id' => $this->tenant->value,
            'week_start' => $this->weekStart,
            'messages' => array_map(static fn (Message $m): array => $m->toSnapshot(), $this->messages),
            'version' => $this->version,
        ];
    }

    /** @param WeeklyReviewSnapshot $s */
    public static function fromSnapshot(array $s): self
    {
        return new self(
            WeeklyReviewId::fromString($s['id']),
            TenantId::fromString($s['tenant_id']),
            $s['week_start'],
            array_map(static fn (array $row): Message => Message::fromSnapshot($row), $s['messages']),
            $s['version'],
        );
    }
}
