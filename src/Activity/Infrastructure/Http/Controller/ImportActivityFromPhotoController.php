<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Http\Controller;

use Cadence\Activity\Application\Port\Exception\ActivityTextUnparseable;
use Cadence\Activity\Application\UseCase\ImportActivityFromPhoto\ImportActivityFromPhotoUseCase;
use Cadence\Activity\Domain\Exception\DuplicateActivity;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final class ImportActivityFromPhotoController
{
    public function __construct(
        private readonly ImportActivityFromPhotoUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:12288'],
            'occurred_at' => ['nullable', 'string', 'max:40'],
        ]);

        $file = $request->file('photo');
        if (! $file instanceof UploadedFile) {
            return back()->withErrors(['photo' => 'Fichier invalide.']);
        }

        $bytes = (string) file_get_contents($file->getRealPath());
        $mime = $file->getMimeType() ?? 'image/jpeg';
        $override = $request->input('occurred_at');

        try {
            $output = $this->useCase->execute(
                $bytes,
                $mime,
                new ExecutionContext($this->tenantContext->current()),
                is_string($override) && $override !== '' ? $override : null,
            );
        } catch (DuplicateActivity) {
            return back()->withErrors(['photo' => 'Cette sortie semble déjà enregistrée.']);
        } catch (ActivityTextUnparseable $e) {
            return back()->withErrors(['photo' => 'Photo illisible : '.$e->getMessage()]);
        }

        if (! $output->imported || $output->activityId === null) {
            return back()->with('status', 'Cette activité est déjà importée.');
        }

        return redirect()
            ->route('activities.show', $output->activityId)
            ->with('status', 'Activité importée depuis la photo.');
    }
}
