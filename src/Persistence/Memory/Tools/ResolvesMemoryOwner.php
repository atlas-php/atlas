<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Memory\Tools;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait ResolvesMemoryOwner
 *
 * Shared owner resolution for memory tools. Resolves the polymorphic
 * owner from tool context meta set by WireMemory middleware.
 */
trait ResolvesMemoryOwner
{
    /**
     * Resolve the memory owner from tool context.
     *
     * @param  array<string, mixed>  $context
     */
    protected function resolveOwner(array $context): ?Model
    {
        /** @var class-string<Model>|null $type */
        $type = $context['memory_owner_type'] ?? null;
        $id = $context['memory_owner_id'] ?? null;

        if ($type === null || $id === null) {
            return null;
        }

        if (! class_exists($type)) {
            return null;
        }

        return $type::find($id);
    }
}
