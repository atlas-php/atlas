<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Contracts;

use Atlasphp\Atlas\Input\Input;

/**
 * Converts Input types into a provider's media format.
 */
interface MediaResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(Input $input): array;
}
