<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Messages;

use Atlasphp\Atlas\Enums\Role;

/**
 * A message containing the result of a tool execution.
 */
class ToolResultMessage extends Message
{
    /**
     * @param  bool  $isError  Whether the tool execution failed. Providers that support error signaling (e.g. Anthropic's is_error) use this to tell the model the tool failed.
     */
    public function __construct(
        public readonly string $toolCallId,
        public readonly string $content,
        public readonly ?string $toolName = null,
        public readonly bool $isError = false,
    ) {}

    public function role(): Role
    {
        return Role::Tool;
    }
}
