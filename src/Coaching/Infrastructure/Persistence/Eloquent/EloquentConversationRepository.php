<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Persistence\Eloquent;

use Cadence\Coaching\Domain\Model\Conversation;
use Cadence\Coaching\Domain\Port\ConversationRepository;
use Cadence\Coaching\Domain\ValueObject\ConversationId;
use Cadence\Shared\Domain\TenantId;
use Cadence\Shared\Infrastructure\Persistence\PersistenceFailure;
use Throwable;

final class EloquentConversationRepository implements ConversationRepository
{
    public function save(Conversation $conversation): void
    {
        $s = $conversation->toSnapshot();

        try {
            ConversationModel::query()->updateOrCreate(['id' => $s['id']], [
                'tenant_id' => $s['tenant_id'],
                'program_id' => $s['program_id'],
                'cycle_id' => $s['cycle_id'],
                'session_date' => $s['session_date'],
                'messages' => $s['messages'],
                'version' => $s['version'],
            ]);
        } catch (Throwable $e) {
            throw new PersistenceFailure('Could not persist the conversation.', 0, $e);
        }
    }

    public function ofId(ConversationId $id, TenantId $tenant): ?Conversation
    {
        $model = ConversationModel::query()
            ->where('id', $id->value)
            ->where('tenant_id', $tenant->value)
            ->first();

        return $model instanceof ConversationModel ? Conversation::fromSnapshot($this->toSnapshot($model)) : null;
    }

    public function forDay(string $programId, string $sessionDate, TenantId $tenant): ?Conversation
    {
        $model = ConversationModel::query()
            ->where('program_id', $programId)
            ->where('session_date', $sessionDate)
            ->where('tenant_id', $tenant->value)
            ->first();

        return $model instanceof ConversationModel ? Conversation::fromSnapshot($this->toSnapshot($model)) : null;
    }

    /**
     * @return array{id:string,tenant_id:string,program_id:string,cycle_id:string,session_date:string,messages:list<array{id:string,role:string,text:string,occurred_at:string,proposal:array{date:string,type:string,title:string,description:string,target_distance_meters:int|null,target_duration_seconds:int|null,target_pace_seconds_per_km:int|null,rationale:string}|null,proposal_applied:bool}>,version:int}
     */
    private function toSnapshot(ConversationModel $model): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $model->messages;

        return [
            'id' => $model->id,
            'tenant_id' => $model->tenant_id,
            'program_id' => $model->program_id,
            'cycle_id' => $model->cycle_id,
            'session_date' => $model->session_date,
            'messages' => array_map(static function (array $m): array {
                /** @var array<string, mixed>|null $p */
                $p = is_array($m['proposal'] ?? null) ? $m['proposal'] : null;

                return [
                    'id' => (string) $m['id'],
                    'role' => (string) $m['role'],
                    'text' => (string) $m['text'],
                    'occurred_at' => (string) $m['occurred_at'],
                    'proposal' => $p === null ? null : [
                        'date' => (string) $p['date'],
                        'type' => (string) $p['type'],
                        'title' => (string) $p['title'],
                        'description' => (string) $p['description'],
                        'target_distance_meters' => isset($p['target_distance_meters']) ? (int) $p['target_distance_meters'] : null,
                        'target_duration_seconds' => isset($p['target_duration_seconds']) ? (int) $p['target_duration_seconds'] : null,
                        'target_pace_seconds_per_km' => isset($p['target_pace_seconds_per_km']) ? (int) $p['target_pace_seconds_per_km'] : null,
                        'rationale' => (string) $p['rationale'],
                    ],
                    'proposal_applied' => (bool) ($m['proposal_applied'] ?? false),
                ];
            }, $rows),
            'version' => (int) $model->version,
        ];
    }
}
