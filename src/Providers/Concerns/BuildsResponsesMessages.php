<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Concerns;

use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Providers\Contracts\MediaResolver;
use Atlasphp\Atlas\Requests\TextRequest;

/**
 * Shared Responses API message building logic.
 *
 * Provides user/assistant/toolResult conversion and the message iteration
 * loop used by both OpenAI and xAI message factories.
 */
trait BuildsResponsesMessages
{
    /**
     * @return array<string, mixed>
     */
    public function user(UserMessage $message, MediaResolver $media): array
    {
        if (empty($message->media)) {
            return [
                'role' => 'user',
                'content' => $message->content,
            ];
        }

        $content = [];
        $content[] = ['type' => 'input_text', 'text' => $message->content];

        foreach ($message->media as $input) {
            $content[] = $media->resolve($input);
        }

        return [
            'role' => 'user',
            'content' => $content,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function assistant(AssistantMessage $message): array
    {
        return [
            'role' => 'assistant',
            'content' => $message->content ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toolResult(ToolResultMessage $message): array
    {
        return [
            'type' => 'function_call_output',
            'call_id' => $message->toolCallId,
            'output' => $message->content,
        ];
    }

    /**
     * Collect input items from a request's messages.
     *
     * Iterates messages, extracts instructions from system messages,
     * and builds the Responses API input array. The caller decides
     * what to do with instructions (top-level param vs system message).
     *
     * @param  array<int, array<string, mixed>>  $input
     */
    protected function collectInputItems(TextRequest $request, MediaResolver $media, ?string &$instructions, array &$input): void
    {
        foreach ($request->messages as $message) {
            if ($message instanceof SystemMessage) {
                $instructions ??= $message->content;
            } elseif ($message instanceof UserMessage) {
                $input[] = $this->user($message, $media);
            } elseif ($message instanceof AssistantMessage) {
                $this->expandAssistant($message, $input);
            } elseif ($message instanceof ToolResultMessage) {
                $input[] = $this->toolResult($message);
            }
        }

        if ($request->message !== null) {
            $userMessage = new UserMessage($request->message, $request->messageMedia);
            $input[] = $this->user($userMessage, $media);
        }
    }

    /**
     * Expand an assistant message into input items.
     *
     * @param  array<int, array<string, mixed>>  $input
     */
    protected function expandAssistant(AssistantMessage $message, array &$input): void
    {
        if ($message->content !== null && $message->content !== '') {
            $input[] = [
                'role' => 'assistant',
                'content' => $message->content,
            ];
        }

        foreach ($message->toolCalls as $toolCall) {
            $input[] = [
                'type' => 'function_call',
                'call_id' => $toolCall->id,
                'name' => $toolCall->name,
                'arguments' => json_encode($toolCall->arguments),
            ];
        }
    }
}
