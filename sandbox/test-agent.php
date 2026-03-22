<?php

declare(strict_types=1);

use App\Models\User;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;
use Atlasphp\Atlas\Persistence\Models\Message;

$app = require __DIR__.'/bootstrap.php';

$user = User::findOrFail(1);

echo "=== Agent: Single image generation ===\n\n";

$response = Atlas::agent('assistant')
    ->for($user)
    ->withMeta([
        'user_email' => $user->email,
        'user_name' => $user->name,
        'source' => 'sandbox-test',
        'ip' => '127.0.0.1',
    ])
    ->message('Generate an image of a cat sitting on a windowsill.')
    ->asText();

echo "Response:\n{$response->text}\n\n";
echo 'Conversation ID: '.($response->meta['conversation_id'] ?? 'null')."\n";
echo 'Execution ID: '.($response->meta['execution_id'] ?? 'null')."\n";
echo "Tokens: {$response->usage->inputTokens} in / {$response->usage->outputTokens} out\n";
echo 'Steps: '.count($response->steps)."\n";
echo 'Tool calls: '.count($response->toolCalls)."\n\n";

foreach ($response->toolCalls as $tc) {
    echo "  Tool: {$tc->name}\n";
}

echo "\n--- Conversations ---\n";
Conversation::all()->each(function ($c) {
    echo "  [{$c->id}] agent={$c->agent} | title={$c->title}\n";
});

echo "\n--- Messages ---\n";
Message::orderBy('sequence')->get()->each(function ($m) {
    $content = mb_substr($m->content ?? '', 0, 100);
    echo "  [{$m->id}] {$m->role->value} | {$m->status->value} | parent={$m->parent_id} | step={$m->step_id} | {$content}\n";
});

echo "\n--- Assets ---\n";
Asset::all()->each(function ($a) {
    echo "  [{$a->id}] {$a->type->value} | {$a->mime_type} | {$a->size_bytes}B | exec={$a->execution_id}\n";
});

echo "\n--- Executions ---\n";
Execution::all()->each(function ($e) {
    echo "  [{$e->id}] {$e->type->value} | {$e->status->label()} | {$e->provider}/{$e->model} | msg={$e->message_id} | asset={$e->asset_id} | {$e->duration_ms}ms | in={$e->total_input_tokens} out={$e->total_output_tokens}\n";
    if ($e->metadata) {
        echo '    metadata: '.json_encode($e->metadata)."\n";
    }
});

echo "\n--- Steps ---\n";
ExecutionStep::orderBy('id')->get()->each(function ($s) {
    $content = mb_substr($s->content ?? '(empty)', 0, 80);
    echo "  [{$s->id}] seq={$s->sequence} | {$s->status->label()} | in={$s->input_tokens} out={$s->output_tokens} | {$s->finish_reason} | {$content}\n";
});

echo "\n--- Tool Calls ---\n";
ExecutionToolCall::orderBy('id')->get()->each(function ($tc) {
    $result = mb_substr($tc->result ?? '', 0, 80);
    echo "  [{$tc->id}] {$tc->name} | {$tc->status->label()} | step={$tc->step_id} | {$tc->duration_ms}ms | {$result}\n";
});

echo "\n--- Files ---\n";
$dir = storage_path('app/atlas/assets');

if (is_dir($dir)) {
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }

        echo '  '.number_format(filesize($dir.'/'.$f)).'B  '.$f."\n";
    }
}
