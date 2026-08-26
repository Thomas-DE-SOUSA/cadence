<?php

declare(strict_types=1);

use Cadence\Coaching\Domain\Service\AdaptationAnalyzer;

beforeEach(function (): void {
    $this->analyzer = new AdaptationAnalyzer();
});

describe('AdaptationAnalyzer', function (): void {
    it('recommends a deload when load spikes', function (): void {
        // High ACWR -> overload, whatever the rest.
        $r = $this->analyzer->analyze(doneCount: 6, plannedCount: 6, acwr: 1.6, tsb: -10, easyPct: 80);
        expect($r->verdict)->toBe('deload');
    });

    it('recommends a deload when form is deeply negative', function (): void {
        $r = $this->analyzer->analyze(6, 6, 1.1, -35, 80);
        expect($r->verdict)->toBe('deload');
    });

    it('recommends rebalancing when there is too much intensity', function (): void {
        $r = $this->analyzer->analyze(6, 6, 1.0, 0, 60);
        expect($r->verdict)->toBe('rebalance');
    });

    it('recommends progression when compliant, fresh and balanced', function (): void {
        $r = $this->analyzer->analyze(6, 6, 1.0, 2, 82);
        expect($r->verdict)->toBe('progress');
        expect($r->consigne)->toContain('progression');
    });

    it('holds when signals are mixed but safe', function (): void {
        // Compliant + balanced but ACWR a touch low (0.7) -> not "fresh" progress, not risky.
        $r = $this->analyzer->analyze(4, 6, 0.7, 0, 80);
        expect($r->verdict)->toBe('hold');
    });

    it('ignores a cold-start load spike when history is thin', function (): void {
        // High ACWR but load not yet reliable + too much intensity -> rebalance, not deload.
        $r = $this->analyzer->analyze(5, 5, 3.11, -27, 69, reliableLoad: false);
        expect($r->verdict)->toBe('rebalance');
        expect($r->reasons)->toContain('Charge en calibration (peu d’historique)');
    });

    it('does not over-progress on unreliable load', function (): void {
        // Compliant + balanced but load unreliable -> hold, never "progress".
        $r = $this->analyzer->analyze(5, 5, 3.11, -27, 82, reliableLoad: false);
        expect($r->verdict)->toBe('hold');
    });

    it('always explains itself and carries a planner consigne', function (): void {
        $r = $this->analyzer->analyze(5, 6, 1.0, 0, 78);
        expect($r->reasons)->not->toBeEmpty();
        expect($r->suggestions)->not->toBeEmpty();
        expect($r->consigne)->not->toBe('');
    });
});
