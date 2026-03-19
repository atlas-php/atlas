<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Tools;

/**
 * Web search provider tool configuration.
 */
class WebSearch extends ProviderTool
{
    public function __construct(
        protected readonly ?int $maxResults = null,
        protected readonly ?string $locale = null,
    ) {}

    public function type(): string
    {
        return 'web_search';
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return array_filter([
            'max_results' => $this->maxResults,
            'locale' => $this->locale,
        ]);
    }
}
