<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Messages;

use Atlasphp\Atlas\Enums\Role;

/**
 * Abstract base class for typed conversation messages.
 */
abstract class Message
{
    abstract public function role(): Role;
}
