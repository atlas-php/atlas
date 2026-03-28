<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

/**
 * Value object representing a recorded request made through a FakeDriver.
 */
class RecordedRequest
{
    public function __construct(
        public readonly string $method,
        public readonly string $provider,
        public readonly string $model,
        public readonly mixed $request,
    ) {}
}
