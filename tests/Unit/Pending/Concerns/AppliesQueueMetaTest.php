<?php

declare(strict_types=1);

use Atlasphp\Atlas\Pending\Concerns\AppliesQueueMeta;
use Atlasphp\Atlas\Pending\Concerns\HasMeta;

function createMetaBuilder(): object
{
    return new class
    {
        use AppliesQueueMeta;
        use HasMeta;

        /** Expose for testing. */
        public static function apply(object $request, array $payload, ?int $executionId): void
        {
            self::applyQueueMeta($request, $payload, $executionId);
        }
    };
}

it('injects execution_id into meta', function () {
    $builder = createMetaBuilder();
    $request = createMetaBuilder();

    $builder::apply($request, ['meta' => ['source' => 'test']], 42);

    expect((fn () => $this->meta)->call($request))->toBe([
        'source' => 'test',
        'execution_id' => 42,
    ]);
});

it('applies meta without execution_id when null', function () {
    $builder = createMetaBuilder();
    $request = createMetaBuilder();

    $builder::apply($request, ['meta' => ['key' => 'value']], null);

    expect((fn () => $this->meta)->call($request))->toBe(['key' => 'value']);
});

it('skips meta when payload has no meta and no execution_id', function () {
    $builder = createMetaBuilder();
    $request = createMetaBuilder();

    $builder::apply($request, [], null);

    expect((fn () => $this->meta)->call($request))->toBe([]);
});

it('applies only execution_id when payload meta is empty', function () {
    $builder = createMetaBuilder();
    $request = createMetaBuilder();

    $builder::apply($request, [], 99);

    expect((fn () => $this->meta)->call($request))->toBe(['execution_id' => 99]);
});
