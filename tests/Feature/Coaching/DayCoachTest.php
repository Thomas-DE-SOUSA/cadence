<?php

declare(strict_types=1);

use Cadence\Coaching\Domain\Port\CoachChat;
use Cadence\Coaching\Domain\Port\CoachStreamer;
use Cadence\Coaching\Domain\ValueObject\CoachContext;
use Cadence\Coaching\Domain\ValueObject\CoachReply;
use Cadence\Coaching\Domain\ValueObject\SessionProposal;
use Cadence\Coaching\Infrastructure\Persistence\Eloquent\ConversationModel;
use Cadence\Training\Infrastructure\Persistence\Eloquent\CycleModel;
use Cadence\Training\Infrastructure\Persistence\Eloquent\TrainingProgramModel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sessionType(string $cycleId, string $date): ?string
{
    /** @var list<array<string, mixed>> $sessions */
    $sessions = CycleModel::query()->find($cycleId)?->sessions ?? [];
    foreach ($sessions as $s) {
        if (($s['date'] ?? null) === $date) {
            return $s['type'];
        }
    }

    return null;
}

describe('Feature: Day coach', function (): void {
    it('discusses a day, proposes a change, and applies it on demand', function (): void {
        // A deterministic coach: it always suggests turning the day into rest.
        $this->app->bind(CoachChat::class, fn (): CoachChat => new class implements CoachChat
        {
            public function reply(CoachContext $context, array $history): CoachReply
            {
                return new CoachReply(
                    'Vu ta fatigue, on transforme cette séance en repos actif.',
                    new SessionProposal($context->day->date, 'REST', 'Repos', 'Repos complet ou 20 min très souple.', null, null, null, 'Récupération pour absorber la charge.'),
                );
            }
        });

        $this->post('/programme', [
            'name' => 'Prépa Odysséa',
            'plan_key' => 'sub40-10k',
            'start_date' => '2026-08-24T00:00:00.000Z',
            'priority' => 'A',
            'objectives' => [],
        ])->assertRedirect();

        $programId = (string) TrainingProgramModel::query()->value('id');
        $cycleId = (string) CycleModel::query()->value('id');
        $date = '2026-08-25'; // day 1 of Fondation — a Footing (EASY)

        expect(sessionType($cycleId, $date))->toBe('EASY');

        // Athlete sends a message; the coach replies with a proposal.
        $this->post("/programme/{$programId}/coach/message", [
            'cycle_id' => $cycleId,
            'date' => $date,
            'message' => 'Grosse fatigue musculaire, je ne me sens pas de faire cette séance.',
        ])->assertRedirect();

        $conversation = ConversationModel::query()->first();
        expect($conversation)->not->toBeNull();
        /** @var list<array<string, mixed>> $messages */
        $messages = $conversation->messages;
        expect($messages)->toHaveCount(2);
        expect($messages[0]['role'])->toBe('athlete');
        expect($messages[1]['role'])->toBe('coach');
        expect($messages[1]['proposal']['type'])->toBe('REST');

        // The plan is unchanged until the athlete accepts.
        expect(sessionType($cycleId, $date))->toBe('EASY');

        // Accept → the day becomes REST and the proposal is marked applied.
        $this->post("/programme/{$programId}/coach/apply", [
            'conversation_id' => $conversation->id,
            'message_id' => $messages[1]['id'],
            'date' => $date,
            'cycle_id' => $cycleId,
        ])->assertRedirect();

        expect(sessionType($cycleId, $date))->toBe('REST');

        /** @var list<array<string, mixed>> $after */
        $after = ConversationModel::query()->first()->messages;
        expect($after[1]['proposal_applied'])->toBeTrue();
    });

    it('streams the coach reply and persists the turn', function (): void {
        $this->app->bind(CoachStreamer::class, fn (): CoachStreamer => new class implements CoachStreamer
        {
            public function stream(CoachContext $context, array $history, callable $onText): CoachReply
            {
                $onText('Repose-');
                $onText('toi bien.');

                return new CoachReply('Repose-toi bien.', new SessionProposal($context->day->date, 'REST', 'Repos', 'Repos complet.', null, null, null, 'Récup.'));
            }
        });

        $this->post('/programme', [
            'name' => 'Bloc', 'plan_key' => 'sub40-10k', 'start_date' => '2026-08-24T00:00:00.000Z',
            'priority' => 'A', 'objectives' => [],
        ])->assertRedirect();
        $programId = (string) TrainingProgramModel::query()->value('id');
        $cycleId = (string) CycleModel::query()->value('id');

        $response = $this->post("/programme/{$programId}/coach/stream", [
            'cycle_id' => $cycleId, 'date' => '2026-08-25', 'message' => 'Grosse fatigue.',
        ]);

        $content = $response->streamedContent();
        expect($content)->toContain('event: text')->toContain('Repose-')->toContain('event: done');

        /** @var list<array<string, mixed>> $messages */
        $messages = ConversationModel::query()->first()->messages;
        expect($messages)->toHaveCount(2);
        expect($messages[1]['role'])->toBe('coach');
        expect($messages[1]['text'])->toBe('Repose-toi bien.');
        expect($messages[1]['proposal']['type'])->toBe('REST');
    });

    it('validates the message input', function (): void {
        $this->post('/programme', [
            'name' => 'Bloc', 'plan_key' => 'sub40-10k', 'start_date' => '2026-08-24T00:00:00.000Z',
            'priority' => 'A', 'objectives' => [],
        ])->assertRedirect();
        $programId = (string) TrainingProgramModel::query()->value('id');

        $this->postJson("/programme/{$programId}/coach/message", ['cycle_id' => '', 'date' => '', 'message' => ''])
            ->assertStatus(422)->assertJsonValidationErrors(['cycle_id', 'date', 'message']);
    });
});
