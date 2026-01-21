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
