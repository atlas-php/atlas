<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Google\Concerns;

/**
 * Shared header builder for Google (Gemini) handlers.
 *
 * Google uses x-goog-api-key authentication instead of Bearer tokens.
 */
trait BuildsGoogleHeaders
{
    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return [
            'x-goog-api-key' => $this->config->apiKey,
            'Content-Type' => 'application/json',
        ];
    }
}
