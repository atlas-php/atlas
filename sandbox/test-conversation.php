<?php

declare(strict_types=1);

use App\Models\User;
use Atlasphp\Atlas\Atlas;

$app = require __DIR__.'/bootstrap.php';

$user = User::findOrFail(1);
$conversationId = null;

$messages = [
    'Hi, my name is Tim. Remember that.',
    'What is my name?',
    'Generate an image of a dog playing fetch.',
    'What did I ask you to generate?',
];

foreach ($messages as $i => $msg) {
    echo '>>> '.($i + 1).": {$msg}\n";

    $request = Atlas::agent('assistant')
        ->for($user)
        ->message($msg);

    if ($conversationId) {
        $request->forConversation($conversationId);
    }

    $response = $request->asText();

    $conversationId = $response->meta['conversation_id'] ?? $conversationId;

    echo "<<< {$response->text}\n\n";
}

echo "--- Conversation {$conversationId} messages ---\n";

$msgs = ConversationMessage::where('conversation_id', $conversationId)
    ->where('is_active', true)
    ->orderBy('sequence')
    ->get();

foreach ($msgs as $m) {
    $content = mb_substr($m->content ?? '', 0, 100);
    echo "  [{$m->id}] {$m->role->value}: {$content}\n";
}
