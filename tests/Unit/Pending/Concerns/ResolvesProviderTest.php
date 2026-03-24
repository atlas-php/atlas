<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Pending\Concerns\HasProviderOptions;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;

function createResolvableBuilder(Provider|string $provider, ?string $model): object
{
    return new class($provider, $model, Mockery::mock(ProviderRegistryContract::class))
    {
        use HasProviderOptions;
        use ResolvesProvider;

        public function __construct(
            protected readonly Provider|string $provider,
            protected readonly ?string $model,
            protected readonly ProviderRegistryContract $registry,
        ) {}

        /** Expose for testing. */
        public function testResolveModelKey(): string
        {
            return $this->resolveModelKey();
        }

        /** Expose for testing. */
        public function testResolveProviderKey(): string
        {
            return $this->resolveProviderKey();
        }
    };
}

it('resolveModelKey returns model as string', function () {
    $builder = createResolvableBuilder('openai', 'gpt-4o');

    expect($builder->testResolveModelKey())->toBe('gpt-4o');
});

it('resolveModelKey returns empty string for null model', function () {
    $builder = createResolvableBuilder('openai', null);

    expect($builder->testResolveModelKey())->toBe('');
});

it('resolveProviderKey normalizes provider enum to string', function () {
    $builder = createResolvableBuilder(Provider::OpenAI, 'gpt-4o');

    expect($builder->testResolveProviderKey())->toBe('openai');
});

it('resolveProviderKey passes through string provider', function () {
    $builder = createResolvableBuilder('anthropic', 'claude-4');

    expect($builder->testResolveProviderKey())->toBe('anthropic');
});
