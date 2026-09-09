<?php

declare(strict_types=1);

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Domain\TenantId;
use Cadence\Strength\Application\Port\EstimatedFood;
use Cadence\Strength\Application\Port\FoodEstimator;
use Cadence\Strength\Application\UseCase\LogFood\LogFoodInput;
use Cadence\Strength\Application\UseCase\LogFood\LogFoodUseCase;
use Cadence\Strength\Application\UseCase\RemoveNutritionEntry\RemoveNutritionEntryUseCase;
use Cadence\Strength\Domain\Port\NutritionEntryRepository;
use Cadence\Strength\Infrastructure\Read\NutritionView;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** A deterministic estimator standing in for Gemini. */
final class FakeFoodEstimator implements FoodEstimator
{
    /** @param list<EstimatedFood> $foods */
    public function __construct(private array $foods)
    {
    }

    public function estimate(string $text): array
    {
        return $this->foods;
    }
}

function nutritionCtx(string $tenant = 'tenant-thomas'): ExecutionContext
{
    return new ExecutionContext(TenantId::fromString($tenant));
}

function bindEstimator(array $foods): void
{
    app()->instance(FoodEstimator::class, new FakeFoodEstimator($foods));
}

describe('Feature: nutrition daily tracking', function (): void {
    it('estimates a free-text meal into entries and aggregates the day against targets', function (): void {
        bindEstimator([
            new EstimatedFood('Énorme part de lasagne', 900, 45, 45, 75),
            new EstimatedFood('Monster Bad Apple 500 ml', 230, 0, 0, 57),
            new EstimatedFood('25 bâtons du berger', 1010, 57, 85, 2),
        ]);

        $saved = app(LogFoodUseCase::class)->execute(
            new LogFoodInput('une énorme part de lasagne, un monster, 25 bâtons du berger', 'midi', '2026-09-09'),
            nutritionCtx(),
        );

        expect($saved)->toHaveCount(3);

        $entries = app(NutritionEntryRepository::class)->forDate(nutritionCtx()->tenant, '2026-09-09');
        expect($entries)->toHaveCount(3);

        $day = NutritionView::day('2026-09-09', $entries);
        expect($day['totals'])->toBe(['kcal' => 2140, 'protein' => 102, 'fat' => 130, 'carbs' => 134]);
        expect($day['remaining']['kcal'])->toBe(3600 - 2140);
        expect($day['remaining']['fat'])->toBe(95 - 130); // over target → negative

        // All three land under the "midi" meal, none elsewhere.
        $midi = collect($day['meals'])->firstWhere('key', 'midi');
        $matin = collect($day['meals'])->firstWhere('key', 'matin');
        expect($midi['entries'])->toHaveCount(3);
        expect($matin['entries'])->toHaveCount(0);
        expect($midi['subtotal']['kcal'])->toBe(2140);
    });

    it('removes a single entry', function (): void {
        bindEstimator([
            new EstimatedFood('Skyr', 100, 17, 0, 8),
            new EstimatedFood('Flocons', 190, 6, 4, 33),
        ]);
        $saved = app(LogFoodUseCase::class)->execute(new LogFoodInput('skyr + flocons', 'matin', '2026-09-09'), nutritionCtx());

        app(RemoveNutritionEntryUseCase::class)->execute($saved[0]->id, nutritionCtx());

        $entries = app(NutritionEntryRepository::class)->forDate(nutritionCtx()->tenant, '2026-09-09');
        expect($entries)->toHaveCount(1);
        expect($entries[0]->description)->toBe('Flocons');
    });

    it('recognises nothing → saves no entries', function (): void {
        bindEstimator([]);
        $saved = app(LogFoodUseCase::class)->execute(new LogFoodInput('euh rien', 'soir', '2026-09-09'), nutritionCtx());

        expect($saved)->toBe([]);
        expect(app(NutritionEntryRepository::class)->forDate(nutritionCtx()->tenant, '2026-09-09'))->toHaveCount(0);
    });

    it('keeps entries private to their tenant', function (): void {
        bindEstimator([new EstimatedFood('Riz', 260, 5, 1, 56)]);
        app(LogFoodUseCase::class)->execute(new LogFoodInput('200g de riz', 'midi', '2026-09-09'), nutritionCtx('tenant-thomas'));

        expect(app(NutritionEntryRepository::class)->forDate(nutritionCtx('tenant-thomas')->tenant, '2026-09-09'))->toHaveCount(1);
        expect(app(NutritionEntryRepository::class)->forDate(nutritionCtx('tenant-other')->tenant, '2026-09-09'))->toHaveCount(0);
    });
});
