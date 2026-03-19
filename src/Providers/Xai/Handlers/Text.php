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

        return $body;
    }
}
