<?php

declare(strict_types=1);

use Atlasphp\Atlas\Pending\Concerns\HasMeta;

function createHasMetaHelper(): object
{
    return new class
    {
        use HasMeta;

        /** Expose for testing. */
        public function getMeta(): array
        {
            return $this->meta;
        }
    };
}

it('injects execution_id into meta', function () {
    $builder = createHasMetaHelper();
    $request = createHasMetaHelper();

    $builder::applyMeta($request, ['meta' => ['source' => 'test']], 42);

    expect($request->getMeta())->toBe([
        'source' => 'test',
        'execution_id' => 42,
    ]);
});

it('applies meta without execution_id when null', function () {
    $builder = createHasMetaHelper();
    $request = createHasMetaHelper();

    $builder::applyMeta($request, ['meta' => ['key' => 'value']], null);

    expect($request->getMeta())->toBe(['key' => 'value']);
});

it('skips meta when payload has no meta and no execution_id', function () {
    $builder = createHasMetaHelper();
    $request = createHasMetaHelper();

    $builder::applyMeta($request, [], null);

    expect($request->getMeta())->toBe([]);
});

it('applies only execution_id when payload meta is empty', function () {
    $builder = createHasMetaHelper();
    $request = createHasMetaHelper();

    $builder::applyMeta($request, [], 99);

    expect($request->getMeta())->toBe(['execution_id' => 99]);
});
