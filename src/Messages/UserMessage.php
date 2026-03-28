<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Messages;

use Atlasphp\Atlas\Enums\Role;

/**
 * A message from the user, optionally including media attachments.
 */
class UserMessage extends Message
{
    /**
     * @param  array<int, mixed>  $media
     */
    public function __construct(
        public readonly string $content,
        public readonly array $media = [],
    ) {}

    public function role(): Role
    {
        return Role::User;
    }
}
