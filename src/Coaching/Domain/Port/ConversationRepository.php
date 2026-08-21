<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Port;

use Cadence\Coaching\Domain\Model\Conversation;
use Cadence\Coaching\Domain\ValueObject\ConversationId;
use Cadence\Shared\Domain\TenantId;

interface ConversationRepository
{
    public function save(Conversation $conversation): void;

    public function ofId(ConversationId $id, TenantId $tenant): ?Conversation;

    public function forDay(string $programId, string $sessionDate, TenantId $tenant): ?Conversation;
}
