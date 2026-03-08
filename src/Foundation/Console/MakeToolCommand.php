<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Foundation\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Artisan command to scaffold a new Atlas tool class.
 *
 * Creates a tool definition in the configured tools directory
 * with name, description, parameters, and handle method stubs.
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
    protected $description = 'Create a new Atlas tool';

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
        return __DIR__.'/../../../stubs/tool.stub';
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
     * Build the class with the given name.
     *
     * Replaces the {{ tool_name }} placeholder with a snake_case version
     * of the class name (with "Tool" suffix stripped).
     *
     * @param  string  $name
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $className = class_basename($name);
        $stripped = preg_replace('/Tool$/', '', $className) ?? $className;
        $baseName = $stripped !== '' ? $stripped : $className;
        $toolName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $baseName) ?? $baseName);

        return str_replace('{{ tool_name }}', $toolName, $stub);
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
