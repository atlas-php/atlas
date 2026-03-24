<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Memory\Tools;

use Atlasphp\Atlas\Schema\Schema;

/**
 * Class MemorySearch
 *
 * Agent tool for semantic memory search. Returns the most relevant
 * memories matching the query using vector similarity.
 */
class MemorySearch extends MemoryTool
{
    public function name(): string
    {
        return 'search_memory';
    }

    public function description(): string
    {
        return 'Search memory for relevant information. Returns the most '
            .'semantically relevant memories matching the query.';
    }

    public function parameters(): array
    {
        return [
            Schema::string('query', 'What to search for'),
            Schema::string('namespace', 'Filter by namespace')->optional(),
            Schema::string('type', 'Filter by type')->optional(),
            Schema::integer('limit', 'Max results (default 10)')->optional(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>|string
     */
    public function handle(array $args, array $context): array|string
    {
        if ($error = $this->guardContext()) {
            return $error;
        }

        $results = $this->service->search(
            owner: $this->memoryContext->owner(),
            query: $args['query'],
            type: $args['type'] ?? null,
            namespace: $args['namespace'] ?? null,
            agent: $this->memoryContext->agentKey(),
            limit: $args['limit'] ?? 10,
        );

        if ($results->isEmpty()) {
            return 'No relevant memories found.';
        }

        return $results->map(fn ($m) => [
            'content' => $m->content,
            'type' => $m->type,
            'namespace' => $m->namespace,
            'importance' => $m->importance,
            'created_at' => $m->created_at->toDateTimeString(),
        ])->values()->toArray();
    }
}
