<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\UserMessage;
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

    public function temperature(): ?float
    {
        return 0.7;
    }

    public function maxTokens(): ?int
    {
        return 4096;
    }

    public function maxSteps(): ?int
    {
        return 10;
    }

    public function concurrent(): bool
    {
        return false;
    }

    public function providerOptions(): array
    {
        return ['top_p' => 0.9];
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

// ─── temperature() ──────────────────────────────────────────────────────────

it('defaults temperature to null', function () {
    expect((new SupportAgent)->temperature())->toBeNull();
});

it('allows overriding temperature', function () {
    expect((new CustomizedAgent)->temperature())->toBe(0.7);
});

// ─── maxTokens() ────────────────────────────────────────────────────────────

it('defaults maxTokens to null', function () {
    expect((new SupportAgent)->maxTokens())->toBeNull();
});

it('allows overriding maxTokens', function () {
    expect((new CustomizedAgent)->maxTokens())->toBe(4096);
});

// ─── maxSteps() ─────────────────────────────────────────────────────────────

it('defaults maxSteps to null', function () {
    expect((new SupportAgent)->maxSteps())->toBeNull();
});

it('allows overriding maxSteps', function () {
    expect((new CustomizedAgent)->maxSteps())->toBe(10);
});

// ─── concurrent() ───────────────────────────────────────────────────────────

it('defaults concurrent to false', function () {
    expect((new SupportAgent)->concurrent())->toBeFalse();
});

it('allows overriding concurrent to false', function () {
    expect((new CustomizedAgent)->concurrent())->toBeFalse();
});

// ─── providerOptions() ─────────────────────────────────────────────────────

it('defaults providerOptions to empty array', function () {
    expect((new SupportAgent)->providerOptions())->toBe([]);
});

it('allows overriding providerOptions', function () {
    expect((new CustomizedAgent)->providerOptions())->toBe(['top_p' => 0.9]);
});

// ─── appendInstructionsForVoice() ──────────────────────────────────────────

// ─── appendInstructionsForVoice() ──────────────────────────────────────────

it('returns empty string when messages are empty', function () {
    $agent = new SupportAgent;

    expect($agent->appendInstructionsForVoice([]))->toBe('');
});

it('returns empty string when all messages have empty content', function () {
    $agent = new SupportAgent;

    $messages = [
        new SystemMessage('system only'),
        new AssistantMessage(''),
    ];

    expect($agent->appendInstructionsForVoice($messages))->toBe('');
});

it('formats user and assistant messages as history block', function () {
    $agent = new SupportAgent;

    $messages = [
        new UserMessage('Hello'),
        new AssistantMessage('Hi there!'),
    ];

    $result = $agent->appendInstructionsForVoice($messages);

    expect($result)->toStartWith('The user has switched to voice.')
        ->and($result)->toContain("User: Hello\nAssistant: Hi there!");
});

it('skips system messages and empty content', function () {
    $agent = new SupportAgent;

    $messages = [
        new SystemMessage('You are helpful'),
        new UserMessage('Hello'),
        new AssistantMessage(''),
        new AssistantMessage('Got it!'),
    ];

    $result = $agent->appendInstructionsForVoice($messages);

    expect($result)->toContain("User: Hello\nAssistant: Got it!")
        ->and($result)->not->toContain('System')
        ->and($result)->not->toContain('You are helpful');
});
