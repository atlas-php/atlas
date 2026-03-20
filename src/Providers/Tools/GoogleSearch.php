<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Tools;

/**
 * Google Search grounding provider tool for Gemini.
 */
class GoogleSearch extends ProviderTool
{
    public function type(): string
    {
        return 'google_search';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['google_search' => (object) []];
    }
}
