<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi;

use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Providers\Concerns\BuildsResponsesMessages;
use Atlasphp\Atlas\Providers\Contracts\MediaResolver;
use Atlasphp\Atlas\Providers\Contracts\MessageFactory as MessageFactoryContract;
use Atlasphp\Atlas\Requests\TextRequest;

/**
 * Converts typed Atlas messages into OpenAI Responses API input format.
 *
 * Instructions are a top-level parameter, system messages use the 'developer' role,
 * and tool results are function_call_output items.
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
            'role' => 'developer',
            'content' => $message->content,
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

        $this->collectInputItems($request, $media, $instructions, $input);

        return [
            'instructions' => $instructions,
            'input' => $input,
        ];
    }
}
