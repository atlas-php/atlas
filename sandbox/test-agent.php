<?php

declare(strict_types=1);

use App\Models\User;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;

$app = require __DIR__.'/bootstrap.php';

$user = User::findOrFail(1);

$tool = $argv[1] ?? 'image';

$prompts = [
    'image' => 'Generate an image of a sunset over mountains.',
    'video' => 'Generate a 5 second video of ocean waves crashing.',
    'speech' => 'Convert this to speech: "Hello from Atlas, the AI execution layer."',
];

echo "=== Agent: {$tool} tool ===\n\n";

$response = Atlas::agent('assistant')
    ->for($user)
    ->withMeta(['user_email' => $user->email, 'source' => 'sandbox-test'])
    ->message($prompts[$tool])
    ->asText();

echo "Response:\n{$response->text}\n\n";
echo 'Conversation: '.($response->meta['conversation_id'] ?? 'null')."\n";
echo 'Execution: '.($response->meta['execution_id'] ?? 'null')."\n";
echo "Tokens: {$response->usage->inputTokens} in / {$response->usage->outputTokens} out\n";
echo 'Steps: '.count($response->steps).' | Tool calls: '.count($response->toolCalls)."\n";

echo "\n--- Conversations ---\n";
Conversation::all()->each(fn ($c) => print "  [{$c->id}] {$c->agent} | {$c->title}\n");

echo "\n--- Messages ---\n";
ConversationMessage::orderBy('sequence')->get()->each(function ($m) {
    $content = mb_substr($m->content ?? '', 0, 90);
    echo "  [{$m->id}] {$m->role->value} | parent={$m->parent_id} | step={$m->step_id} | {$content}\n";
});

echo "\n--- Assets ---\n";
Asset::all()->each(fn ($a) => print "  [{$a->id}] {$a->type->value} | {$a->mime_type} | ".number_format($a->size_bytes)."B | exec={$a->execution_id}\n");

echo "\n--- Executions ---\n";
Execution::all()->each(fn ($e) => print "  [{$e->id}] {$e->type->value} | {$e->status->label()} | {$e->provider}/{$e->model} | msg={$e->message_id} | asset={$e->asset_id} | {$e->duration_ms}ms\n");

echo "\n--- Steps ---\n";
ExecutionStep::orderBy('id')->get()->each(fn ($s) => print "  [{$s->id}] seq={$s->sequence} | {$s->finish_reason} | in={$s->input_tokens} out={$s->output_tokens}\n");

echo "\n--- Tool Calls ---\n";
ExecutionToolCall::orderBy('id')->get()->each(function ($tc) {
    $result = mb_substr($tc->result ?? '', 0, 70);
    echo "  [{$tc->id}] {$tc->name} | {$tc->status->label()} | step={$tc->step_id} | {$tc->duration_ms}ms | {$result}\n";
});

echo "\n--- Files ---\n";
$dir = storage_path('app/atlas/assets');

if (is_dir($dir)) {
    foreach (scandir($dir) as $f) {
        if ($f !== '.' && $f !== '..') {
            echo '  '.number_format(filesize($dir.'/'.$f)).'B  '.$f."\n";
        }
    }
}
