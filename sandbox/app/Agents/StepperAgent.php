<?php

declare(strict_types=1);

namespace App\Agents;

use App\Tools\LookupEmployeeTool;
use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;

/**
 * Demonstrates a genuine multi-step tool loop.
 *
 * Each org-chart lookup only reveals the next manager's id, so the model must
 * climb the chain one call at a time — one executor step per lookup.
 */
class StepperAgent extends Agent
{
    use HasConversations;

    public function key(): string
    {
        return 'stepper';
    }

    public function name(): string
    {
        return 'Stepper';
    }

    public function description(): ?string
    {
        return 'Climbs an org chart one lookup at a time — a real multi-step tool loop.';
    }

    public function provider(): Provider|string|null
    {
        return Provider::OpenAI;
    }

    public function model(): ?string
    {
        return 'gpt-5';
    }

    /**
     * @return array<int, class-string>
     */
    public function tools(): array
    {
        return [LookupEmployeeTool::class];
    }

    public function instructions(): string
    {
        return <<<'PROMPT'
        You trace reporting chains using the lookup_employee tool.

        You only know an employee's manager by looking them up. Never guess ids.
        Look up the starting employee, read their manager_id, look that manager up,
        and keep going up the chain until you reach someone whose manager_id is null.
        Then list the full chain from the starting employee up to the top.
        PROMPT;
    }
}
