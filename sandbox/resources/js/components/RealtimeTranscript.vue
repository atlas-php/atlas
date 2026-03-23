<script setup lang="ts">
import type { SessionStatus } from '../composables/useRealtime';

defineProps<{
    status: SessionStatus;
    userTranscript: string;
    assistantTranscript: string;
    isSpeaking: boolean;
}>();
</script>

<template>
    <div v-if="status === 'active' || status === 'connecting'" class="mx-auto max-w-3xl px-4 pb-2">
        <div class="rounded-xl border border-border/50 bg-muted/20 p-3 space-y-2">
            <!-- Status indicator -->
            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                <span class="relative flex size-2">
                    <span
                        class="absolute inline-flex size-full animate-ping rounded-full opacity-75"
                        :class="status === 'active' ? 'bg-green-400' : 'bg-yellow-400'"
                    />
                    <span
                        class="relative inline-flex size-2 rounded-full"
                        :class="status === 'active' ? 'bg-green-500' : 'bg-yellow-500'"
                    />
                </span>
                {{ status === 'connecting' ? 'Connecting...' : 'Voice session active' }}
            </div>

            <!-- User transcript (partial, updating) -->
            <div v-if="userTranscript" class="flex justify-end">
                <div class="max-w-[80%] rounded-2xl rounded-br-md bg-brand/10 px-3 py-1.5 text-sm text-foreground/70">
                    {{ userTranscript }}
                </div>
            </div>

            <!-- Assistant transcript (live) -->
            <div v-if="assistantTranscript" class="flex justify-start">
                <div class="max-w-[80%] rounded-2xl rounded-bl-md bg-muted px-3 py-1.5 text-sm text-foreground">
                    <span>{{ assistantTranscript }}</span>
                    <span v-if="isSpeaking" class="ml-0.5 inline-block animate-pulse">|</span>
                </div>
            </div>
        </div>
    </div>
</template>
