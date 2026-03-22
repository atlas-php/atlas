<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending\Concerns;

/**
 * Adds meta context support to Pending request builders.
 *
 * Meta set here is copied to the actual request object when buildRequest() is called,
 * then passed to ProviderContext by Driver::dispatch() for middleware access.
 */
trait HasMeta
{
    /** @var array<string, mixed> */
    protected array $meta = [];

    /**
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): static
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    /**
     * Get metadata for the execution record when queuing.
     *
     * @return array<string, mixed>
     */
    protected function getQueueMeta(): array
    {
        return $this->meta;
    }
}
