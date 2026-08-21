<?php

declare(strict_types=1);

namespace Cadence\Coaching\Application\ApplyProposal;

use Cadence\Coaching\Domain\Exception\ConversationNotFound;
use Cadence\Coaching\Domain\Port\ConversationRepository;
use Cadence\Coaching\Domain\Port\SessionAdjuster;
use Cadence\Coaching\Domain\ValueObject\ConversationId;
use Cadence\Shared\Application\ExecutionContext;

/** Applies a coach proposal the athlete accepted: adjusts the plan, marks it applied. */
final readonly class ApplyProposalUseCase
{
    public function __construct(
        private ConversationRepository $conversations,
        private SessionAdjuster $adjuster,
    ) {
    }

    public function execute(string $conversationId, string $messageId, ExecutionContext $context): void
    {
        $tenant = $context->tenant;

        $conversation = $this->conversations->ofId(ConversationId::fromString($conversationId), $tenant);
        if ($conversation === null) {
            throw ConversationNotFound::withId($conversationId);
        }

        $proposal = $conversation->markProposalApplied($messageId);
        if ($proposal === null) {
            return; // already applied or not a proposal — idempotent no-op
        }

        $this->adjuster->apply($conversation->programId(), $conversation->cycleId(), $proposal, $tenant);
        $this->conversations->save($conversation);
    }
}
