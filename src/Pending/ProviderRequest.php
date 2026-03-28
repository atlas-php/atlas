<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\ModelList;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Providers\VoiceList;

/**
 * Provider interrogation — delegates to the resolved driver for metadata queries.
 */
class ProviderRequest
{
    use ResolvesProvider;

    public function __construct(
        protected readonly Provider|string $provider,
        protected readonly ProviderRegistryContract $registry,
    ) {}

    public function models(): ModelList
    {
        return $this->resolveDriver()->models();
    }

    public function voices(): VoiceList
    {
        return $this->resolveDriver()->voices();
    }

    public function validate(): bool
    {
        return $this->resolveDriver()->validate();
    }

    public function capabilities(): ProviderCapabilities
    {
        return $this->resolveDriver()->capabilities();
    }

    public function name(): string
    {
        return $this->resolveDriver()->name();
    }

    /**
     * ProviderRequest has no model — always returns empty string.
     */
    protected function resolveModelKey(): string
    {
        return '';
    }
}
