<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * File-based persistence service for chat threads.
 *
 * Stores conversation threads as JSON files in the storage directory.
 * Each thread is identified by a UUID and contains the full message history.
 */
class ThreadStorageService
{
    /**
     * @param  string  $storagePath  The path to the threads storage directory.
     */
    public function __construct(
        protected string $storagePath,
    ) {
        if (! is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Create a new thread.
     *
     * @param  string  $agent  The agent key for this thread.
     * @return array{uuid: string, agent: string, created_at: string, updated_at: string, messages: array<int, array{role: string, content: string, attachments?: array}>, metadata: array{total_tokens: int, message_count: int}}
     */
    public function create(string $agent): array
    {
        $uuid = $this->generateUuid();
        $now = (new DateTimeImmutable)->format('c');

        $thread = [
            'uuid' => $uuid,
            'agent' => $agent,
            'created_at' => $now,
            'updated_at' => $now,
            'messages' => [],
            'metadata' => [
                'total_tokens' => 0,
                'message_count' => 0,
            ],
        ];

        $this->save($thread);

        return $thread;
    }

    /**
     * Load an existing thread by UUID.
     *
     * @param  string  $uuid  The thread UUID.
     * @return array{uuid: string, agent: string, created_at: string, updated_at: string, messages: array<int, array{role: string, content: string}>, metadata: array{total_tokens: int, message_count: int}}|null
     */
    public function load(string $uuid): ?array
    {
        $path = $this->getPath($uuid);

        if (! file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $thread = json_decode($content, true);
        if (! is_array($thread)) {
            return null;
        }

        return $thread;
    }

    /**
     * Save a thread to storage.
     *
     * @param  array{uuid: string, agent: string, created_at: string, updated_at: string, messages: array<int, array{role: string, content: string}>, metadata: array{total_tokens: int, message_count: int}}  $thread
     */
    public function save(array $thread): void
    {
        if (! isset($thread['uuid'])) {
            throw new InvalidArgumentException('Thread must have a UUID');
        }

        $thread['updated_at'] = (new DateTimeImmutable)->format('c');
        $thread['metadata']['message_count'] = count($thread['messages']);

        $path = $this->getPath($thread['uuid']);
        $json = json_encode($thread, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        if (file_put_contents($path, $json) === false) {
            throw new RuntimeException("Failed to save thread to: {$path}");
        }
    }

    /**
     * List all threads with metadata.
     *
     * @return array<int, array{uuid: string, agent: string, created_at: string, updated_at: string, message_count: int}>
     */
    public function list(): array
    {
        $threads = [];
        $files = glob($this->storagePath.'/*.json') ?: [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $thread = json_decode($content, true);
            if (! is_array($thread) || ! isset($thread['uuid'])) {
                continue;
            }

            $threads[] = [
                'uuid' => $thread['uuid'],
                'agent' => $thread['agent'] ?? 'unknown',
                'created_at' => $thread['created_at'] ?? '',
                'updated_at' => $thread['updated_at'] ?? '',
                'message_count' => $thread['metadata']['message_count'] ?? 0,
            ];
        }

        // Sort by updated_at descending
        usort($threads, function ($a, $b) {
            return $b['updated_at'] <=> $a['updated_at'];
        });

        return $threads;
    }

    /**
     * Delete a thread.
     *
     * @param  string  $uuid  The thread UUID.
     * @return bool True if deleted, false if not found.
     */
    public function delete(string $uuid): bool
    {
        $path = $this->getPath($uuid);

        if (! file_exists($path)) {
            return false;
        }

        return unlink($path);
    }

    /**
     * Add a message to a thread.
     *
     * @param  array{uuid: string, agent: string, created_at: string, updated_at: string, messages: array<int, array{role: string, content: string, attachments?: array}>, metadata: array{total_tokens: int, message_count: int}}  $thread
     * @param  string  $role  The message role (user or assistant).
     * @param  string  $content  The message content.
     * @param  array<int, array{type: string, source: string, data: string, mime_type?: string|null, title?: string|null, disk?: string|null}>  $attachments  Optional attachments.
     * @return array{uuid: string, agent: string, created_at: string, updated_at: string, messages: array<int, array{role: string, content: string, attachments?: array}>, metadata: array{total_tokens: int, message_count: int}}
     */
    public function addMessage(array $thread, string $role, string $content, array $attachments = []): array
    {
        $message = [
            'role' => $role,
            'content' => $content,
        ];

        if ($attachments !== []) {
            $message['attachments'] = $attachments;
        }

        $thread['messages'][] = $message;

        return $thread;
    }

    /**
     * Update token usage for a thread.
     *
     * @param  array{uuid: string, agent: string, created_at: string, updated_at: string, messages: array<int, array{role: string, content: string}>, metadata: array{total_tokens: int, message_count: int}}  $thread
     * @param  int  $tokens  The number of tokens to add.
     * @return array{uuid: string, agent: string, created_at: string, updated_at: string, messages: array<int, array{role: string, content: string}>, metadata: array{total_tokens: int, message_count: int}}
     */
    public function addTokens(array $thread, int $tokens): array
    {
        $thread['metadata']['total_tokens'] += $tokens;

        return $thread;
    }

    /**
     * Clear messages from a thread while preserving metadata.
     *
     * @param  array{uuid: string, agent: string, created_at: string, updated_at: string, messages: array<int, array{role: string, content: string}>, metadata: array{total_tokens: int, message_count: int}}  $thread
     * @return array{uuid: string, agent: string, created_at: string, updated_at: string, messages: array<int, array{role: string, content: string}>, metadata: array{total_tokens: int, message_count: int}}
     */
    public function clearMessages(array $thread): array
    {
        $thread['messages'] = [];
        $thread['metadata']['message_count'] = 0;

        return $thread;
    }

    /**
     * Get the file path for a thread.
     */
    protected function getPath(string $uuid): string
    {
        return $this->storagePath.'/'.$uuid.'.json';
    }

    /**
     * Generate a UUID v4.
     */
    protected function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0F | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
