<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Model;

use Cadence\Coaching\Domain\Enum\MessageRole;
use Cadence\Coaching\Domain\ValueObject\ConversationId;
use Cadence\Coaching\Domain\ValueObject\SessionProposal;
use Cadence\Shared\Domain\TenantId;

/**
 * A coaching thread about one training day. Holds the athlete/coach messages
 * and the coach's proposals; a proposal is applied only on the athlete's word.
 *
 * @phpstan-import-type MessageSnapshot from Message
 *
 * @phpstan-type ConversationSnapshot array{id:string,tenant_id:string,program_id:string,cycle_id:string,session_date:string,messages:list<MessageSnapshot>,version:int}
 */
final class Conversation
{
    /**
     * @param list<Message> $messages
     */
    private function __construct(
        private readonly ConversationId $id,
        private readonly TenantId $tenant,
        private readonly string $programId,
        private readonly string $cycleId,
        private readonly string $sessionDate,
        private array $messages,
        private int $version,
    ) {
    }

    public static function start(ConversationId $id, TenantId $tenant, string $programId, string $cycleId, string $sessionDate): self
    {
        return new self($id, $tenant, $programId, $cycleId, $sessionDate, [], version: 1);
    }

    public function addAthleteMessage(string $messageId, string $text, string $occurredAt): void
    {
        $this->messages[] = new Message($messageId, MessageRole::ATHLETE, $text, $occurredAt, null);
        $this->version++;
    }

    public function addCoachMessage(string $messageId, string $text, ?SessionProposal $proposal, string $occurredAt): void
    {
        $this->messages[] = new Message($messageId, MessageRole::COACH, $text, $occurredAt, $proposal);
        $this->version++;
    }

    public function markProposalApplied(string $messageId): ?SessionProposal
    {
        foreach ($this->messages as $message) {
            if ($message->id === $messageId && $message->proposal !== null && ! $message->proposalApplied) {
                $message->proposalApplied = true;
                $this->version++;

                return $message->proposal;
            }
        }

        return null;
    }

    public function id(): ConversationId
    {
        return $this->id;
    }

    public function programId(): string
    {
        return $this->programId;
    }

    public function cycleId(): string
    {
        return $this->cycleId;
    }

    public function sessionDate(): string
    {
        return $this->sessionDate;
    }

    /** @return list<Message> */
    public function messages(): array
    {
        return $this->messages;
    }

    /** @return ConversationSnapshot */
    public function toSnapshot(): array
    {
        return [
            'id' => $this->id->value,
            'tenant_id' => $this->tenant->value,
            'program_id' => $this->programId,
            'cycle_id' => $this->cycleId,
            'session_date' => $this->sessionDate,
            'messages' => array_map(static fn (Message $m): array => $m->toSnapshot(), $this->messages),
            'version' => $this->version,
        ];
    }

    /** @param ConversationSnapshot $s */
    public static function fromSnapshot(array $s): self
    {
        return new self(
            ConversationId::fromString($s['id']),
            TenantId::fromString($s['tenant_id']),
            $s['program_id'],
            $s['cycle_id'],
            $s['session_date'],
            array_map(static fn (array $row): Message => Message::fromSnapshot($row), $s['messages']),
            $s['version'],
        );
    }
}
