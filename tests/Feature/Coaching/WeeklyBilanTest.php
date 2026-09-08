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

/** A streamer that fails, to exercise the SSE error path. */
final class ThrowingWeeklyCoachStreamer implements WeeklyCoachStreamer
{
    public function stream(WeeklyReviewContext $context, array $history, callable $onText): CoachReply
    {
        throw new RuntimeException('gemini down');
    }
}

function currentMonday(): string
{
    return (new DateTimeImmutable('now'))->modify('monday this week')->format('Y-m-d');
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

    it('rejects a malformed week_start', function (): void {
        $this->post('/muscu/bilan/stream', ['message' => 'x', 'week_start' => 'tuesday'])
            ->assertSessionHasErrors('week_start');
    });

    it('does not leak another tenant’s review thread', function (): void {
        WeeklyReviewModel::query()->create([
            'id' => 'wr-other',
            'tenant_id' => 'tenant-other',
            'week_start' => currentMonday(),
            'messages' => [[
                'id' => 'x', 'role' => 'coach', 'text' => 'secret',
                'occurred_at' => '2026-01-01T00:00:00+00:00', 'proposal' => null, 'proposal_applied' => false,
            ]],
            'version' => 2,
        ]);

        $this->get('/muscu/bilan/thread')->assertOk()->assertExactJson(['thread' => []]);
    });

    it('emits event: error and persists no coach reply when the streamer fails', function (): void {
        $this->app->instance(WeeklyCoachStreamer::class, new ThrowingWeeklyCoachStreamer());

        $body = $this->post('/muscu/bilan/stream', ['message' => 'Fais le bilan.'])->streamedContent();

        expect($body)->toContain('event: error');
        expect($body)->not->toContain('event: done');

        $this->get('/muscu/bilan/thread')
            ->assertOk()
            ->assertJsonCount(1, 'thread')
            ->assertJsonPath('thread.0.role', 'athlete');
    });

});
