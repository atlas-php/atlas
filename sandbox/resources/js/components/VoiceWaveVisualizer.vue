<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { PhoneOff } from 'lucide-vue-next';
import type { SessionStatus } from '../composables/useVoice';

const props = defineProps<{
    status: SessionStatus;
    audioLevel: number;
    isListening: boolean;
    isSpeaking: boolean;
}>();

const emit = defineEmits<{
    stop: [];
}>();

const canvasRef = ref<HTMLCanvasElement | null>(null);
let animFrame: number | null = null;
let bars: number[] = Array(24).fill(0);

function draw() {
    const canvas = canvasRef.value;
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const w = canvas.width;
    const h = canvas.height;
    ctx.clearRect(0, 0, w, h);

    const barCount = bars.length;
    const barWidth = (w / barCount) * 0.6;
    const gap = (w / barCount) * 0.4;

    for (let i = 0; i < barCount; i++) {
        const target = props.isListening || props.isSpeaking
            ? (Math.sin(Date.now() / 150 + i * 0.5) * 0.5 + 0.5) * Math.max(props.audioLevel, props.isSpeaking ? 0.5 : 0.1)
            : 0.05;

        bars[i] += (target - bars[i]) * 0.15;
    }

    const color = props.isSpeaking ? '#60a5fa' : '#4ade80';

    for (let i = 0; i < barCount; i++) {
        const barHeight = Math.max(bars[i] * h * 0.8, 2);
        const x = i * (barWidth + gap) + gap / 2;
        const y = (h - barHeight) / 2;

        ctx.fillStyle = color;
        ctx.globalAlpha = 0.6 + bars[i] * 0.4;
        ctx.beginPath();
        ctx.roundRect(x, y, barWidth, barHeight, 2);
        ctx.fill();
    }

    ctx.globalAlpha = 1;
    animFrame = requestAnimationFrame(draw);
}

onMounted(() => {
    if (props.status === 'active') {
        animFrame = requestAnimationFrame(draw);
    }
});

watch(() => props.status, (status) => {
    if (status === 'active' && animFrame === null) {
        animFrame = requestAnimationFrame(draw);
    } else if (status !== 'active' && animFrame !== null) {
        cancelAnimationFrame(animFrame);
        animFrame = null;
    }
});

onUnmounted(() => {
    if (animFrame !== null) {
        cancelAnimationFrame(animFrame);
    }
});
</script>

<template>
    <div class="rounded-2xl border border-border bg-muted/30 p-4">
        <!-- Header: status + end button -->
        <div class="flex items-center justify-between mb-3">
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
                <span v-if="status === 'closing'" class="text-yellow-400">Ending call...</span>
                <span v-else-if="status === 'connecting'">Connecting...</span>
                <span v-else-if="isSpeaking" class="text-blue-400">AI speaking</span>
                <span v-else-if="isListening" class="text-green-400">Listening</span>
                <span v-else>Voice active</span>
            </div>

            <!-- End call button -->
            <button
                class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium text-white transition-colors"
                :class="status === 'closing' ? 'bg-red-800 opacity-50 cursor-not-allowed' : 'bg-red-600 hover:bg-red-700'"
                :disabled="status === 'closing'"
                @click="emit('stop')"
            >
                <PhoneOff class="size-3" />
                {{ status === 'closing' ? 'Ending...' : 'End' }}
            </button>
        </div>

        <!-- Wave visualizer -->
        <canvas
            ref="canvasRef"
            width="400"
            height="48"
            class="w-full h-12"
        />
    </div>
</template>
