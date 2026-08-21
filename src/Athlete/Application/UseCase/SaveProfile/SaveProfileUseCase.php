<?php

declare(strict_types=1);

namespace Cadence\Athlete\Application\UseCase\SaveProfile;

use Cadence\Athlete\Domain\Event\AthleteProfileSaved;
use Cadence\Athlete\Domain\Model\Athlete;
use Cadence\Athlete\Domain\Port\AthleteRepository;
use Cadence\Athlete\Domain\ValueObject\AthleteId;
use Cadence\Shared\Application\AuditTrail;
use Cadence\Shared\Application\EventPublisher;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Shared\Identifier\IdGenerator;

/**
 * Upserts the tenant's single athlete profile: creates it on first save,
 * updates it (with an optimistic version bump) thereafter.
 */
final readonly class SaveProfileUseCase
{
    public function __construct(
        private AthleteRepository $athletes,
        private IdGenerator $ids,
        private Clock $clock,
        private EventPublisher $eventPublisher,
        private AuditTrail $auditTrail,
    ) {
    }

    public function execute(SaveProfileInput $input, ExecutionContext $context): string
    {
        $tenant = $context->tenant;
        $profile = $input->toProfileData();

        $athlete = $this->athletes->ofTenant($tenant);
        if ($athlete === null) {
            $athlete = Athlete::create(AthleteId::generate($this->ids), $tenant, $profile, $this->clock->now());
        } else {
            $athlete->updateProfile($profile, $this->clock->now());
        }

        $events = $athlete->releaseEvents();
        $this->athletes->save($athlete, $events);
        $this->eventPublisher->publish($events);
        $this->auditTrail->record(AthleteProfileSaved::NAME, $tenant, $athlete->id()->value, $athlete->toSnapshot(), $this->clock->now());

        return $athlete->id()->value;
    }
}
