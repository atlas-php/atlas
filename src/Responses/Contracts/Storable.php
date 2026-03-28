<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses\Contracts;

/**
 * Marks a response type that produces storable binary content.
 */
interface Storable
{
    public function contents(): string;
}
