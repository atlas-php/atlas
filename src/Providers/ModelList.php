<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers;

/**
 * A list of models available from a provider.
 */
class ModelList
{
    /**
     * @param  array<int, string>  $models
     */
    public function __construct(
        public readonly array $models = [],
    ) {}
}
