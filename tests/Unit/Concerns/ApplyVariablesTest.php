<?php

declare(strict_types=1);

use Atlasphp\Atlas\Pending\Concerns\HasMeta;
use Atlasphp\Atlas\Pending\Concerns\HasVariables;

function createVariableBuilder(): object
{
    return new class
    {
        use HasMeta;
        use HasVariables;

        /** Expose for testing. */
        public static function apply(object $request, array $payload): void
        {
            self::applyVariables($request, $payload);
        }

        public function getVariables(): array
        {
            return $this->variables;
        }

        public function getInterpolateMessages(): bool
        {
            return $this->interpolateMessages;
        }
    };
}

it('restores variables from payload', function () {
    $builder = createVariableBuilder();
    $request = createVariableBuilder();

    $builder::apply($request, [
        'variables' => ['NAME' => 'Tim', 'ROLE' => 'admin'],
        'interpolate_messages' => false,
    ]);

    expect($request->getVariables())->toBe(['NAME' => 'Tim', 'ROLE' => 'admin']);
});

it('restores interpolate_messages flag from payload', function () {
    $builder = createVariableBuilder();
    $request = createVariableBuilder();

    $builder::apply($request, [
        'variables' => [],
        'interpolate_messages' => true,
    ]);

    expect($request->getInterpolateMessages())->toBeTrue();
});

it('skips variables when payload has empty variables', function () {
    $builder = createVariableBuilder();
    $request = createVariableBuilder();

    $builder::apply($request, [
        'variables' => [],
        'interpolate_messages' => false,
    ]);

    expect($request->getVariables())->toBe([]);
    expect($request->getInterpolateMessages())->toBeFalse();
});

it('skips variables when payload keys are missing', function () {
    $builder = createVariableBuilder();
    $request = createVariableBuilder();

    $builder::apply($request, []);

    expect($request->getVariables())->toBe([]);
    expect($request->getInterpolateMessages())->toBeFalse();
});

it('restores nested variables from payload', function () {
    $builder = createVariableBuilder();
    $request = createVariableBuilder();

    $builder::apply($request, [
        'variables' => ['COMPANY' => ['NAME' => 'Acme', 'URL' => 'https://acme.com']],
    ]);

    expect($request->getVariables())->toBe([
        'COMPANY' => ['NAME' => 'Acme', 'URL' => 'https://acme.com'],
    ]);
});
