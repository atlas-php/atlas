<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Foundation\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Artisan command to scaffold a new Atlas agent class.
 *
 * Creates an agent definition in the configured agents directory
 * with system prompt and tools method stubs.
 */
#[AsCommand(name: 'make:agent')]
class MakeAgentCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:agent';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Atlas agent';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Agent';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__.'/../../../stubs/agent.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Agents';
    }

    /**
     * Get the console command options.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the agent already exists'],
        ];
    }
}
