<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Anthropic;

use Atlasphp\Atlas\Enums\Role;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Providers\Contracts\MediaResolver;
use Atlasphp\Atlas\Providers\Contracts\MessageFactory as MessageFactoryContract;
use Atlasphp\Atlas\Requests\TextRequest;

/**
 * Converts Atlas messages into Anthropic's Messages API format.
 *
 * Anthropic separates system instructions as a top-level parameter
 * and uses content block arrays for multi-part messages.
 */
class MessageFactory implements MessageFactoryContract
{
    /**
     * @return array<string, mixed>
     */
    public function system(SystemMessage $message): array
    {
        return ['text' => $message->content];
    }

    /**
     * @return array<string, mixed>
     */
    public function user(UserMessage $message, MediaResolver $media): array
    {
        $content = [];

        foreach ($message->media as $input) {
            $content[] = $media->resolve($input);
        }

        if ($message->content !== '') {
            $content[] = ['type' => 'text', 'text' => $message->content];
        }

        return [
            'role' => 'user',
            'content' => count($content) === 1 && ($content[0]['type'] ?? '') === 'text'
                ? $message->content
                : $content,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function assistant(AssistantMessage $message): array
    {
        $content = [];

        if ($message->content !== null && $message->content !== '') {
            $content[] = ['type' => 'text', 'text' => $message->content];
        }

        foreach ($message->toolCalls as $toolCall) {
            $content[] = [
                'type' => 'tool_use',
                'id' => $toolCall->id,
                'name' => $toolCall->name,
                'input' => $toolCall->arguments ?: (object) [],
            ];
        }

        return [
            'role' => 'assistant',
            'content' => $content,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toolResult(ToolResultMessage $message): array
    {
        return [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'tool_result',
                    'tool_use_id' => $message->toolCallId,
                    'content' => $message->content,
                ],
            ],
        ];
    }

    /**
     * Build the full request structure.
     *
     * @return array{system: string|null, messages: array<int, array<string, mixed>>}
     */
    public function buildAll(TextRequest $request, MediaResolver $media): array
    {
        $systemParts = [];
        $messages = [];

        if ($request->instructions !== null && $request->instructions !== '') {
            $systemParts[] = $request->instructions;
        }

        foreach ($request->messages as $message) {
            if ($message->role() === Role::System) {
                $systemParts[] = $message->content;

                continue;
            }

            $entry = match ($message->role()) {
                Role::User => $this->user($message, $media),
                Role::Assistant => $this->assistant($message),
                Role::Tool => $this->toolResult($message),
                default => null,
            };

            if ($entry !== null) {
                $messages[] = $entry;
            }
        }

        if ($request->message !== null) {
            $userMessage = new UserMessage($request->message, $request->messageMedia);
            $messages[] = $this->user($userMessage, $media);
        }

        return [
            'system' => $systemParts !== [] ? implode("\n\n", $systemParts) : null,
            'messages' => $messages,
        ];
    }
}
