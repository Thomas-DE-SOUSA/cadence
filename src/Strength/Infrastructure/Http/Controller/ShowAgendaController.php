<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\TenantContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Strength\Domain\Port\StrengthSessionRepository;
use Cadence\Strength\Domain\Port\WorkoutTemplateRepository;
use Cadence\Strength\Infrastructure\Read\StrengthView;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ShowAgendaController
{
    private const WEEKDAYS = [1 => 'lun', 2 => 'mar', 3 => 'mer', 4 => 'jeu', 5 => 'ven', 6 => 'sam', 7 => 'dim'];

    private const MONTHS = [1 => 'janv.', 2 => 'févr.', 3 => 'mars', 4 => 'avr.', 5 => 'mai', 6 => 'juin', 7 => 'juil.', 8 => 'août', 9 => 'sept.', 10 => 'oct.', 11 => 'nov.', 12 => 'déc.'];

    public function __construct(
        private readonly StrengthSessionRepository $sessions,
        private readonly WorkoutTemplateRepository $templates,
        private readonly TenantContext $tenantContext,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $tenant = $this->tenantContext->current();
        $offset = (int) $request->query('week', '0');
        $today = $this->clock->now()->format('Y-m-d');

        $monday = (new DateTimeImmutable($today))->modify('monday this week')->modify(($offset >= 0 ? '+' : '').$offset.' week');
        $sunday = $monday->modify('+6 days');

        $summaries = StrengthView::summaries($this->sessions->forRange($tenant, $monday->format('Y-m-d'), $sunday->format('Y-m-d')));

        /** @var array<string, list<array<string, mixed>>> $byDate */
        $byDate = [];
        foreach ($summaries as $s) {
            $byDate[(string) $s['date']][] = $s;
        }

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $monday->modify("+{$i} days");
            $key = $d->format('Y-m-d');
            $days[] = [
                'date' => $key,
                'dayLabel' => self::WEEKDAYS[(int) $d->format('N')].' '.$d->format('j'),
                'isToday' => $key === $today,
                'sessions' => $byDate[$key] ?? [],
            ];
        }

        return Inertia::render('MuscuAgenda', [
            'weekLabel' => $this->weekLabel($monday, $sunday),
            'weekOffset' => $offset,
            'days' => $days,
            'templates' => StrengthView::templates($this->templates->forTenant($tenant)),
        ]);
    }

    private function weekLabel(DateTimeImmutable $from, DateTimeImmutable $to): string
    {
        $fromLabel = $from->format('j').' '.self::MONTHS[(int) $from->format('n')];
        $toLabel = $to->format('j').' '.self::MONTHS[(int) $to->format('n')];

        return $fromLabel.' – '.$toLabel;
    }
}
