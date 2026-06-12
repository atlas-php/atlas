<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Xai\Handlers;

use Atlasphp\Atlas\Providers\Responses\Handlers\Text as ResponsesText;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\TokenCount;

/**
 * xAI text handler built on the neutral Responses base.
 *
 * Strips the `instructions` key from the payload since xAI does not support it
 * as a top-level parameter (instructions are handled via MessageFactory as a
 * system message in input). Ensures `store` is always false.
 */
class Text extends ResponsesText
{
    /**
     * Estimate input tokens with the heuristic.
     *
     * xAI exposes only a plain-text `tokenize-text` endpoint — it cannot count a
     * full chat request (system, tools, images), so Atlas estimates instead of
     * inheriting OpenAI's native /responses/input_tokens call.
     */
    public function countTokens(TextRequest $request): TokenCount
    {
        return $this->estimateTokens($this->config->provider, $request->model, $this->buildPayload($request));
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(TextRequest $request): array
    {
        $body = parent::buildPayload($request);

        unset($body['instructions']);

        // Re-force store=false AFTER the base merges providerOptions, so a caller
        // can't enable server-side storage on xAI (which doesn't support it).
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
