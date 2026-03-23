<script setup lang="ts">
import { computed } from 'vue';
import { Mic, MicOff, Loader2 } from 'lucide-vue-next';
import type { SessionStatus } from '../composables/useRealtime';

const props = defineProps<{
    status: SessionStatus;
    audioLevel: number;
    isListening: boolean;
    isSpeaking: boolean;
}>();

const emit = defineEmits<{
    toggle: [];
}>();

const isActive = computed(() => props.status === 'active');
const isConnecting = computed(() => props.status === 'connecting');
const isIdle = computed(() => props.status === 'idle' || props.status === 'closed');

const glowIntensity = computed(() => {
    if (!isActive.value) return 0;
    if (props.isSpeaking) return 0.6;
    return props.audioLevel;
});

const ringColor = computed(() => {
    if (props.isSpeaking) return 'ring-blue-500/60';
    if (isActive.value && props.isListening) return 'ring-green-500/60';
    return 'ring-transparent';
});
</script>

<template>
    <button
        class="relative flex size-8 items-center justify-center rounded-full transition-all"
        :class="[
            isActive
                ? 'bg-red-600 text-white hover:bg-red-700'
                : isConnecting
                  ? 'bg-muted text-muted-foreground'
                  : 'text-muted-foreground hover:bg-muted hover:text-foreground',
            isActive ? `ring-2 ${ringColor}` : '',
        ]"
        :style="
            isActive
                ? { boxShadow: `0 0 ${glowIntensity * 20}px ${glowIntensity * 8}px rgba(34, 197, 94, ${glowIntensity * 0.4})` }
                : {}
        "
        :disabled="isConnecting"
        :title="isActive ? 'End voice session' : 'Start voice session'"
        @click="emit('toggle')"
    >
        <Loader2 v-if="isConnecting" class="size-4 animate-spin" />
        <MicOff v-else-if="isActive && !isListening" class="size-4" />
        <Mic v-else class="size-4" />
    </button>
</template>
