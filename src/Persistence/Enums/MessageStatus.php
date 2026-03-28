<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Enums;

/**
 * Delivery status for conversation messages.
 *
 * Delivered messages are visible to the agent. Queued messages are
 * stored but invisible until the current execution completes.
 */
enum MessageStatus: string
{
    case Delivered = 'delivered';
    case Queued = 'queued';
    case Failed = 'failed';
}
