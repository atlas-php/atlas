<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Google;

use Atlasphp\Atlas\Enums\Role;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Providers\Contracts\MediaResolverContract;
use Atlasphp\Atlas\Providers\Contracts\MessageFactoryContract;
use Atlasphp\Atlas\Requests\TextRequest;

/**
 * Converts Atlas messages into Gemini's contents array format.
 *
 * Gemini uses parts arrays with typed part objects and separates system
 * instructions from conversation contents.
 */
class MessageFactory implements MessageFactoryContract
{
    /**
     * @return array<string, mixed>
     */
    public function system(SystemMessage $message): array
    {
        return [
            'parts' => [
                ['text' => $message->content],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function user(UserMessage $message, MediaResolverContract $media): array
    {
        $parts = [];

        if ($message->content !== '') {
            $parts[] = ['text' => $message->content];
        }

        foreach ($message->media as $input) {
            $parts[] = $media->resolve($input);
        }

        return [
            'role' => 'user',
            'parts' => $parts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function assistant(AssistantMessage $message): array
    {
        $parts = [];

        if ($message->content !== null && $message->content !== '') {
            $parts[] = ['text' => $message->content];
        }

        foreach ($message->toolCalls as $toolCall) {
            $parts[] = [
                'functionCall' => [
                    'name' => $toolCall->name,
                    'args' => $toolCall->arguments ?: (object) [],
                ],
            ];
        }

        return [
            'role' => 'model',
            'parts' => $parts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toolResult(ToolResultMessage $message): array
    {
        $response = json_decode($message->content, true);

        return [
            'role' => 'user',
            'parts' => [
                [
                    'functionResponse' => [
                        'name' => $message->toolName ?? '',
                        'response' => $response ?? ['result' => $message->content],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build the full request structure.
     *
     * @return array{system_instruction: array<string, mixed>|null, contents: array<int, array<string, mixed>>}
     */
    public function buildAll(TextRequest $request, MediaResolverContract $media): array
    {
        $systemInstruction = null;
        $contents = [];

        if ($request->instructions !== null && $request->instructions !== '') {
            $systemInstruction = [
                'parts' => [
                    ['text' => $request->instructions],
                ],
            ];
        }

        foreach ($request->messages as $message) {
            if ($message->role() === Role::System) {
                if ($systemInstruction === null) {
                    $systemInstruction = ['parts' => []];
                }
                $systemInstruction['parts'][] = ['text' => $message->content];

                continue;
            }

            $entry = match ($message->role()) {
                Role::User => $this->user($message, $media),
                Role::Assistant => $this->assistant($message),
                Role::Tool => $this->toolResult($message),
                default => null,
            };

            if ($entry !== null) {
                $contents[] = $entry;
            }
        }

        if ($request->message !== null) {
            $userMessage = new UserMessage($request->message, $request->messageMedia);
            $contents[] = $this->user($userMessage, $media);
        }

        return [
            'system_instruction' => $systemInstruction,
            'contents' => $contents,
        ];
    }
}
