<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Scaffolds a new Atlas tool class.
 */
#[AsCommand(name: 'make:tool')]
class MakeToolCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:tool';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Atlas tool class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Tool';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/tool.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     */
    protected function resolveStubPath(string $stub): string
    {
        return __DIR__.'/../../'.ltrim($stub, '/');
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $className = class_basename($name);
        $toolName = $this->deriveToolName($className);

        return str_replace('{{ toolName }}', $toolName, $stub);
    }

    /**
     * Derive snake_case tool name from class name.
     *
     * LookupOrderTool → lookup_order
     * SearchKnowledgeBaseTool → search_knowledge_base
     * ProcessRefund → process_refund
     */
    protected function deriveToolName(string $className): string
    {
        // Strip "Tool" suffix
        $name = (string) preg_replace('/Tool$/', '', $className);

        // PascalCase → snake_case
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Tools';
    }

    /**
     * Get the console command options.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the tool already exists'],
        ];
    }
}
