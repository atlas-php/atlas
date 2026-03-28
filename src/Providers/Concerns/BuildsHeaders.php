<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Concerns;

/**
 * Shared header builder for provider handlers.
 *
 * Expects the using class to have a $config property of type ProviderConfig.
 * Override extraHeaders() to add provider-specific headers.
 */
trait BuildsHeaders
{
    /**
     * Build standard API headers with Bearer auth.
     *
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return array_merge([
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json',
        ], $this->extraHeaders());
    }

    /**
     * Build headers without Content-Type for multipart requests.
     *
     * @return array<string, string>
     */
    protected function headersWithoutContentType(): array
    {
        return array_merge([
            'Authorization' => "Bearer {$this->config->apiKey}",
        ], $this->extraHeaders());
    }

    /**
     * Provider-specific headers. Override to add vendor headers.
     *
     * @return array<string, string>
     */
    protected function extraHeaders(): array
    {
        return [];
    }
}
