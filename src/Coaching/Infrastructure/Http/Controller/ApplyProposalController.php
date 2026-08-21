<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Http\Controller;

use Cadence\Coaching\Application\ApplyProposal\ApplyProposalUseCase;
use Cadence\Coaching\Domain\Exception\ConversationNotFound;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ApplyProposalController
{
    public function __construct(
        private readonly ApplyProposalUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'conversation_id' => ['required', 'string', 'max:64'],
            'message_id' => ['required', 'string', 'max:64'],
            'date' => ['required', 'string', 'max:40'],
            'cycle_id' => ['required', 'string', 'max:64'],
        ]);

        try {
            $this->useCase->execute(
                $data['conversation_id'],
                $data['message_id'],
                new ExecutionContext($this->tenantContext->current()),
            );
        } catch (ConversationNotFound) {
            abort(404);
        }

        $back = route('programs.coach', $id).'?'.http_build_query(['date' => $data['date'], 'cycle' => $data['cycle_id']]);

        return redirect($back)->with('status', 'Séance ajustée par le coach.');
    }
}
