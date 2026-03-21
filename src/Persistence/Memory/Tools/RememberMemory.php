<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Memory\Tools;

use Atlasphp\Atlas\Persistence\Memory\MemoryService;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\Tool;
use Illuminate\Support\Str;

/**
 * Class RememberMemory
 *
 * Agent tool for storing new memories. Use when the agent learns
 * something worth remembering across conversations.
 */
class RememberMemory extends Tool
{
    use ResolvesMemoryOwner;

    public function __construct(
        protected readonly MemoryService $service,
    ) {}

    public function name(): string
    {
        return 'remember_memory';
    }

    public function description(): string
    {
        return 'Store information for future reference. Use when you learn '
            .'something worth remembering across conversations.';
    }

    public function parameters(): array
    {
        return [
            Schema::string('content', 'The information to remember'),
            Schema::string('type', 'Memory type (consumer-defined)')->optional(),
            Schema::string('namespace', 'Category for grouping')->optional(),
            Schema::string('key', 'Unique key for upsert (omit for atomic)')->optional(),
            Schema::number('importance', '0.0-1.0, default 0.5')->optional(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $context
     */
    public function handle(array $args, array $context): string
    {
        $owner = $this->resolveOwner($context);

        $memory = $this->service->remember(
            owner: $owner,
            content: $args['content'],
            type: $args['type'] ?? 'atomic',
            namespace: $args['namespace'] ?? null,
            key: $args['key'] ?? null,
            importance: (float) ($args['importance'] ?? 0.5),
            source: 'tool:remember_memory',
            agent: $context['memory_agent'] ?? null,
        );

        return 'Remembered: '.Str::limit($memory->content, 100);
    }
}
