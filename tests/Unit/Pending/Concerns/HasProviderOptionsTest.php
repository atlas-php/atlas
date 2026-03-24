<?php

declare(strict_types=1);

use Atlasphp\Atlas\Pending\Concerns\HasProviderOptions;

it('sets provider options and returns static', function () {
    $builder = new class
    {
        use HasProviderOptions;
    };

    $result = $builder->withProviderOptions(['foo' => 'bar']);

    expect($result)->toBe($builder);
    expect((fn () => $this->providerOptions)->call($builder))->toBe(['foo' => 'bar']);
});

it('defaults to empty array', function () {
    $builder = new class
    {
        use HasProviderOptions;
    };

    expect((fn () => $this->providerOptions)->call($builder))->toBe([]);
});

it('replaces options on subsequent calls', function () {
    $builder = new class
    {
        use HasProviderOptions;
    };

    $builder->withProviderOptions(['first' => 1]);
    $builder->withProviderOptions(['second' => 2]);

    expect((fn () => $this->providerOptions)->call($builder))->toBe(['second' => 2]);
});
