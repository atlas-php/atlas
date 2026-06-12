<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Anthropic\Concerns;

/**
 * Shared header builder for Anthropic handlers.
 *
 * Anthropic uses x-api-key + anthropic-version instead of Bearer auth, so it
 * can't use the shared BuildsHeaders trait. Expects the using class to have a
 * $config property of type ProviderConfig.
 */
trait BuildsAnthropicHeaders
{
    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return $this->headersWithoutContentType() + ['Content-Type' => 'application/json'];
    }

    /**
     * Headers without Content-Type, for multipart and GET requests.
     *
     * @return array<string, string>
     */
    protected function headersWithoutContentType(): array
    {
        return [
            'x-api-key' => $this->config->apiKey,
            'anthropic-version' => $this->config->extra['version'] ?? '2023-06-01',
        ];
    }
}
