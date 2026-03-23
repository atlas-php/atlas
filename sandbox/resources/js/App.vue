<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue';
import ThreadSidebar from './components/ThreadSidebar.vue';
import ChatThread from './components/ChatThread.vue';
import ChatInput from './components/ChatInput.vue';
import ChatTypingIndicator from './components/ChatTypingIndicator.vue';
import VoiceWaveVisualizer from './components/VoiceWaveVisualizer.vue';
import { useChat } from './composables/useChat';
import { useAttachments } from './composables/useAttachments';
import { useVoice } from './composables/useVoice';

const chat = useChat();
const { attachments, hasAttachments, canAddMore, addFiles, removeAttachment, clearAttachments, toPayload } =
    useAttachments();
const voice = useVoice();

const chatThreadRef = ref<InstanceType<typeof ChatThread> | null>(null);
const chatInputRef = ref<InstanceType<typeof ChatInput> | null>(null);

// Register scroll callback so composable can trigger scroll-to-bottom
chat.onScrollToBottom(() => {
    chatThreadRef.value?.scrollToBottom('instant');
});

// Focus input when assistant response completes
chat.onResponseComplete(() => {
    chatInputRef.value?.focus();
});

// ─── URL-based thread routing ────────────────────────

function getThreadIdFromUrl(): number | null {
    const match = window.location.pathname.match(/^\/thread\/(\d+)/);
    return match ? parseInt(match[1], 10) : null;
}

function pushThreadUrl(id: number | null) {
    const path = id ? `/thread/${id}` : '/';
    if (window.location.pathname !== path) {
        window.history.pushState(null, '', path);
    }
}

// Sync URL when active conversation changes
watch(() => chat.activeConversationId.value, (id) => {
    pushThreadUrl(id);
});

// Handle browser back/forward
function onPopState() {
    const id = getThreadIdFromUrl();
    if (id && id !== chat.activeConversationId.value) {
        chat.loadConversation(id);
    } else if (!id) {
        chat.startNewConversation();
    }
}

onMounted(() => {
    window.addEventListener('popstate', onPopState);
    chat.loadConversations();

    // Load thread from URL on initial page load
    const threadId = getThreadIdFromUrl();
    if (threadId) {
        chat.loadConversation(threadId);
    }
});

onUnmounted(() => {
    window.removeEventListener('popstate', onPopState);
    chat.unsubscribe();
});

// ─── Handlers ────────────────────────────────────────

async function handleSend(text: string) {
    const payload = hasAttachments.value ? toPayload() : undefined;
    const previews = attachments.value.map((a) => a.previewUrl);
    clearAttachments();
    await chat.sendMessage(text, payload, previews);
}

function handleSelectConversation(id: number) {
    chat.loadConversation(id);
}

function handleNewChat() {
    chat.startNewConversation();
}

async function handleDeleteConversation(id: number) {
    await chat.deleteConversation(id);
}

async function handleVoiceToggle() {
    if (voice.sessionStatus.value === 'active') {
        await voice.stopSession();
        // Refresh chat to show persisted voice transcripts
        if (chat.activeConversationId.value) {
            setTimeout(() => chat.loadConversation(chat.activeConversationId.value!), 500);
        }
    } else {
        voice.startSession({
            conversation_id: chat.activeConversationId.value,
        });
        chatThreadRef.value?.scrollToBottom('smooth');
    }
}

function handleRetry() {
    chat.retryLastMessage();
}

function handleCycleSibling(messageId: number, index: number) {
    chat.cycleSibling(messageId, index);
}
</script>

<template>
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <ThreadSidebar
            :conversations="chat.conversations.value"
            :active-id="chat.activeConversationId.value"
            @select="handleSelectConversation"
            @new-chat="handleNewChat"
            @delete="handleDeleteConversation"
        />

        <!-- Chat area -->
        <div class="flex flex-1 flex-col min-w-0">
            <!-- Messages -->
            <ChatThread
                ref="chatThreadRef"
                :messages="chat.messages.value"
                :is-loading="chat.isLoading.value"
                :has-more="chat.hasMore.value"
                :is-empty="chat.isEmpty.value"
                :is-streaming="chat.isStreaming.value"
                :streaming-text="chat.streamingText.value"
                @load-more="chat.loadOlderMessages"
                @retry="handleRetry"
                @cycle-sibling="handleCycleSibling"
            >
                <template #typing>
                    <ChatTypingIndicator v-if="chat.isTyping.value" />
                </template>
            </ChatThread>

            <!-- Error banner -->
            <div v-if="chat.error.value || voice.error.value" class="px-4">
                <div class="mx-auto max-w-3xl mb-2 rounded-lg bg-red-950/50 border border-red-800 px-4 py-2 text-sm text-red-300">
                    {{ chat.error.value || voice.error.value }}
                </div>
            </div>

            <!-- Voice active: wave visualizer replaces input -->
            <div v-if="voice.sessionStatus.value === 'active' || voice.sessionStatus.value === 'connecting'" class="shrink-0 px-3 pb-4 pt-2 md:px-4">
                <div class="mx-auto max-w-3xl">
                    <VoiceWaveVisualizer
                        :status="voice.sessionStatus.value"
                        :audio-level="voice.audioLevel.value"
                        :is-listening="voice.isListening.value"
                        :is-speaking="voice.isSpeaking.value"
                        @stop="handleVoiceToggle"
                    />
                </div>
            </div>

            <!-- Normal: chat input -->
            <ChatInput
                v-else
                ref="chatInputRef"
                :disabled="chat.isTyping.value || chat.isStreaming.value"
                :attachments="attachments"
                :can-add-more="canAddMore"
                :voice-status="voice.sessionStatus.value"
                :voice-audio-level="voice.audioLevel.value"
                :voice-is-listening="voice.isListening.value"
                :voice-is-speaking="voice.isSpeaking.value"
                @send="handleSend"
                @add-files="addFiles"
                @remove-attachment="removeAttachment"
                @voice-toggle="handleVoiceToggle"
            />
        </div>
    </div>
</template>
