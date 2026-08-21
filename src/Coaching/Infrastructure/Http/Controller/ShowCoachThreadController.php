<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Http\Controller;

use Cadence\Coaching\Domain\Model\Message;
use Cadence\Coaching\Domain\Port\ConversationRepository;
use Cadence\Shared\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Returns the coach conversation for a day as JSON, so the drawer can load it in place. */
final class ShowCoachThreadController
{
    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $tenant = $this->tenantContext->current();
        $date = (string) $request->query('date', '');

        $conversation = $this->conversations->forDay($id, $date, $tenant);

        return new JsonResponse([
            'conversation' => $conversation === null ? null : [
                'id' => $conversation->id()->value,
                'messages' => array_map(fn (Message $m): array => $this->messageView($m), $conversation->messages()),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function messageView(Message $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role->value,
            'text' => $message->text,
            'proposalApplied' => $message->proposalApplied,
            'proposal' => $message->proposal === null ? null : [
                'date' => $message->proposal->date,
                'type' => $message->proposal->type,
                'title' => $message->proposal->title,
                'description' => $message->proposal->description,
                'targetDistanceMeters' => $message->proposal->targetDistanceMeters,
                'targetPaceSecondsPerKm' => $message->proposal->targetPaceSecondsPerKm,
                'rationale' => $message->proposal->rationale,
            ],
        ];
    }
}
