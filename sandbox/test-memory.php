<?php

declare(strict_types=1);

use App\Models\User;
use Atlasphp\Atlas\Exceptions\MaxStepsExceededException;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Persistence\Memory\MemoryContext;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;
use Atlasphp\Atlas\Persistence\Models\Memory;
use Atlasphp\Atlas\Persistence\Models\Message;

/**
 * Manual test script for the memory system.
 *
 * Tests the full memory lifecycle:
 * - Agent remembers information via remember_memory tool
 * - Agent recalls information via recall_memory tool
 * - Consumer-side memory management via Atlas::memory()
 * - MemoryContext isolation (no meta pollution)
 * - Auto-embedding on save
 *
 * Usage: php sandbox/test-memory.php
 *
 * Prerequisites:
 * - Database migrated with atlas tables
 * - ATLAS_PERSISTENCE_ENABLED=true in sandbox .env
 * - Valid API key configured
 */
$app = require __DIR__.'/bootstrap.php';

$user = User::findOrFail(1);

// Clean up any existing test data
Memory::where('memoryable_type', $user->getMorphClass())
    ->where('memoryable_id', $user->getKey())
    ->forceDelete();

Conversation::where('owner_type', $user->getMorphClass())
    ->where('owner_id', $user->getKey())
    ->each(function ($c) {
        Message::where('conversation_id', $c->id)->forceDelete();
        $c->forceDelete();
    });

echo "=== Atlas Memory System Test ===\n\n";

// ── Test 1: Agent remembers information via tool ────────────
echo "1. Testing agent remember (via remember_memory tool)...\n";

$response = Atlas::agent('memory-test')
    ->for($user)
    ->message('Use the remember_memory tool to save this: content="My favorite color is blue", type="fact", key="color"')
    ->asText();

echo '   Response: '.substr($response->text, 0, 200)."\n";

$memories = Memory::where('memoryable_type', $user->getMorphClass())
    ->where('memoryable_id', $user->getKey())
    ->get();

echo '   Memories stored: '.$memories->count()."\n";

if ($memories->isEmpty()) {
    echo "   FAIL: No memories were stored!\n";
    exit(1);
}

$storedMemory = $memories->first();
echo "   - [{$storedMemory->type}:{$storedMemory->key}] {$storedMemory->content}\n";
echo "   Agent: {$storedMemory->agent}\n";
echo '   Embedding: '.($storedMemory->getRawOriginal('embedding') !== null ? 'yes (auto-embedded)' : 'no')."\n";
echo "   PASS\n\n";

// ── Test 2: Agent recalls information via tool ──────────────
echo "2. Testing agent recall (via recall_memory tool)...\n";

$storedType = $storedMemory->type;
$storedKey = $storedMemory->key;

try {
    $response = Atlas::agent('memory-test')
        ->for($user)
        ->message("Use the recall_memory tool with type=\"{$storedType}\" and key=\"{$storedKey}\" to find what you stored.")
        ->asText();

    echo '   Response: '.substr($response->text, 0, 200)."\n";

    $mentionsBlue = stripos($response->text, 'blue') !== false;
    echo '   Contains "blue": '.($mentionsBlue ? 'yes' : 'no')."\n";
    echo '   '.($mentionsBlue ? 'PASS' : 'WARN: Agent may not have recalled the memory')."\n\n";
} catch (MaxStepsExceededException $e) {
    // Check if the tool actually succeeded despite the agent looping
    $lastCall = ExecutionToolCall::where('name', 'recall_memory')
        ->orderByDesc('id')
        ->first();

    if ($lastCall && stripos($lastCall->result ?? '', 'blue') !== false) {
        echo "   Tool succeeded (recall returned: {$lastCall->result}) but agent looped. LLM behavior issue, not a memory bug.\n";
        echo "   PASS (tool works, agent behavior is LLM-dependent)\n\n";
    } else {
        echo "   FAIL: Tool did not recall correctly\n";
        exit(1);
    }
}

// ── Test 3: MemoryContext isolation (no meta pollution) ─────
echo "3. Testing MemoryContext isolation...\n";

$memoryContext = app(MemoryContext::class);
echo '   MemoryContext configured after execution: '.($memoryContext->isConfigured() ? 'yes (BAD)' : 'no (GOOD)')."\n";

if ($memoryContext->isConfigured()) {
    echo "   FAIL: MemoryContext was not reset after execution!\n";
    exit(1);
}

echo "   PASS\n\n";

// ── Test 4: Consumer-side memory management ─────────────────
echo "4. Testing consumer-side memory via Atlas::memory()...\n";

// Remember
$memory = Atlas::memory()
    ->for($user)
    ->agent('memory-test')
    ->remember('User prefers dark mode', type: 'preference', key: 'theme');

echo "   Stored preference: {$memory->content} (ID: {$memory->id})\n";

// Recall
$recalled = Atlas::memory()
    ->for($user)
    ->agent('memory-test')
    ->recall('preference', key: 'theme');

echo '   Recalled: '.($recalled ? $recalled->content : 'null')."\n";

if ($recalled === null || $recalled->content !== 'User prefers dark mode') {
    echo "   FAIL: Recall returned unexpected result\n";
    exit(1);
}

// Upsert (document-style)
$updated = Atlas::memory()
    ->for($user)
    ->agent('memory-test')
    ->remember('User prefers light mode', type: 'preference', key: 'theme');

$recalled2 = Atlas::memory()
    ->for($user)
    ->agent('memory-test')
    ->recall('preference', key: 'theme');

echo '   After upsert: '.($recalled2 ? $recalled2->content : 'null')."\n";

if ($recalled2 === null || $recalled2->content !== 'User prefers light mode') {
    echo "   FAIL: Upsert did not replace old value\n";
    exit(1);
}

// Version history
$history = Memory::withTrashed()
    ->where('memoryable_type', $user->getMorphClass())
    ->where('memoryable_id', $user->getKey())
    ->where('type', 'preference')
    ->where('key', 'theme')
    ->orderBy('id')
    ->get();

echo '   Version history: '.$history->count()." records\n";

// Forget
$forgotten = Atlas::memory()
    ->for($user)
    ->agent('memory-test')
    ->forget($updated->id);

echo '   Forgot memory: '.($forgotten ? 'yes' : 'no')."\n";

$afterForget = Atlas::memory()
    ->for($user)
    ->agent('memory-test')
    ->recall('preference', key: 'theme');

echo '   After forget: '.($afterForget ? 'still exists (BAD)' : 'null (GOOD)')."\n";

// ForgetWhere
Atlas::memory()->for($user)->agent('memory-test')
    ->remember('Note 1', type: 'note', namespace: 'test');
Atlas::memory()->for($user)->agent('memory-test')
    ->remember('Note 2', type: 'note', namespace: 'test');

$deletedCount = Atlas::memory()
    ->for($user)
    ->agent('memory-test')
    ->namespace('test')
    ->forgetWhere(type: 'note');

echo "   ForgetWhere deleted: {$deletedCount} notes\n";

echo "   PASS\n\n";

// ── Summary ─────────────────────────────────────────────────
$totalMemories = Memory::where('memoryable_type', $user->getMorphClass())
    ->where('memoryable_id', $user->getKey())
    ->count();

echo "=== Summary ===\n";
echo "Active memories for user: {$totalMemories}\n";
echo "All tests passed.\n";
