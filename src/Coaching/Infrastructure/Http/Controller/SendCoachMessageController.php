<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Http\Controller;

use Cadence\Coaching\Application\SendCoachMessage\SendCoachMessageInput;
use Cadence\Coaching\Application\SendCoachMessage\SendCoachMessageUseCase;
use Cadence\Coaching\Domain\Exception\DayContextMissing;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final class SendCoachMessageController
{
    public function __construct(
        private readonly SendCoachMessageUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'cycle_id' => ['required', 'string', 'max:64'],
            'date' => ['required', 'string', 'max:40'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $back = route('programs.show', $id);

        try {
            $this->useCase->execute(
                new SendCoachMessageInput($id, $data['cycle_id'], $data['date'], $data['message']),
                new ExecutionContext($this->tenantContext->current()),
            );
        } catch (DayContextMissing) {
            abort(404);
        } catch (Throwable $e) {
            return redirect($back)->withErrors(['message' => $e->getMessage()]);
        }

        return redirect($back);
    }
}
