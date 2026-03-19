<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi;

use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Providers\Contracts\MediaResolver;
use Atlasphp\Atlas\Providers\Contracts\MessageFactory as MessageFactoryContract;
use Atlasphp\Atlas\Requests\TextRequest;

/**
 * Converts typed Atlas messages into OpenAI Responses API input format.
 *
 * Key differences from Chat Completions: instructions are a top-level parameter,
 * content parts use input_text/input_image types, tool results are function_call_output
 * items, and assistant messages with tool calls expand to function_call input items.
 */
class MessageFactory implements MessageFactoryContract
{
    /**
     * @return array<string, mixed>
     */
    public function system(SystemMessage $message): array
    {
        return [
            'role' => 'developer',
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
     * Build the full input array and extract instructions.
     *
     * Returns an associative array with 'instructions' (top-level param) and
     * 'input' (the input items array) separated for the Responses API.
     *
     * @return array<string, mixed>
     */
    public function buildAll(TextRequest $request, MediaResolver $media): array
    {
        $instructions = $request->instructions;
        $input = [];

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

        return [
            'instructions' => $instructions,
            'input' => $input,
        ];
    }

    /**
     * Expand an assistant message into input items.
     *
     * When the assistant message has tool calls, each tool call becomes a
     * function_call input item for the Responses API replay format.
     *
     * @param  array<int, array<string, mixed>>  $input
     */
    private function expandAssistant(AssistantMessage $message, array &$input): void
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
