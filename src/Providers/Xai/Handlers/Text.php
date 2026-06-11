<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Xai\Handlers;

use Atlasphp\Atlas\Providers\OpenAi\Handlers\Text as OpenAiText;
use Atlasphp\Atlas\Requests\TextRequest;

/**
 * xAI text handler extending OpenAI's Responses API handler.
 *
 * Strips the `instructions` key from the payload since xAI does not support it
 * as a top-level parameter (instructions are handled via MessageFactory as a
 * system message in input). Ensures `store` is always false.
 */
class Text extends OpenAiText
{
    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(TextRequest $request): array
    {
        $body = parent::buildPayload($request);

        unset($body['instructions']);

        $body['store'] = false;

        // xAI's grok reasoning models accept only low|high effort (no
        // minimal|medium); collapse the normalized effort to the nearest.
        if (isset($body['reasoning']['effort'])) {
            $body['reasoning']['effort'] = in_array($body['reasoning']['effort'], ['minimal', 'low'], true)
                ? 'low'
                : 'high';
        }

        // Encrypted-reasoning replay is an OpenAI Responses feature; drop the
        // include so xAI doesn't reject an unsupported parameter.
        if (isset($body['include'])) {
            $body['include'] = array_values(array_filter(
                $body['include'],
                fn ($value) => $value !== 'reasoning.encrypted_content',
            ));

            if ($body['include'] === []) {
                unset($body['include']);
            }
        }

        return $body;
    }
}
