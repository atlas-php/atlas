<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending\Concerns;

/**
 * Adds provider-specific option support to pending request builders.
 *
 * Provider options are passed through to the driver as-is, allowing consumers
 * to use provider-specific features without the builder needing to model them.
 */
trait HasProviderOptions
{
    /** @var array<string, mixed> */
    protected array $providerOptions = [];

    /**
     * @param  array<string, mixed>  $options
     */
    public function withProviderOptions(array $options): static
    {
        $this->providerOptions = $options;

        return $this;
    }
}
