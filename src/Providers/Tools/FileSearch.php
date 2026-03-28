<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Tools;

/**
 * File search provider tool configuration.
 */
class FileSearch extends ProviderTool
{
    /**
     * @param  array<int, string>  $stores
     */
    public function __construct(
        protected readonly array $stores = [],
        protected readonly ?int $maxResults = null,
    ) {}

    public function type(): string
    {
        return 'file_search';
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return array_filter([
            'vector_store_ids' => $this->stores ?: null,
            'max_num_results' => $this->maxResults,
        ]);
    }
}
