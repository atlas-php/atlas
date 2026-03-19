<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi\Concerns;

/**
 * Shared header builder for OpenAI handlers.
 *
 * Expects the using class to have a $config property of type ProviderConfig.
 */
trait BuildsHeaders
{
    /**
     * Build standard OpenAI API headers.
     *
     * @return array<string, string>
     */
    protected function headers(): array
    {
        $headers = [
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json',
        ];

        if ($this->config->organization !== null) {
            $headers['OpenAI-Organization'] = $this->config->organization;
        }

        return $headers;
    }

    /**
     * Build headers without Content-Type for multipart requests.
     *
     * @return array<string, string>
     */
    protected function headersWithoutContentType(): array
    {
        $headers = [
            'Authorization' => "Bearer {$this->config->apiKey}",
        ];

        if ($this->config->organization !== null) {
            $headers['OpenAI-Organization'] = $this->config->organization;
        }

        return $headers;
    }
}
