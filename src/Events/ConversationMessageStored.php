<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Enums\Role;

/**
 * Dispatched when a message is persisted to a conversation.
 */
class ConversationMessageStored
{
    public function __construct(
        public readonly int $conversationId,
        public readonly int $messageId,
        public readonly Role $role,
        public readonly ?string $agentKey,
    ) {}
}
