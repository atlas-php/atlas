<?php

declare(strict_types=1);

namespace App\Agents;

use App\Tools\MultiplyTool;
use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;

/**
 * Minimal persisted agent for reasoning recording audits.
 *
 * No fixed provider and no provider-native tools, so the audit can override the
 * provider/model per call and exercise reasoning + a client tool loop cleanly
 * across every provider.
 */
class ReasoningAuditAgent extends Agent
{
    use HasConversations;

    public function key(): string
    {
        return 'reasoning-audit';
    }

    public function instructions(): string
    {
        return 'You are a calculator assistant. Use the multiply tool for every multiplication, one call per product, then state the final result.';
    }

    /**
     * @return array<int, class-string>
     */
    public function tools(): array
    {
        return [MultiplyTool::class];
    }
}
