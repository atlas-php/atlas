<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\ChatCompletions;

use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Providers\Contracts\MediaResolver;
use Atlasphp\Atlas\Providers\Contracts\MessageFactory as MessageFactoryContract;
use Atlasphp\Atlas\Requests\TextRequest;

/**
 * Converts typed Atlas messages into Chat Completions API format.
 *
 * Uses standard roles (system, user, assistant, tool), nested tool_calls
 * with function wrapper, and a flat messages array.
 */
class MessageFactory implements MessageFactoryContract
{
    /**
     * @return array<string, mixed>
     */
    public function system(SystemMessage $message): array
    {
        return [
            'role' => 'system',
            'content' => $message->content,
        ];
    }

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

        $content = [['type' => 'text', 'text' => $message->content]];

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
        $msg = ['role' => 'assistant', 'content' => $message->content ?? ''];

        if (! empty($message->toolCalls)) {
            $msg['tool_calls'] = array_map(fn ($tc) => [
                'id' => $tc->id,
                'type' => 'function',
                'function' => [
                    'name' => $tc->name,
                    'arguments' => json_encode($tc->arguments),
                ],
            ], $message->toolCalls);
        }

        return $msg;
    }

    /**
     * @return array<string, mixed>
     */
    public function toolResult(ToolResultMessage $message): array
    {
        return [
            'role' => 'tool',
            'tool_call_id' => $message->toolCallId,
            'content' => $message->content,
        ];
    }

    /**
     * Build the full messages array for a Chat Completions request.
     *
     * Instructions become the first system message. Returns an associative
     * array with a 'messages' key containing the flat messages array.
     *
     * @return array<string, mixed>
     */
    public function buildAll(TextRequest $request, MediaResolver $media): array
    {
        $messages = [];

        if ($request->instructions !== null) {
            $messages[] = ['role' => 'system', 'content' => $request->instructions];
        }

        foreach ($request->messages as $message) {
            if ($message instanceof SystemMessage) {
                $messages[] = $this->system($message);
            } elseif ($message instanceof UserMessage) {
                $messages[] = $this->user($message, $media);
            } elseif ($message instanceof AssistantMessage) {
                $messages[] = $this->assistant($message);
            } elseif ($message instanceof ToolResultMessage) {
                $messages[] = $this->toolResult($message);
            }
        }

        if ($request->message !== null) {
            $userMessage = new UserMessage($request->message, $request->messageMedia);
            $messages[] = $this->user($userMessage, $media);
        }

        return ['messages' => $messages];
    }
}
