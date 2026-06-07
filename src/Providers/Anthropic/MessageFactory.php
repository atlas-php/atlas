<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Anthropic;

use Atlasphp\Atlas\Enums\Role;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Providers\Contracts\MediaResolverContract;
use Atlasphp\Atlas\Providers\Contracts\MessageFactoryContract;
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
    public function user(UserMessage $message, MediaResolverContract $media): array
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
        $block = [
            'type' => 'tool_result',
            'tool_use_id' => $message->toolCallId,
            'content' => $message->content,
        ];

        if ($message->isError) {
            $block['is_error'] = true;
        }

        return [
            'role' => 'user',
            'content' => [$block],
        ];
    }

    /**
     * Build the full request structure.
     *
     * @return array{system: string|null, messages: array<int, array<string, mixed>>}
     */
    public function buildAll(TextRequest $request, MediaResolverContract $media): array
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

        $system = $systemParts !== [] ? implode("\n\n", $systemParts) : null;

        // Anthropic needs explicit cache breakpoints (unlike OpenAI/xAI/Google,
        // which cache automatically). Mark the system block and the end of the
        // message history so the static prefix is reused on the next turn.
        if ($request->cache) {
            return [
                'system' => $this->cacheSystem($system),
                'messages' => $this->cacheTrailingMessage($messages),
            ];
        }

        return [
            'system' => $system,
            'messages' => $messages,
        ];
    }

    /**
     * Wrap the system prompt in a cacheable text block so the model can reuse
     * it (plus the tool definitions, which precede it) across turns.
     *
     * @return string|array<int, array<string, mixed>>|null
     */
    private function cacheSystem(?string $system): string|array|null
    {
        if ($system === null || $system === '') {
            return $system;
        }

        return [[
            'type' => 'text',
            'text' => $system,
            'cache_control' => ['type' => 'ephemeral'],
        ]];
    }

    /**
     * Mark the final block of the last message as a cache breakpoint, so the
     * whole prior conversation prefix is cached and reused next turn.
     *
     * Only user messages are marked: their trailing block is always a cacheable
     * type (text/image/document/tool_result), whereas an assistant message can
     * end in a `tool_use` or `thinking` block that must not carry cache_control.
     * Skipping a trailing assistant message still caches system + tools.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<int, array<string, mixed>>
     */
    private function cacheTrailingMessage(array $messages): array
    {
        if ($messages === []) {
            return $messages;
        }

        $lastIndex = array_key_last($messages);

        if (($messages[$lastIndex]['role'] ?? null) !== 'user') {
            return $messages;
        }

        $content = $messages[$lastIndex]['content'];

        if (is_string($content)) {
            $content = [[
                'type' => 'text',
                'text' => $content,
                'cache_control' => ['type' => 'ephemeral'],
            ]];
        } elseif (is_array($content) && $content !== []) {
            $blockKey = array_key_last($content);
            $content[$blockKey]['cache_control'] = ['type' => 'ephemeral'];
        }

        $messages[$lastIndex]['content'] = $content;

        return $messages;
    }
}
