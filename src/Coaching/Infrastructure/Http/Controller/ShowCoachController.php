<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Http\Controller;

use Cadence\Coaching\Domain\Model\Message;
use Cadence\Coaching\Domain\Port\ConversationRepository;
use Cadence\Coaching\Domain\Port\ProgramContextProvider;
use Cadence\Coaching\Domain\ValueObject\PlannedDay;
use Cadence\Shared\Application\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ShowCoachController
{
    public function __construct(
        private readonly ProgramContextProvider $programs,
        private readonly ConversationRepository $conversations,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $tenant = $this->tenantContext->current();
        $date = (string) $request->query('date', '');
        $cycle = (string) $request->query('cycle', '');

        $programDay = $this->programs->forDay($id, $cycle, $date, $tenant);
        abort_unless($programDay !== null, 404);

        $conversation = $this->conversations->forDay($id, $date, $tenant);

        return Inertia::render('Coach', [
            'programId' => $id,
            'cycleId' => $cycle,
            'programName' => $programDay->programName,
            'day' => $this->dayView($programDay->day),
            'conversation' => $conversation === null ? null : [
                'id' => $conversation->id()->value,
                'messages' => array_map(fn (Message $m): array => $this->messageView($m), $conversation->messages()),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function dayView(PlannedDay $day): array
    {
        return [
            'date' => $day->date,
            'type' => $day->type,
            'title' => $day->title,
            'description' => $day->description,
            'targetDistanceMeters' => $day->targetDistanceMeters,
            'targetDurationSeconds' => $day->targetDurationSeconds,
            'targetPaceSecondsPerKm' => $day->targetPaceSecondsPerKm,
            'actualSummary' => $day->actualSummary,
        ];
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
