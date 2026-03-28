<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Xai;

use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Providers\Concerns\BuildsResponsesMessages;
use Atlasphp\Atlas\Providers\Contracts\MediaResolverContract;
use Atlasphp\Atlas\Providers\Contracts\MessageFactoryContract;
use Atlasphp\Atlas\Requests\TextRequest;

/**
 * Converts typed Atlas messages into xAI Responses API input format.
 *
 * xAI does not support the `instructions` top-level parameter, so instructions
 * are injected as a system role message in the input array. Uses `system` role
 * instead of OpenAI's `developer` role.
 */
class MessageFactory implements MessageFactoryContract
{
    use BuildsResponsesMessages;

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
    public function buildAll(TextRequest $request, MediaResolverContract $media): array
    {
        $instructions = $request->instructions;
        $input = [];

        $this->collectInputItems($request, $media, $instructions, $input);

        if ($instructions !== null) {
            array_unshift($input, [
                'role' => 'system',
                'content' => $instructions,
            ]);
        }

        return [
            'instructions' => null,
            'input' => $input,
        ];
    }
}
