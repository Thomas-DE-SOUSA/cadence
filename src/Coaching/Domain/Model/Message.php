<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Model;

use Cadence\Coaching\Domain\Enum\MessageRole;
use Cadence\Coaching\Domain\ValueObject\SessionProposal;

/**
 * @phpstan-import-type SessionProposalSnapshot from SessionProposal
 *
 * @phpstan-type MessageSnapshot array{id:string,role:string,text:string,occurred_at:string,proposal:SessionProposalSnapshot|null,proposal_applied:bool}
 */
final class Message
{
    public function __construct(
        public readonly string $id,
        public readonly MessageRole $role,
        public readonly string $text,
        public readonly string $occurredAt,
        public readonly ?SessionProposal $proposal,
        public bool $proposalApplied = false,
    ) {
    }

    /** @return MessageSnapshot */
    public function toSnapshot(): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role->value,
            'text' => $this->text,
            'occurred_at' => $this->occurredAt,
            'proposal' => $this->proposal?->toSnapshot(),
            'proposal_applied' => $this->proposalApplied,
        ];
    }

    /** @param MessageSnapshot $s */
    public static function fromSnapshot(array $s): self
    {
        return new self(
            $s['id'],
            MessageRole::from($s['role']),
            $s['text'],
            $s['occurred_at'],
            $s['proposal'] !== null ? SessionProposal::fromSnapshot($s['proposal']) : null,
            $s['proposal_applied'],
        );
    }
}
