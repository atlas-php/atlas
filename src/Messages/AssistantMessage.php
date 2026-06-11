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
     * @param  array<int, array<string, mixed>>  $reasoningBlocks  Provider-shaped
     *                                                             reasoning blocks (Anthropic thinking/redacted_thinking, OpenAI
     *                                                             reasoning items) replayed verbatim across tool-loop turns. Each
     *                                                             provider's MessageFactory re-emits only blocks it recognizes.
     */
    public function __construct(
        public readonly ?string $content = null,
        public readonly array $toolCalls = [],
        public readonly ?string $reasoning = null,
        public readonly array $reasoningBlocks = [],
    ) {}

    public function role(): Role
    {
        return Role::Assistant;
    }
}
