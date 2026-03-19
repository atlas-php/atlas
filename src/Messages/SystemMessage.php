<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Messages;

use Atlasphp\Atlas\Enums\Role;

/**
 * A system message providing instructions to the model.
 */
class SystemMessage extends Message
{
    public function __construct(
        public readonly string $content,
    ) {}

    public function role(): Role
    {
        return Role::System;
    }
}
