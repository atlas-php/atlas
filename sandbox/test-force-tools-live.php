<?php

declare(strict_types=1);

/**
 * Forced tool choice — live API test across providers.
 *
 * Verifies the provider-normalized tool choice end-to-end through Atlas:
 * `->forceTools()` (tool_choice = required) makes the model call a tool even on a
 * prompt it would normally answer in plain text, and the executor then relaxes the
 * choice so the model still produces a final reply. Also checks a specific named
 * tool is forced to open the turn.
 *
 * Usage: php test-force-tools-live.php
 * Requires OPENAI_API_KEY / ANTHROPIC_API_KEY / GEMINI_API_KEY / XAI_API_KEY in sandbox/.env
 */
$app = require __DIR__.'/bootstrap.php';

$app['config']->set('atlas.providers', [
    'openai' => ['api_key' => env('OPENAI_API_KEY'), 'url' => env('OPENAI_URL', 'https://api.openai.com/v1')],
    'anthropic' => ['api_key' => env('ANTHROPIC_API_KEY'), 'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1')],
    'google' => ['api_key' => env('GEMINI_API_KEY', env('GOOGLE_API_KEY')), 'url' => env('GOOGLE_URL', 'https://generativelanguage.googleapis.com')],
    'xai' => ['api_key' => env('XAI_API_KEY'), 'url' => env('XAI_URL', 'https://api.x.ai/v1')],
]);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Schema\Fields\StringField;
use Atlasphp\Atlas\Tools\Tool;
use Atlasphp\Atlas\Tools\ToolChoice;

/** Records the order tools were called, so we can prove WHICH tool a choice forced. */
class ToolCallLog
{
    /** @var array<int, string> */
    public static array $order = [];

    public static function reset(): void
    {
        self::$order = [];
    }
}

/** A probe tool that records each invocation, so we can prove the model was forced to call it. */
class LogMoodTool extends Tool
{
    public function name(): string
    {
        return 'log_mood';
    }

    public function description(): string
    {
        return 'Record the conversation mood. Returns a confirmation.';
    }

    /** A real parameter so the function schema is valid under OpenAI strict mode. */
    public function parameters(): array
    {
        return [new StringField('mood', 'One word describing the current mood.')];
    }

    public function handle(array $args, array $context): mixed
    {
        ToolCallLog::$order[] = 'log_mood';

        return 'mood logged';
    }
}

/** A second, unrelated tool — the choice must NOT pick this when a specific tool is forced. */
class GetTimeTool extends Tool
{
    public function name(): string
    {
        return 'get_time';
    }

    public function description(): string
    {
        return 'Get the current time for a timezone.';
    }

    public function parameters(): array
    {
        return [new StringField('zone', 'The IANA timezone, e.g. UTC.')];
    }

    public function handle(array $args, array $context): mixed
    {
        ToolCallLog::$order[] = 'get_time';

        return '12:00 UTC';
    }
}

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo '  '.($ok ? '✓' : '✗')."  {$label}".($detail !== '' ? "  — {$detail}" : '')."\n";
}

/** forceTools(): the model must call the only tool even though the prompt invites a plain reply. */
function runForce(Provider $provider, string $model): void
{
    $label = $provider->value;
    try {
        ToolCallLog::reset();

        $r = Atlas::text($provider, $model)
            ->instructions('Reply in plain conversational text.')
            ->message('Just say hello back to me.')
            ->withTools([new LogMoodTool])
            ->forceTools()
            ->asText();

        check("{$label}: forced tool was called on a trivial prompt", ToolCallLog::$order !== [], 'order='.implode(',', ToolCallLog::$order));
        check("{$label}: model still produced a final reply (first-step relaxation)", $r->text !== '', mb_substr($r->text, 0, 60));
    } catch (Throwable $e) {
        check("{$label}: forceTools live call", false, get_class($e).': '.$e->getMessage());
    }
}

/**
 * toolChoice(specific tool): with TWO tools available, the named one must be the
 * tool that opens the turn — proving the choice targets a specific tool, not just
 * "some tool". A trivial prompt that maps to neither tool removes any natural pull.
 */
function runForceSpecific(Provider $provider, string $model): void
{
    $label = $provider->value;
    try {
        ToolCallLog::reset();

        $r = Atlas::text($provider, $model)
            ->instructions('Reply in plain conversational text.')
            ->message('How are you today?')
            ->withTools([new GetTimeTool, new LogMoodTool])
            ->toolChoice(ToolChoice::tool('log_mood'))
            ->asText();

        check("{$label}: the FORCED tool (log_mood) opened the turn, not the other", (ToolCallLog::$order[0] ?? null) === 'log_mood', 'order='.implode(',', ToolCallLog::$order));
        check("{$label}: model still produced a final reply", $r->text !== '', mb_substr($r->text, 0, 60));
    } catch (Throwable $e) {
        check("{$label}: specific-tool live call", false, get_class($e).': '.$e->getMessage());
    }
}

echo "── forceTools (tool_choice = required) across providers ──\n";
runForce(Provider::OpenAI, 'gpt-4o-mini');
runForce(Provider::Anthropic, 'claude-sonnet-4-5');
runForce(Provider::Google, 'gemini-2.5-flash');
runForce(Provider::xAI, 'grok-4.3');

echo "\n── toolChoice(specific tool) — forces a NAMED tool over another, across providers ──\n";
runForceSpecific(Provider::OpenAI, 'gpt-4o-mini');
runForceSpecific(Provider::Anthropic, 'claude-sonnet-4-5');
runForceSpecific(Provider::Google, 'gemini-2.5-flash');
runForceSpecific(Provider::xAI, 'grok-4.3');

echo "\n".str_repeat('─', 50)."\n";
echo "  Passed: {$pass}   Failed: {$fail}\n";

exit($fail === 0 ? 0 : 1);
