<script setup lang="ts">
import { computed, ref } from 'vue';
import { RotateCcw, ChevronLeft, ChevronRight, ChevronDown, Clock, Zap, Copy, Check, FileText, ImageIcon, AudioLines } from 'lucide-vue-next';
import { renderMarkdown } from '../utils/markdown';
import type { ChatMessage } from '../composables/useChat';

const props = defineProps<{
    message: ChatMessage;
    isLastAssistant: boolean;
    isLastUser: boolean;
}>();

const emit = defineEmits<{
    retry: [];
    'cycle-sibling': [messageId: number, index: number];
}>();

const isUser = computed(() => props.message.role === 'user');
const imageAttachments = computed(() =>
    (props.message.attachments ?? []).filter((a) => a.type === 'image' && a.url),
);
const docAttachments = computed(() =>
    (props.message.attachments ?? []).filter((a) => a.type !== 'image'),
);
const hasExecution = computed(() => !!props.message.execution);
const isRealtime = computed(() => props.message.metadata?.source === 'realtime');
const hasSiblings = computed(
    () => (props.message.sibling_count ?? 0) > 1,
);
const showExecution = ref(false);
const copied = ref(false);

const renderedContent = computed(() => {
    if (!props.message.content) return '';
    if (isUser.value) return props.message.content;
    return renderMarkdown(props.message.content);
});

function copyContent() {
    if (props.message.content) {
        navigator.clipboard.writeText(props.message.content);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    }
}

function cyclePrev() {
    const idx = (props.message.sibling_index ?? 1) - 1;
    if (idx >= 1) emit('cycle-sibling', props.message.id, idx);
}

function cycleNext() {
    const idx = (props.message.sibling_index ?? 1) + 1;
    if (idx <= (props.message.sibling_count ?? 1)) emit('cycle-sibling', props.message.id, idx);
}

function formatDuration(ms: number | null): string {
    if (!ms) return '';
    if (ms < 1000) return `${ms}ms`;
    return `${(ms / 1000).toFixed(1)}s`;
}

function formatTokens(n: number): string {
    if (n >= 1000) return `${(n / 1000).toFixed(1)}k`;
    return String(n);
}

function formatTime(dateStr: string): string {
    const date = new Date(dateStr);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <div class="group flex gap-3 py-2" :class="isUser ? 'justify-end' : 'justify-start'">
        <div :class="isUser ? 'max-w-[80%]' : 'max-w-[85%] w-full'">
            <!-- Attachments (above the bubble) -->
            <div v-if="message.attachments?.length" class="mb-2 flex flex-wrap gap-1.5" :class="isUser ? 'justify-end' : ''">
                <template v-for="att in message.attachments" :key="att.id">
                    <!-- User image attachments: show preview thumbnail -->
                    <a
                        v-if="isUser && att.type === 'image' && att.url"
                        :href="att.url"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <img
                            :src="att.url"
                            alt="attachment"
                            class="max-h-48 max-w-[220px] rounded-xl border border-border object-cover transition-opacity hover:opacity-80"
                        />
                    </a>

                    <!-- All other attachments: chip style with icon + mime -->
                    <a
                        v-else
                        :href="att.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center gap-2 rounded-lg border border-border bg-muted/50 px-3 py-2 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground hover:border-foreground/20"
                    >
                        <ImageIcon v-if="att.type === 'image'" class="size-3.5 shrink-0" />
                        <FileText v-else class="size-3.5 shrink-0" />
                        <span>{{ att.mime_type }}</span>
                    </a>
                </template>
            </div>

            <!-- Message bubble -->
            <div
                class="rounded-2xl px-4 py-2.5"
                :class="
                    isUser
                        ? 'bg-brand-bubble text-brand-foreground rounded-br-md'
                        : 'bg-muted text-foreground rounded-bl-md'
                "
            >
                <!-- User: plain text -->
                <div v-if="isUser" class="text-sm whitespace-pre-wrap">{{ message.content }}</div>

                <!-- Assistant: rendered markdown -->
                <div
                    v-else
                    class="prose prose-sm prose-invert max-w-none"
                    v-html="renderedContent"
                />
            </div>

            <!-- User message status -->
            <div v-if="isUser" class="mt-0.5 flex items-center justify-end gap-1.5 text-[10px] text-muted-foreground">
                <span v-if="isRealtime" class="flex items-center gap-0.5">
                    <AudioLines class="size-2.5" />
                    Voice
                </span>
                <template v-if="isLastUser">
                    <span v-if="isRealtime">·</span>
                    <span v-if="message.read_at">Read · {{ formatTime(message.read_at) }}</span>
                    <span v-else>Delivered</span>
                </template>
            </div>

            <!-- Action bar (assistant messages) -->
            <div
                v-if="!isUser && message.content"
                class="mt-1 flex items-center gap-1"
                :class="isRealtime ? '' : 'opacity-0 transition-opacity group-hover:opacity-100'"
            >
                <!-- Voice badge (always visible for realtime) -->
                <span v-if="isRealtime" class="flex items-center gap-0.5 px-1 text-[10px] text-muted-foreground">
                    <AudioLines class="size-3" />
                    Voice
                </span>

                <!-- Copy -->
                <button
                    class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    title="Copy"
                    @click="copyContent"
                >
                    <Check v-if="copied" class="size-3.5 text-green-400" />
                    <Copy v-else class="size-3.5" />
                </button>

                <!-- Retry (not available for realtime voice messages) -->
                <button
                    v-if="isLastAssistant && !isRealtime"
                    class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    title="Retry"
                    @click="emit('retry')"
                >
                    <RotateCcw class="size-3.5" />
                </button>

                <!-- Sibling navigation -->
                <div v-if="hasSiblings" class="ml-1 flex items-center gap-0.5 text-xs text-muted-foreground">
                    <button
                        class="rounded p-0.5 hover:bg-muted"
                        :disabled="(message.sibling_index ?? 1) <= 1"
                        @click="cyclePrev"
                    >
                        <ChevronLeft class="size-3.5" />
                    </button>
                    <span>{{ message.sibling_index }} / {{ message.sibling_count }}</span>
                    <button
                        class="rounded p-0.5 hover:bg-muted"
                        :disabled="(message.sibling_index ?? 1) >= (message.sibling_count ?? 1)"
                        @click="cycleNext"
                    >
                        <ChevronRight class="size-3.5" />
                    </button>
                </div>

                <!-- Execution toggle -->
                <button
                    v-if="hasExecution"
                    class="ml-1 flex items-center gap-1 rounded px-1.5 py-0.5 text-xs text-muted-foreground hover:bg-muted hover:text-foreground"
                    @click="showExecution = !showExecution"
                >
                    <Zap class="size-3" />
                    <span v-if="message.execution?.duration_ms">
                        {{ formatDuration(message.execution.duration_ms) }}
                    </span>
                    <ChevronDown
                        class="size-3 transition-transform"
                        :class="{ 'rotate-180': showExecution }"
                    />
                </button>
            </div>

            <!-- Execution trace (collapsible) -->
            <div
                v-if="showExecution && message.execution"
                class="mt-2 rounded-lg border border-border bg-zinc-900/50 p-3 text-xs"
            >
                <!-- Execution header -->
                <div class="mb-2 flex items-center gap-3 text-muted-foreground">
                    <span>{{ message.execution.provider }} / {{ message.execution.model }}</span>
                    <span class="flex items-center gap-1">
                        <Clock class="size-3" />
                        {{ formatDuration(message.execution.duration_ms) }}
                    </span>
                    <span>
                        {{ formatTokens(message.execution.tokens.input) }} in /
                        {{ formatTokens(message.execution.tokens.output) }} out
                    </span>
                </div>

                <!-- Steps -->
                <div v-for="step in message.execution.steps" :key="step.id" class="mb-2 last:mb-0">
                    <div class="mb-1 text-muted-foreground">
                        Step {{ step.sequence + 1 }}
                        <span v-if="step.duration_ms" class="ml-2">{{ formatDuration(step.duration_ms) }}</span>
                        <span v-if="step.finish_reason" class="ml-2 text-zinc-500">{{ step.finish_reason }}</span>
                    </div>

                    <!-- Tool calls within step -->
                    <div v-for="tc in step.tool_calls" :key="tc.id" class="ml-3 mb-1 border-l-2 border-zinc-700 pl-3">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-violet-400">{{ tc.name }}</span>
                            <span
                                class="rounded px-1 py-0.5 text-[10px]"
                                :class="{
                                    'bg-green-900/30 text-green-400': tc.status === 'Completed',
                                    'bg-red-900/30 text-red-400': tc.status === 'Failed',
                                    'bg-yellow-900/30 text-yellow-400': tc.status !== 'Completed' && tc.status !== 'Failed',
                                }"
                            >
                                {{ tc.status }}
                            </span>
                            <span v-if="tc.duration_ms" class="text-zinc-500">{{ formatDuration(tc.duration_ms) }}</span>
                        </div>
                        <details v-if="tc.arguments && Object.keys(tc.arguments).length" class="mt-1">
                            <summary class="cursor-pointer text-zinc-500 hover:text-zinc-400">Arguments</summary>
                            <pre class="mt-1 overflow-x-auto rounded bg-zinc-950 p-2 text-zinc-400">{{ JSON.stringify(tc.arguments, null, 2) }}</pre>
                        </details>
                        <details v-if="tc.result" class="mt-1">
                            <summary class="cursor-pointer text-zinc-500 hover:text-zinc-400">Result</summary>
                            <pre class="mt-1 overflow-x-auto rounded bg-zinc-950 p-2 text-zinc-400 whitespace-pre-wrap">{{ tc.result }}</pre>
                        </details>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
