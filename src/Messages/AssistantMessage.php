<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Messages;

use Atlasphp\Atlas\Enums\Role;

/**
 * A message from the assistant, which may include tool calls and reasoning.
 */
class AssistantMessage extends Message
{
    /**
     * @param  array<int, ToolCall>  $toolCalls
     */
    public function __construct(
        public readonly ?string $content = null,
        public readonly array $toolCalls = [],
        public readonly ?string $reasoning = null,
    ) {}

    public function role(): Role
    {
        return Role::Assistant;
    }
}
