<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { Loader2 } from 'lucide-vue-next';
import ChatMessageBubble from './ChatMessageBubble.vue';
import { renderMarkdown } from '../utils/markdown';
import type { ChatMessage } from '../composables/useChat';

const props = defineProps<{
    messages: ChatMessage[];
    isLoading: boolean;
    hasMore: boolean;
    isEmpty: boolean;
    isStreaming: boolean;
    streamingText: string;
}>();

const emit = defineEmits<{
    'load-more': [];
    retry: [];
    'cycle-sibling': [messageId: number, index: number];
}>();

const renderedStreamingText = computed(() => renderMarkdown(props.streamingText));

const containerRef = ref<HTMLElement | null>(null);
const sentinelRef = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;
let shouldAutoScroll = true;

function scrollToBottom(behavior: ScrollBehavior = 'smooth') {
    nextTick(() => {
        if (containerRef.value) {
            containerRef.value.scrollTo({
                top: containerRef.value.scrollHeight,
                behavior,
            });
        }
    });
}

function onScroll() {
    if (!containerRef.value) return;
    const { scrollTop, scrollHeight, clientHeight } = containerRef.value;
    shouldAutoScroll = scrollHeight - scrollTop - clientHeight < 100;
}

// Auto-scroll on new messages or when messages array is replaced (thread load)
watch(
    () => props.messages,
    () => {
        shouldAutoScroll = true;
        scrollToBottom('instant');
    },
);

// Auto-scroll while streaming
watch(
    () => props.streamingText,
    () => {
        if (shouldAutoScroll) scrollToBottom();
    },
);

// Setup IntersectionObserver for infinite scroll
onMounted(() => {
    if (!sentinelRef.value || !containerRef.value) return;

    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0].isIntersecting && props.hasMore && !props.isLoading) {
                const container = containerRef.value;
                const prevHeight = container?.scrollHeight ?? 0;

                emit('load-more');

                // Preserve scroll position after prepending messages
                nextTick(() => {
                    if (container) {
                        const newHeight = container.scrollHeight;
                        container.scrollTop += newHeight - prevHeight;
                    }
                });
            }
        },
        { root: containerRef.value, threshold: 0.1 },
    );

    observer.observe(sentinelRef.value);
});

function isLastAssistant(msg: ChatMessage, index: number): boolean {
    if (msg.role !== 'assistant') return false;
    for (let i = index + 1; i < props.messages.length; i++) {
        if (props.messages[i].role === 'assistant') return false;
    }
    return true;
}

function isLastUser(msg: ChatMessage, index: number): boolean {
    if (msg.role !== 'user') return false;
    for (let i = index + 1; i < props.messages.length; i++) {
        if (props.messages[i].role === 'user') return false;
    }
    return true;
}

defineExpose({ scrollToBottom });
</script>

<template>
    <div ref="containerRef" class="flex-1 overflow-y-auto scrollbar-thin" @scroll="onScroll">
        <!-- Infinite scroll sentinel -->
        <div ref="sentinelRef" class="h-1" />

        <!-- Loading indicator for older messages -->
        <div v-if="isLoading && hasMore" class="flex justify-center py-4">
            <Loader2 class="size-5 animate-spin text-muted-foreground" />
        </div>

        <!-- Empty state -->
        <div v-if="isEmpty" class="flex h-full flex-col items-center justify-center gap-4 px-4">
            <div class="flex size-16 items-center justify-center rounded-2xl bg-brand/10">
                <span class="text-3xl">✦</span>
            </div>
            <div class="text-center">
                <h2 class="text-lg font-semibold text-foreground">Atlas Sandbox</h2>
                <p class="mt-1 text-sm text-muted-foreground">Send a message to start a conversation</p>
            </div>
        </div>

        <!-- Messages -->
        <div v-else class="mx-auto max-w-3xl px-4 py-6 space-y-1">
            <ChatMessageBubble
                v-for="(msg, index) in messages"
                :key="msg.id"
                :message="msg"
                :is-last-assistant="isLastAssistant(msg, index)"
                :is-last-user="isLastUser(msg, index)"
                @retry="emit('retry')"
                @cycle-sibling="(messageId, idx) => emit('cycle-sibling', messageId, idx)"
            />

            <!-- Streaming assistant message -->
            <div v-if="isStreaming && streamingText" class="group flex gap-3 py-2 justify-start">
                <div class="max-w-[85%] w-full">
                    <div class="rounded-2xl rounded-bl-md bg-muted text-foreground px-4 py-2.5">
                        <div class="prose prose-sm prose-invert max-w-none streaming-cursor" v-html="renderedStreamingText" />
                    </div>
                </div>
            </div>

            <!-- Typing indicator slot -->
            <slot name="typing" />
        </div>
    </div>
</template>
