<?php

declare(strict_types=1);

use Cadence\Coaching\Domain\Port\WeeklyCoachStreamer;
use Cadence\Coaching\Domain\ValueObject\CoachReply;
use Cadence\Coaching\Domain\ValueObject\WeeklyReviewContext;
use Cadence\Coaching\Infrastructure\Persistence\Eloquent\WeeklyReviewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/** A streamer that emits two deltas and never touches Gemini. */
final class FakeWeeklyCoachStreamer implements WeeklyCoachStreamer
{
    public function stream(WeeklyReviewContext $context, array $history, callable $onText): CoachReply
    {
        $onText('Bonne ');
        $onText('semaine, muscu bien placée.');

        return new CoachReply('Bonne semaine, muscu bien placée.', null);
    }
}

describe('Feature: weekly bilan', function (): void {
    it('renders the review page with the (empty) week and no thread', function (): void {
        $this->get('/muscu/bilan')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('MuscuBilan')
                ->where('strength.hasData', false)
                ->where('thread', [])
                ->has('weekStart')
                ->has('weekEnd'),
        );
    });

    it('streams the verdict over SSE and persists the thread', function (): void {
        $this->app->instance(WeeklyCoachStreamer::class, new FakeWeeklyCoachStreamer());

        $response = $this->post('/muscu/bilan/stream', ['message' => 'Fais le bilan de ma semaine.']);
        $response->assertStatus(200);

        $body = $response->streamedContent();
        expect($body)->toContain('event: text');
        expect($body)->toContain('event: done');

        // One review row, two messages (athlete + coach).
        expect(WeeklyReviewModel::query()->where('tenant_id', 'tenant-thomas')->count())->toBe(1);
    });

    it('returns the persisted thread as JSON', function (): void {
        $this->app->instance(WeeklyCoachStreamer::class, new FakeWeeklyCoachStreamer());
        $this->post('/muscu/bilan/stream', ['message' => 'Fais le bilan.'])->streamedContent();

        $this->get('/muscu/bilan/thread')
            ->assertOk()
            ->assertJsonCount(2, 'thread')
            ->assertJsonPath('thread.0.role', 'athlete')
            ->assertJsonPath('thread.1.role', 'coach')
            ->assertJsonPath('thread.1.text', 'Bonne semaine, muscu bien placée.');
    });

    it('rejects an empty message', function (): void {
        $this->post('/muscu/bilan/stream', ['message' => ''])->assertSessionHasErrors('message');
    });
});
