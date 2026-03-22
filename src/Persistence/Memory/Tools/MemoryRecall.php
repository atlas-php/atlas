<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Memory\Tools;

use Atlasphp\Atlas\Persistence\Memory\MemoryContext;
use Atlasphp\Atlas\Persistence\Memory\MemoryModelService;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\Tool;

/**
 * Class MemoryRecall
 *
 * Agent tool for direct memory fetch by type and optional key.
 * Unlike search, this fetches by exact type — not semantic similarity.
 */
class MemoryRecall extends Tool
{
    public function __construct(
        protected readonly MemoryModelService $service,
        protected readonly MemoryContext $memoryContext,
    ) {}

    public function name(): string
    {
        return 'recall_memory';
    }

    public function description(): string
    {
        return 'Recall a specific memory by type and optional key. '
            .'Unlike search_memory, this fetches by exact type — not semantic similarity.';
    }

    public function parameters(): array
    {
        return [
            Schema::string('type', 'Memory type to recall'),
            Schema::string('key', 'Specific key within the type')->optional(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $context
     */
    public function handle(array $args, array $context): string
    {
        $memory = $this->service->recall(
            owner: $this->memoryContext->owner(),
            type: $args['type'],
            key: $args['key'] ?? null,
            agent: $this->memoryContext->agentKey(),
        );

        if ($memory === null) {
            return "No memory found for type '{$args['type']}'.";
        }

        return $memory->content;
    }
}
