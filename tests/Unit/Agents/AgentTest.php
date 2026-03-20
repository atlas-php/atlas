<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Agent;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Providers\Tools\WebSearch;
use Atlasphp\Atlas\Tools\Tool;

// ─── Test agents ────────────────────────────────────────────────────────────

class SupportAgent extends Agent {}

class BillingAssistantAgent extends Agent {}

class CustomizedAgent extends Agent
{
    public function key(): string
    {
        return 'my-custom-key';
    }

    public function name(): string
    {
        return 'My Custom Agent';
    }

    public function description(): ?string
    {
        return 'Handles custom tasks.';
    }

    public function provider(): Provider|string|null
    {
        return Provider::OpenAI;
    }

    public function model(): ?string
    {
        return 'gpt-4o';
    }

    public function instructions(): ?string
    {
        return 'You are a helpful assistant for {company_name}.';
    }

    public function tools(): array
    {
        return [EchoAgentTool::class];
    }

    public function providerTools(): array
    {
        return [new WebSearch];
    }
}

class EchoAgentTool extends Tool
{
    public function name(): string
    {
        return 'echo';
    }

    public function description(): string
    {
        return 'Echoes input.';
    }

    public function handle(array $args, array $context): mixed
    {
        return $args['text'] ?? 'echo';
    }
}

// ─── key() ──────────────────────────────────────────────────────────────────

it('derives kebab-case key from class name, stripping Agent suffix', function () {
    expect((new SupportAgent)->key())->toBe('support');
});

it('derives multi-word kebab-case key', function () {
    expect((new BillingAssistantAgent)->key())->toBe('billing-assistant');
});

it('allows overriding key', function () {
    expect((new CustomizedAgent)->key())->toBe('my-custom-key');
});

// ─── name() ─────────────────────────────────────────────────────────────────

it('derives display name from class name, stripping Agent suffix', function () {
    expect((new SupportAgent)->name())->toBe('Support');
});

it('derives multi-word display name with spaces', function () {
    expect((new BillingAssistantAgent)->name())->toBe('Billing Assistant');
});

it('allows overriding name', function () {
    expect((new CustomizedAgent)->name())->toBe('My Custom Agent');
});

// ─── description() ──────────────────────────────────────────────────────────

it('defaults description to null', function () {
    expect((new SupportAgent)->description())->toBeNull();
});

it('allows overriding description', function () {
    expect((new CustomizedAgent)->description())->toBe('Handles custom tasks.');
});

// ─── provider() ─────────────────────────────────────────────────────────────

it('defaults provider to null', function () {
    expect((new SupportAgent)->provider())->toBeNull();
});

it('allows overriding provider with enum', function () {
    expect((new CustomizedAgent)->provider())->toBe(Provider::OpenAI);
});

// ─── model() ────────────────────────────────────────────────────────────────

it('defaults model to null', function () {
    expect((new SupportAgent)->model())->toBeNull();
});

it('allows overriding model', function () {
    expect((new CustomizedAgent)->model())->toBe('gpt-4o');
});

// ─── instructions() ─────────────────────────────────────────────────────────

it('defaults instructions to null', function () {
    expect((new SupportAgent)->instructions())->toBeNull();
});

it('allows overriding instructions with variable placeholders', function () {
    expect((new CustomizedAgent)->instructions())->toBe('You are a helpful assistant for {company_name}.');
});

// ─── tools() ────────────────────────────────────────────────────────────────

it('defaults tools to empty array', function () {
    expect((new SupportAgent)->tools())->toBe([]);
});

it('allows overriding tools with class strings', function () {
    $tools = (new CustomizedAgent)->tools();

    expect($tools)->toHaveCount(1);
    expect($tools[0])->toBe(EchoAgentTool::class);
});

// ─── providerTools() ────────────────────────────────────────────────────────

it('defaults providerTools to empty array', function () {
    expect((new SupportAgent)->providerTools())->toBe([]);
});

it('allows overriding providerTools', function () {
    $tools = (new CustomizedAgent)->providerTools();

    expect($tools)->toHaveCount(1);
    expect($tools[0])->toBeInstanceOf(WebSearch::class);
});
