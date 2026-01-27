<template>
    <div class="flex h-full bg-chat-main">
        <!-- Sidebar -->
        <div class="w-64 bg-chat-sidebar text-white flex flex-col">
            <div class="px-3 py-2 border-b border-white/10">
                <img src="/atlas-logo.png" alt="Atlas" class="w-full" />
            </div>

            <!-- Agent Selector -->
            <div class="p-3 border-b border-white/10">
                <label class="block text-xs text-gray-400 mb-1">Agent</label>
                <select
                    v-model="selectedAgent"
                    @change="onAgentChange"
                    class="w-full bg-chat-input text-white text-sm rounded-md px-3 py-2 border border-white/10 focus:outline-none focus:border-chat-accent cursor-pointer"
                >
                    <option :value="null" disabled>Select an agent...</option>
                    <option v-for="agent in agents" :key="agent.key" :value="agent.key">
                        {{ agent.key }}
                    </option>
                </select>
            </div>

            <!-- New Chat Button -->
            <div class="p-3">
                <button
                    @click="newChat"
                    class="w-full bg-chat-accent hover:bg-chat-accent-hover text-white text-sm font-medium py-2.5 px-4 rounded-md transition-colors cursor-pointer flex items-center justify-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    New Chat
                </button>
            </div>

            <!-- Thread List -->
            <div class="flex-1 overflow-y-auto">
                <div
                    v-for="thread in filteredThreads"
                    :key="thread.id"
                    @click="selectThread(thread)"
                    class="group px-3 py-3 cursor-pointer hover:bg-white/5 transition-colors border-l-2 border-transparent"
                    :class="{ 'bg-white/5 border-l-chat-accent': currentThread?.id === thread.id }"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium truncate text-gray-200">
                                {{ thread.title || 'New conversation' }}
                            </p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ formatDate(thread.updated_at) }}
                            </p>
                        </div>
                        <button
                            @click.stop="deleteThread(thread.id)"
                            class="opacity-0 group-hover:opacity-100 text-gray-500 hover:text-red-400 p-1 transition-opacity cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div v-if="filteredThreads.length === 0" class="p-4 text-center text-gray-500 text-sm">
                    No conversations yet
                </div>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="flex-1 flex flex-col relative">
            <!-- Messages -->
            <div ref="messagesContainer" class="flex-1 overflow-y-auto px-6 py-8 pb-32 space-y-6">
                <template v-if="currentThread && currentThread.messages.length > 0">
                    <div
                        v-for="message in currentThread.messages"
                        :key="message.id"
                        class="flex"
                        :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
                    >
                        <div
                            class="max-w-2xl rounded-2xl px-4 py-3"
                            :class="message.role === 'user'
                                ? 'bg-chat-accent text-white rounded-br-md'
                                : 'bg-chat-assistant text-gray-200 rounded-bl-md'"
                        >
                            <!-- Processing indicator -->
                            <div v-if="message.status === 'processing'" class="flex items-center gap-3">
                                <div class="flex gap-1">
                                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-pulse"></span>
                                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-pulse animation-delay-200"></span>
                                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-pulse animation-delay-400"></span>
                                </div>
                                <span class="text-sm text-gray-400">Thinking<span class="thinking-dots"></span></span>
                            </div>

                            <!-- Message content -->
                            <div
                                v-else-if="message.role === 'assistant'"
                                class="prose prose-invert prose-sm max-w-none"
                                v-html="renderMarkdown(message.content)"
                            ></div>
                            <div v-else class="whitespace-pre-wrap">{{ message.content }}</div>

                            <!-- Error indicator -->
                            <div v-if="message.status === 'failed'" class="mt-2 text-xs text-red-300">
                                Failed to generate response
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Empty state -->
                <div v-else class="h-full flex items-center justify-center">
                    <div class="text-center text-gray-500">
                        <svg class="w-16 h-16 mx-auto mb-4 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                        <p class="text-lg font-medium text-gray-400 mb-1">Atlas Chat</p>
                        <p v-if="!selectedAgent" class="text-sm">Select an agent to start</p>
                        <p v-else class="text-sm">Start typing to begin a conversation</p>
                    </div>
                </div>
            </div>

            <!-- Floating Input Area -->
            <div class="absolute bottom-6 left-1/2 -translate-x-1/2 w-full max-w-2xl px-4">
                <form @submit.prevent="sendMessage" class="relative">
                    <input
                        v-model="messageInput"
                        type="text"
                        :placeholder="selectedAgent ? 'Send a message...' : 'Select an agent to start...'"
                        class="w-full bg-chat-input text-white rounded-3xl px-6 py-4 pr-14 border border-white/20 focus:outline-none focus:border-chat-accent placeholder-gray-500 disabled:opacity-50 shadow-2xl"
                        :disabled="isProcessing || !selectedAgent"
                    />
                    <button
                        type="submit"
                        :disabled="!messageInput.trim() || isProcessing || !selectedAgent"
                        class="absolute right-3 top-1/2 -translate-y-1/2 p-2.5 bg-chat-accent hover:bg-chat-accent-hover text-white rounded-xl cursor-pointer disabled:opacity-30 disabled:bg-gray-600 disabled:cursor-not-allowed transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                    </button>
                </form>
                <p v-if="selectedAgent" class="text-xs text-gray-500 text-center mt-3">
                    {{ selectedAgent }}
                </p>
            </div>
        </div>
    </div>
</template>

<script>
import MarkdownIt from 'markdown-it';

const md = new MarkdownIt({
    html: false,
    linkify: true,
    typographer: true,
});

export default {
    data() {
        return {
            agents: [],
            selectedAgent: null,
            threads: [],
            currentThread: null,
            messageInput: '',
            isProcessing: false,
            thinkingTime: 0,
            thinkingTimer: null,
        };
    },

    computed: {
        filteredThreads() {
            if (!this.selectedAgent) return [];
            return this.threads.filter(t => t.agent_key === this.selectedAgent);
        },
    },

    async mounted() {
        await this.loadAgents();
        await this.loadThreads();
        this.parseUrl();
        window.addEventListener('popstate', this.parseUrl);
    },

    beforeUnmount() {
        this.stopTimers();
        window.removeEventListener('popstate', this.parseUrl);
    },

    methods: {
        parseUrl() {
            const path = window.location.pathname;
            const match = path.match(/\/sandbox\/chat(?:\/([a-z0-9-]+))?(?:\/(\d+))?/);

            if (match) {
                const agentKey = match[1];
                const threadId = match[2];

                // Only set agent if specified in URL
                if (agentKey && this.agents.find(a => a.key === agentKey)) {
                    this.selectedAgent = agentKey;
                }

                if (threadId) {
                    this.loadThread(parseInt(threadId, 10));
                }
            }
        },

        updateUrl() {
            let url = `/sandbox/chat/${this.selectedAgent}`;
            if (this.currentThread) {
                url += `/${this.currentThread.id}`;
            }
            window.history.pushState({}, '', url);
        },

        async loadAgents() {
            try {
                const response = await fetch('/api/chat/agents');
                const data = await response.json();
                this.agents = data.agents;
            } catch (error) {
                console.error('Failed to load agents:', error);
            }
        },

        async loadThreads() {
            try {
                const response = await fetch('/api/chat/threads');
                const data = await response.json();
                this.threads = data.threads;
            } catch (error) {
                console.error('Failed to load threads:', error);
            }
        },

        onAgentChange() {
            this.currentThread = null;
            // Navigate to agent home (no thread)
            if (this.selectedAgent) {
                window.history.pushState({}, '', `/sandbox/chat/${this.selectedAgent}`);
            } else {
                window.history.pushState({}, '', '/sandbox/chat');
            }
        },

        newChat() {
            this.currentThread = null;
            if (this.selectedAgent) {
                window.history.pushState({}, '', `/sandbox/chat/${this.selectedAgent}`);
            } else {
                window.history.pushState({}, '', '/sandbox/chat');
            }
        },

        async createThread() {
            try {
                const response = await fetch('/api/chat/threads', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ agent_key: this.selectedAgent }),
                });
                const data = await response.json();
                this.currentThread = data.thread;
                await this.loadThreads();
                this.updateUrl();
            } catch (error) {
                console.error('Failed to create thread:', error);
            }
        },

        async loadThread(id) {
            try {
                const response = await fetch(`/api/chat/threads/${id}`);
                const data = await response.json();
                this.currentThread = data.thread;
                this.selectedAgent = data.thread.agent_key;
                this.$nextTick(() => this.scrollToBottom());
            } catch (error) {
                console.error('Failed to load thread:', error);
            }
        },

        selectThread(thread) {
            this.loadThread(thread.id);
            this.updateUrl();
        },

        async deleteThread(id) {
            try {
                await fetch(`/api/chat/threads/${id}`, { method: 'DELETE' });
                if (this.currentThread?.id === id) {
                    this.currentThread = null;
                    this.updateUrl();
                }
                await this.loadThreads();
            } catch (error) {
                console.error('Failed to delete thread:', error);
            }
        },

        async sendMessage() {
            const content = this.messageInput.trim();
            if (!content || this.isProcessing) return;

            this.messageInput = '';

            if (!this.currentThread) {
                await this.createThread();
            }

            this.isProcessing = true;
            this.startThinkingTimer();

            const tempUserMessage = {
                id: 'temp-user-' + Date.now(),
                role: 'user',
                content: content,
                status: 'completed',
            };

            const tempAssistantMessage = {
                id: 'temp-assistant-' + Date.now(),
                role: 'assistant',
                content: '',
                status: 'processing',
            };

            this.currentThread.messages.push(tempUserMessage);
            this.currentThread.messages.push(tempAssistantMessage);
            this.$nextTick(() => this.scrollToBottom());

            try {
                const response = await fetch(`/api/chat/threads/${this.currentThread.id}/messages`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: content }),
                });
                const data = await response.json();
                this.currentThread = data.thread;
                await this.loadThreads();
                this.updateUrl();
                this.$nextTick(() => this.scrollToBottom());
            } catch (error) {
                console.error('Failed to send message:', error);
                this.currentThread.messages = this.currentThread.messages.filter(
                    m => m.id !== tempUserMessage.id && m.id !== tempAssistantMessage.id
                );
            } finally {
                this.isProcessing = false;
                this.stopTimers();
            }
        },

        startThinkingTimer() {
            this.thinkingTime = 0;
            this.thinkingTimer = setInterval(() => {
                this.thinkingTime++;
            }, 1000);
        },

        stopTimers() {
            if (this.thinkingTimer) {
                clearInterval(this.thinkingTimer);
                this.thinkingTimer = null;
            }
            this.thinkingTime = 0;
        },

        scrollToBottom() {
            const container = this.$refs.messagesContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        renderMarkdown(content) {
            if (!content) return '';
            return md.render(content);
        },

        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString(undefined, {
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
    },
};
</script>

<style>
/* ChatGPT-like color scheme */
.bg-chat-sidebar { background-color: #202123; }
.bg-chat-main { background-color: #343541; }
.bg-chat-assistant { background-color: #444654; }
.bg-chat-input { background-color: #40414f; }
.bg-chat-accent { background-color: #10a37f; }
.bg-chat-accent-hover { background-color: #1a7f64; }
.border-l-chat-accent { border-left-color: #10a37f; }
.border-chat-accent { border-color: #10a37f; }
.focus\:border-chat-accent:focus { border-color: #10a37f; }

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: transparent;
}

::-webkit-scrollbar-thumb {
    background: #565869;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #6e7086;
}

/* Firefox scrollbar */
* {
    scrollbar-width: thin;
    scrollbar-color: #565869 transparent;
}

/* Pulse animation for thinking dots */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}

.animate-pulse {
    animation: pulse 1.5s ease-in-out infinite;
}

.animation-delay-200 {
    animation-delay: 0.2s;
}

.animation-delay-400 {
    animation-delay: 0.4s;
}

/* Thinking dots animation */
.thinking-dots::after {
    content: '';
    animation: dots 1.5s steps(4, end) infinite;
}

@keyframes dots {
    0%, 20% { content: ''; }
    40% { content: '.'; }
    60% { content: '..'; }
    80%, 100% { content: '...'; }
}

/* Prose overrides for dark mode */
.prose-invert a { color: #10a37f; }
.prose-invert code { background-color: #1e1e1e; padding: 0.2em 0.4em; border-radius: 4px; }
.prose-invert pre { background-color: #1e1e1e; }
.prose-invert pre code { background-color: transparent; padding: 0; }
</style>
