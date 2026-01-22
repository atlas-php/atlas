<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\AgentDefinition;
use Atlasphp\Atlas\Agents\Enums\AgentType;

test('it generates key from class name', function () {
    $agent = new class extends AgentDefinition
    {
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
            return 'You are helpful.';
        }
    };

    // Anonymous class gets a generated name
    expect($agent->key())->toBeString();
});

test('it generates name from class name', function () {
    $agent = new class extends AgentDefinition
    {
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
            return 'You are helpful.';
        }
    };

    expect($agent->name())->toBeString();
});

test('it returns null description by default', function () {
    $agent = new class extends AgentDefinition
    {
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
            return 'Test';
        }
    };

    expect($agent->description())->toBeNull();
});

test('it returns empty tools by default', function () {
    $agent = new class extends AgentDefinition
    {
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
            return 'Test';
        }
    };

    expect($agent->tools())->toBe([]);
});

test('it returns empty provider tools by default', function () {
    $agent = new class extends AgentDefinition
    {
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
            return 'Test';
        }
    };

    expect($agent->providerTools())->toBe([]);
});

test('it returns null temperature by default', function () {
    $agent = new class extends AgentDefinition
    {
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
            return 'Test';
        }
    };

    expect($agent->temperature())->toBeNull();
});

test('it returns null maxTokens by default', function () {
    $agent = new class extends AgentDefinition
    {
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
            return 'Test';
        }
    };

    expect($agent->maxTokens())->toBeNull();
});

test('it returns null maxSteps by default', function () {
    $agent = new class extends AgentDefinition
    {
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
            return 'Test';
        }
    };

    expect($agent->maxSteps())->toBeNull();
});

test('it returns empty settings by default', function () {
    $agent = new class extends AgentDefinition
    {
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
            return 'Test';
        }
    };

    expect($agent->settings())->toBe([]);
});

test('it returns Api type by default', function () {
    $agent = new class extends AgentDefinition
    {
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
            return 'Test';
        }
    };

    expect($agent->type())->toBe(AgentType::Api);
});

test('it caches the key after first call', function () {
    $agent = new class extends AgentDefinition
    {
        public int $keyCallCount = 0;

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
            return 'Test';
        }

        public function key(): string
        {
            $this->keyCallCount++;

            return parent::key();
        }
    };

    $firstCall = $agent->key();
    $secondCall = $agent->key();
    $thirdCall = $agent->key();

    expect($firstCall)->toBe($secondCall);
    expect($secondCall)->toBe($thirdCall);
    // The counter increments each time key() is called on child,
    // but parent::key() should use the cache after first computation
    expect($agent->keyCallCount)->toBe(3);
});

test('it caches the name after first call', function () {
    $agent = new class extends AgentDefinition
    {
        public int $nameCallCount = 0;

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
            return 'Test';
        }

        public function name(): string
        {
            $this->nameCallCount++;

            return parent::name();
        }
    };

    $firstCall = $agent->name();
    $secondCall = $agent->name();
    $thirdCall = $agent->name();

    expect($firstCall)->toBe($secondCall);
    expect($secondCall)->toBe($thirdCall);
    // The counter increments each time name() is called on child,
    // but parent::name() should use the cache after first computation
    expect($agent->nameCallCount)->toBe(3);
});

test('key returns cached value on subsequent calls', function () {
    $agent = new class extends AgentDefinition
    {
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
            return 'Test';
        }
    };

    // Use reflection to verify caching behavior on parent class
    $reflection = new ReflectionClass(AgentDefinition::class);
    $cachedKeyProperty = $reflection->getProperty('cachedKey');
    $cachedKeyProperty->setAccessible(true);

    // Initially null
    expect($cachedKeyProperty->getValue($agent))->toBeNull();

    // First call computes and caches
    $key = $agent->key();
    expect($cachedKeyProperty->getValue($agent))->toBe($key);

    // Subsequent calls return cached value
    expect($agent->key())->toBe($key);
    expect($cachedKeyProperty->getValue($agent))->toBe($key);
});

test('name returns cached value on subsequent calls', function () {
    $agent = new class extends AgentDefinition
    {
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
            return 'Test';
        }
    };

    // Use reflection to verify caching behavior on parent class
    $reflection = new ReflectionClass(AgentDefinition::class);
    $cachedNameProperty = $reflection->getProperty('cachedName');
    $cachedNameProperty->setAccessible(true);

    // Initially null
    expect($cachedNameProperty->getValue($agent))->toBeNull();

    // First call computes and caches
    $name = $agent->name();
    expect($cachedNameProperty->getValue($agent))->toBe($name);

    // Subsequent calls return cached value
    expect($agent->name())->toBe($name);
    expect($cachedNameProperty->getValue($agent))->toBe($name);
});
