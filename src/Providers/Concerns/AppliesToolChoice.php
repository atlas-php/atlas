<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Concerns;

use Atlasphp\Atlas\Providers\Contracts\ToolMapperContract;
use Atlasphp\Atlas\Requests\TextRequest;

/**
 * Merges a request's normalized tool choice into a provider request body.
 *
 * Shared by the text handlers so the guard lives in one place: a choice is only
 * applied when one is set AND the request is not a structured-output call (which
 * forces its own `tool_choice` for the schema tool). Each handler passes its own
 * mapper since the property name differs per provider.
 */
trait AppliesToolChoice
{
    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function applyToolChoice(array $body, TextRequest $request, ToolMapperContract $mapper): array
    {
        if ($request->toolChoice === null || $request->schema !== null) {
            return $body;
        }

        return array_merge($body, $mapper->mapToolChoice($request->toolChoice));
    }
}
