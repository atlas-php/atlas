<?php

declare(strict_types=1);

namespace App\Tools;

use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\Tool;

/**
 * Org-chart lookup. Each record only reveals the NEXT manager's id, so the model
 * cannot know the chain in advance — it must look up one person, read the
 * manager_id from the result, then look that person up, and so on. This forces a
 * genuine multi-step tool loop (one step per lookup).
 */
class LookupEmployeeTool extends Tool
{
    /** @var array<string, array{name: string, title: string, manager_id: string|null}> */
    private const DIRECTORY = [
        'E001' => ['name' => 'Alice Reyes', 'title' => 'Engineer', 'manager_id' => 'E002'],
        'E002' => ['name' => 'Bob Tran', 'title' => 'Eng Manager', 'manager_id' => 'E003'],
        'E003' => ['name' => 'Carol Singh', 'title' => 'Director', 'manager_id' => 'E004'],
        'E004' => ['name' => 'Dave Okoro', 'title' => 'VP Engineering', 'manager_id' => 'E005'],
        'E005' => ['name' => 'Eve Lindqvist', 'title' => 'CEO', 'manager_id' => null],
    ];

    public function name(): string
    {
        return 'lookup_employee';
    }

    public function description(): string
    {
        return 'Look up an employee by id. Returns their name, title, and their manager_id (or null if they have no manager).';
    }

    /**
     * @return array<int, mixed>
     */
    public function parameters(): array
    {
        return [Schema::string('id', 'The employee id, e.g. E001')];
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $context
     */
    public function handle(array $args, array $context): mixed
    {
        $id = strtoupper(trim((string) ($args['id'] ?? '')));
        $record = self::DIRECTORY[$id] ?? null;

        if ($record === null) {
            return json_encode(['error' => "No employee with id {$id}"]);
        }

        return json_encode(['id' => $id] + $record);
    }
}
