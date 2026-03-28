<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending\Concerns;

use Atlasphp\Atlas\Enums\Role;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\Message;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use InvalidArgumentException;

/**
 * Converts mixed arrays of typed messages and shorthand arrays into Message[].
 *
 * Supports both Message instances (passed through) and array shorthand
 * with role/content keys.
 */
trait NormalizesMessages
{
    /**
     * @param  array<int, mixed>  $messages
     * @return array<int, Message>
     */
    protected function normalizeMessages(array $messages): array
    {
        return array_map(
            fn (mixed $msg): Message => $msg instanceof Message
                ? $msg
                : $this->messageFromArray($msg),
            $messages,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function messageFromArray(array $data): Message
    {
        $role = Role::tryFrom($data['role'] ?? '');

        if ($role === null) {
            $given = $data['role'] ?? '(missing)';

            throw new InvalidArgumentException(
                "Invalid message role: '{$given}'. Expected one of: system, user, assistant, tool.",
            );
        }

        $content = $data['content'] ?? '';

        return match ($role) {
            Role::System => new SystemMessage($content),
            Role::User => new UserMessage($content),
            Role::Assistant => new AssistantMessage(content: $content),
            Role::Tool => new ToolResultMessage(
                toolCallId: $data['toolCallId'] ?? '',
                content: $content,
                toolName: $data['toolName'] ?? null,
            ),
        };
    }
}
