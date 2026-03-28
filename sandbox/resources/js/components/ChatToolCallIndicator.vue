<script setup lang="ts">
import { Loader2, Check, X } from 'lucide-vue-next';
import type { ActiveToolCall } from '../composables/useChat';

defineProps<{
    toolCalls: ActiveToolCall[];
}>();

function formatDuration(startedAt: number): string {
    const ms = Date.now() - startedAt;
    return ms < 1000 ? `${ms}ms` : `${(ms / 1000).toFixed(1)}s`;
}
</script>

<template>
    <div class="flex justify-start py-1">
        <div class="rounded-2xl rounded-bl-md bg-muted px-4 py-2.5 space-y-1.5 max-w-[85%]">
            <div
                v-for="tc in toolCalls"
                :key="tc.id"
                class="flex items-center gap-2 text-sm"
            >
                <!-- Status icon -->
                <Loader2
                    v-if="tc.status === 'running'"
                    class="size-3.5 shrink-0 animate-spin text-violet-400"
                />
                <Check
                    v-else-if="tc.status === 'completed'"
                    class="size-3.5 shrink-0 text-emerald-400"
                />
                <X
                    v-else
                    class="size-3.5 shrink-0 text-red-400"
                />

                <!-- Tool name -->
                <span class="font-mono text-violet-400">{{ tc.name }}</span>

                <!-- Duration for completed/failed -->
                <span
                    v-if="tc.status !== 'running'"
                    class="text-xs text-muted-foreground"
                >
                    {{ formatDuration(tc.startedAt) }}
                </span>
            </div>
        </div>
    </div>
</template>
