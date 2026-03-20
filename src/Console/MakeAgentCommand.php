<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Scaffolds a new Atlas agent class.
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
    protected $description = 'Create a new Atlas agent class';

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
        if ($this->option('tools') && $this->option('provider-tools')) {
            return $this->resolveStubPath('/stubs/agent.full.stub');
        }

        if ($this->option('tools')) {
            return $this->resolveStubPath('/stubs/agent.tools.stub');
        }

        if ($this->option('provider-tools')) {
            return $this->resolveStubPath('/stubs/agent.provider-tools.stub');
        }

        return $this->resolveStubPath('/stubs/agent.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     */
    protected function resolveStubPath(string $stub): string
    {
        return __DIR__.'/../../'.ltrim($stub, '/');
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
            ['tools', 't', InputOption::VALUE_NONE, 'Include tools() method stub'],
            ['provider-tools', 'p', InputOption::VALUE_NONE, 'Include providerTools() method stub'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the agent already exists'],
        ];
    }
}
