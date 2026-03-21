<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi;

/**
 * Adds the OpenAI-Organization header when configured.
 *
 * Overrides extraHeaders() from BuildsHeaders trait.
 */
trait HasOrganizationHeader
{
    /**
     * @return array<string, string>
     */
    protected function extraHeaders(): array
    {
        if ($this->config->organization !== null) {
            return ['OpenAI-Organization' => $this->config->organization];
        }

        return [];
    }
}
