<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Services\SystemPromptBuilder;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Pipelines\PipelineRegistry;
use Atlasphp\Atlas\Pipelines\PipelineRunner;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Illuminate\Container\Container;

beforeEach(function () {
    $container = new Container;
    $registry = new PipelineRegistry;
    $this->runner = new PipelineRunner($registry, $container);
    $this->builder = new SystemPromptBuilder($this->runner);
});

test('it builds system prompt from agent', function () {
    $agent = new TestAgent;
    $context = new AgentContext;

    $prompt = $this->builder->build($agent, $context);

    // TestAgent has: 'You are {agent_name}. Help {user_name} with their request.'
    // Without variables, placeholders remain
    expect($prompt)->toContain('{agent_name}');
    expect($prompt)->toContain('{user_name}');
});

test('it interpolates variables', function () {
    $agent = new TestAgent;
    $context = new AgentContext(variables: [
        'agent_name' => 'Atlas',
        'user_name' => 'John',
    ]);

    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toContain('You are Atlas');
    expect($prompt)->toContain('Help John');
});

test('it uses global variables', function () {
    $this->builder->registerVariable('agent_name', 'GlobalAgent');

    $agent = new TestAgent;
    $context = new AgentContext;

    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toContain('You are GlobalAgent');
});

test('context variables override global variables', function () {
    $this->builder->registerVariable('agent_name', 'GlobalAgent');

    $agent = new TestAgent;
    $context = new AgentContext(variables: ['agent_name' => 'ContextAgent']);

    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toContain('You are ContextAgent');
});

test('it unregisters global variable', function () {
    $this->builder->registerVariable('agent_name', 'Test');
    $this->builder->unregisterVariable('agent_name');

    $agent = new TestAgent;
    $context = new AgentContext;

    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toContain('{agent_name}');
});

test('it adds sections to prompt', function () {
    $this->builder->addSection('rules', '## Rules\nFollow these rules.');

    $agent = new TestAgent;
    $context = new AgentContext;

    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toContain('## Rules');
    expect($prompt)->toContain('Follow these rules.');
});

test('it removes section', function () {
    $this->builder->addSection('rules', '## Rules');
    $this->builder->removeSection('rules');

    $agent = new TestAgent;
    $context = new AgentContext;

    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->not->toContain('## Rules');
});

test('it clears all sections', function () {
    $this->builder->addSection('rules', '## Rules');
    $this->builder->addSection('examples', '## Examples');
    $this->builder->clearSections();

    $agent = new TestAgent;
    $context = new AgentContext;

    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->not->toContain('## Rules');
    expect($prompt)->not->toContain('## Examples');
});

test('it handles boolean variables', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
        }

        public function provider(): string
        {
            return 'openai';
        }

        public function model(): string
        {
            return 'gpt-4';
        }

        public function systemPrompt(): string
        {
            return 'Debug mode: {debug}';
        }
    };

    $context = new AgentContext(variables: ['debug' => true]);
    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toContain('Debug mode: true');
});

test('it handles array variables as JSON', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
        }

        public function provider(): string
        {
            return 'openai';
        }

        public function model(): string
        {
            return 'gpt-4';
        }

        public function systemPrompt(): string
        {
            return 'Data: {data}';
        }
    };

    $context = new AgentContext(variables: ['data' => ['a' => 1]]);
    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toContain('{"a":1}');
});

// ============================================================================
// Additional Happy Path Tests
// ============================================================================

test('it handles integer variables', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
        }

        public function provider(): string
        {
            return 'openai';
        }

        public function model(): string
        {
            return 'gpt-4';
        }

        public function systemPrompt(): string
        {
            return 'Count: {count}';
        }
    };

    $context = new AgentContext(variables: ['count' => 42]);
    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toBe('Count: 42');
});

test('it handles float variables', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
        }

        public function provider(): string
        {
            return 'openai';
        }

        public function model(): string
        {
            return 'gpt-4';
        }

        public function systemPrompt(): string
        {
            return 'Temperature: {temp}';
        }
    };

    $context = new AgentContext(variables: ['temp' => 0.7]);
    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toBe('Temperature: 0.7');
});

test('it handles null variables as empty string', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
        }

        public function provider(): string
        {
            return 'openai';
        }

        public function model(): string
        {
            return 'gpt-4';
        }

        public function systemPrompt(): string
        {
            return 'Value: {value}';
        }
    };

    $context = new AgentContext(variables: ['value' => null]);
    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toBe('Value: ');
});

test('it handles boolean false as string false', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
        }

        public function provider(): string
        {
            return 'openai';
        }

        public function model(): string
        {
            return 'gpt-4';
        }

        public function systemPrompt(): string
        {
            return 'Active: {active}';
        }
    };

    $context = new AgentContext(variables: ['active' => false]);
    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toBe('Active: false');
});

test('it handles nested arrays as JSON', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
        }

        public function provider(): string
        {
            return 'openai';
        }

        public function model(): string
        {
            return 'gpt-4';
        }

        public function systemPrompt(): string
        {
            return 'Config: {config}';
        }
    };

    $context = new AgentContext(variables: [
        'config' => ['level1' => ['level2' => ['value' => 'deep']]],
    ]);
    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toContain('"level1"');
    expect($prompt)->toContain('"level2"');
    expect($prompt)->toContain('"deep"');
});

test('it concatenates multiple sections with newlines', function () {
    $this->builder->addSection('rules', '## Rules');
    $this->builder->addSection('examples', '## Examples');
    $this->builder->addSection('notes', '## Notes');

    $agent = new TestAgent;
    $context = new AgentContext;

    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toContain('## Rules');
    expect($prompt)->toContain('## Examples');
    expect($prompt)->toContain('## Notes');
    // Sections separated by double newlines
    expect($prompt)->toContain("\n\n");
});

test('it supports method chaining on registerVariable', function () {
    $result = $this->builder
        ->registerVariable('var1', 'value1')
        ->registerVariable('var2', 'value2');

    expect($result)->toBe($this->builder);
});

test('it supports method chaining on addSection', function () {
    $result = $this->builder
        ->addSection('sec1', 'content1')
        ->addSection('sec2', 'content2');

    expect($result)->toBe($this->builder);
});

test('it supports method chaining on removeSection', function () {
    $this->builder->addSection('sec1', 'content1');
    $result = $this->builder->removeSection('sec1');

    expect($result)->toBe($this->builder);
});

test('it supports method chaining on clearSections', function () {
    $result = $this->builder->clearSections();

    expect($result)->toBe($this->builder);
});

test('it supports method chaining on unregisterVariable', function () {
    $this->builder->registerVariable('var1', 'value1');
    $result = $this->builder->unregisterVariable('var1');

    expect($result)->toBe($this->builder);
});

test('it handles prompt with no placeholders', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
        }

        public function provider(): string
        {
            return 'openai';
        }

        public function model(): string
        {
            return 'gpt-4';
        }

        public function systemPrompt(): string
        {
            return 'You are a helpful assistant.';
        }
    };

    $context = new AgentContext(variables: ['unused' => 'value']);
    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toBe('You are a helpful assistant.');
});

test('it handles empty prompt', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
        }

        public function provider(): string
        {
            return 'openai';
        }

        public function model(): string
        {
            return 'gpt-4';
        }

        public function systemPrompt(): string
        {
            return '';
        }
    };

    $context = new AgentContext;
    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toBe('');
});

test('it handles multiple variables in single prompt', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
        }

        public function provider(): string
        {
            return 'openai';
        }

        public function model(): string
        {
            return 'gpt-4';
        }

        public function systemPrompt(): string
        {
            return 'Hello {name}, your role is {role} at {company}.';
        }
    };

    $context = new AgentContext(variables: [
        'name' => 'Alice',
        'role' => 'Engineer',
        'company' => 'Acme',
    ]);
    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toBe('Hello Alice, your role is Engineer at Acme.');
});

// ============================================================================
// Negative/Edge Case Tests
// ============================================================================

test('it leaves unmatched placeholders intact', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
        }

        public function provider(): string
        {
            return 'openai';
        }

        public function model(): string
        {
            return 'gpt-4';
        }

        public function systemPrompt(): string
        {
            return 'Hello {name}, your id is {user_id}';
        }
    };

    $context = new AgentContext(variables: ['name' => 'Bob']);
    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toBe('Hello Bob, your id is {user_id}');
});

test('it ignores invalid placeholder formats', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
        }

        public function provider(): string
        {
            return 'openai';
        }

        public function model(): string
        {
            return 'gpt-4';
        }

        public function systemPrompt(): string
        {
            return 'Test {123invalid} and {-dash} and { space } and {valid}';
        }
    };

    $context = new AgentContext(variables: ['valid' => 'works']);
    $prompt = $this->builder->build($agent, $context);

    // Invalid patterns should remain unchanged
    expect($prompt)->toContain('{123invalid}');
    expect($prompt)->toContain('{-dash}');
    expect($prompt)->toContain('{ space }');
    // Valid pattern should be replaced
    expect($prompt)->toContain('works');
});

test('it handles empty context variables', function () {
    $agent = new TestAgent;
    $context = new AgentContext(variables: []);

    $prompt = $this->builder->build($agent, $context);

    // Placeholders should remain
    expect($prompt)->toContain('{agent_name}');
    expect($prompt)->toContain('{user_name}');
});

test('it removes nonexistent section gracefully', function () {
    // Should not throw
    $result = $this->builder->removeSection('nonexistent');

    expect($result)->toBe($this->builder);
});

test('it unregisters nonexistent variable gracefully', function () {
    // Should not throw
    $result = $this->builder->unregisterVariable('nonexistent');

    expect($result)->toBe($this->builder);
});

test('it handles empty string variable', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
        }

        public function provider(): string
        {
            return 'openai';
        }

        public function model(): string
        {
            return 'gpt-4';
        }

        public function systemPrompt(): string
        {
            return 'Name: {name}';
        }
    };

    $context = new AgentContext(variables: ['name' => '']);
    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toBe('Name: ');
});

test('it handles camelCase variable names', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
        }

        public function provider(): string
        {
            return 'openai';
        }

        public function model(): string
        {
            return 'gpt-4';
        }

        public function systemPrompt(): string
        {
            return 'User: {userName}, Account: {accountType}';
        }
    };

    $context = new AgentContext(variables: [
        'userName' => 'John',
        'accountType' => 'premium',
    ]);
    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toBe('User: John, Account: premium');
});

test('it handles underscore prefixed variable names', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
        }

        public function provider(): string
        {
            return 'openai';
        }

        public function model(): string
        {
            return 'gpt-4';
        }

        public function systemPrompt(): string
        {
            return 'Internal: {_internal}';
        }
    };

    $context = new AgentContext(variables: ['_internal' => 'secret']);
    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toBe('Internal: secret');
});

test('it handles same variable used multiple times', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
        }

        public function provider(): string
        {
            return 'openai';
        }

        public function model(): string
        {
            return 'gpt-4';
        }

        public function systemPrompt(): string
        {
            return 'Hello {name}! How are you, {name}? Goodbye {name}.';
        }
    };

    $context = new AgentContext(variables: ['name' => 'Alice']);
    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toBe('Hello Alice! How are you, Alice? Goodbye Alice.');
});

test('it replaces section with same key', function () {
    $this->builder->addSection('rules', 'Old rules');
    $this->builder->addSection('rules', 'New rules');

    $agent = new TestAgent;
    $context = new AgentContext;

    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->not->toContain('Old rules');
    expect($prompt)->toContain('New rules');
});

test('it handles special characters in variable values', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
        }

        public function provider(): string
        {
            return 'openai';
        }

        public function model(): string
        {
            return 'gpt-4';
        }

        public function systemPrompt(): string
        {
            return 'Content: {content}';
        }
    };

    $context = new AgentContext(variables: [
        'content' => 'Test with $pecial ch@rs & <html> "quotes"',
    ]);
    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toBe('Content: Test with $pecial ch@rs & <html> "quotes"');
});

test('it runs before_build pipeline', function () {
    $container = new Container;
    $registry = new PipelineRegistry;

    // Register a handler that modifies variables
    $registry->define('agent.system_prompt.before_build');

    $handler = new class implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function handle(mixed $data, \Closure $next): mixed
        {
            $data['variables']['injected'] = 'pipeline_value';

            return $next($data);
        }
    };

    $registry->register('agent.system_prompt.before_build', $handler);

    $runner = new PipelineRunner($registry, $container);
    $builder = new SystemPromptBuilder($runner);

    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
        }

        public function provider(): string
        {
            return 'openai';
        }

        public function model(): string
        {
            return 'gpt-4';
        }

        public function systemPrompt(): string
        {
            return 'Injected: {injected}';
        }
    };

    $context = new AgentContext;
    $prompt = $builder->build($agent, $context);

    expect($prompt)->toBe('Injected: pipeline_value');
});

// ============================================================================
// Null System Prompt Tests
// ============================================================================

test('it returns null when agent has no system prompt and no sections', function () {
    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
        }

        public function systemPrompt(): ?string
        {
            return null;
        }
    };

    $context = new AgentContext;
    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toBeNull();
});

test('it builds sections only when agent has null system prompt but sections exist', function () {
    $this->builder->addSection('rules', '## Rules\nFollow these rules.');

    $agent = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'test';
        }

        public function systemPrompt(): ?string
        {
            return null;
        }
    };

    $context = new AgentContext;
    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toBe('## Rules\nFollow these rules.');
});

test('it handles agent with null system prompt using fixture', function () {
    $agent = new \Atlasphp\Atlas\Tests\Fixtures\TestAgentNoSystemPrompt;
    $context = new AgentContext;

    $prompt = $this->builder->build($agent, $context);

    expect($prompt)->toBeNull();
});

test('it runs after_build pipeline', function () {
    $container = new Container;
    $registry = new PipelineRegistry;

    // Register a handler that modifies the final prompt
    $registry->define('agent.system_prompt.after_build');

    $handler = new class implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function handle(mixed $data, \Closure $next): mixed
        {
            $data['prompt'] .= "\n\n[Modified by pipeline]";

            return $next($data);
        }
    };

    $registry->register('agent.system_prompt.after_build', $handler);

    $runner = new PipelineRunner($registry, $container);
    $builder = new SystemPromptBuilder($runner);

    $agent = new TestAgent;
    $context = new AgentContext;

    $prompt = $builder->build($agent, $context);

    expect($prompt)->toContain('[Modified by pipeline]');
});
