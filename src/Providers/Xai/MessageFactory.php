<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Xai;

use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Providers\Contracts\MediaResolver;
use Atlasphp\Atlas\Providers\OpenAi\MessageFactory as OpenAiMessageFactory;
use Atlasphp\Atlas\Requests\TextRequest;

/**
 * Converts typed Atlas messages into xAI Responses API input format.
 *
 * xAI does not support the `instructions` top-level parameter, so instructions
 * are injected as a system role message in the input array. Uses `system` role
 * instead of OpenAI's `developer` role.
 */
class MessageFactory extends OpenAiMessageFactory
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
     * Build the full input array with instructions as a system message in input.
     *
     * Returns instructions as null since xAI does not support top-level instructions.
     * Instead, instructions are prepended as a system role message in the input array.
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

        if ($instructions !== null) {
            array_unshift($input, [
                'role' => 'system',
                'content' => $instructions,
            ]);
        }

        if ($request->message !== null) {
            $userMessage = new UserMessage($request->message, $request->messageMedia);
            $input[] = $this->user($userMessage, $media);
        }

        return [
            'instructions' => null,
            'input' => $input,
        ];
    }
}
