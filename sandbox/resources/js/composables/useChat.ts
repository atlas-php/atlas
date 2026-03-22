import { ref, computed } from 'vue';

// ─── Types ───────────────────────────────────────────────────

export interface ConversationSummary {
    id: number;
    title: string;
    agent: string;
    created_at: string;
    updated_at: string;
}

export interface MessageAttachment {
    id: number;
    type: string;
    url: string;
    mime_type: string;
}

export interface ToolCall {
    id: number;
    name: string;
    arguments: Record<string, unknown>;
    result: string | null;
    status: string;
    duration_ms: number | null;
}

export interface ExecutionStep {
    id: number;
    sequence: number;
    status: string;
    finish_reason: string | null;
    tokens: { input: number; output: number };
    duration_ms: number | null;
    tool_calls: ToolCall[];
}

export interface MessageExecution {
    id: number;
    status: string;
    provider: string;
    model: string;
    duration_ms: number | null;
    tokens: { input: number; output: number };
    steps: ExecutionStep[];
}

export interface ChatMessage {
    id: number;
    role: string;
    status: string;
    content: string | null;
    author: { type: string; id: number | null; key: string | null; name: string };
    parent_id: number | null;
    sequence: number;
    created_at: string;
    execution?: MessageExecution | null;
    sibling_count?: number;
    sibling_index?: number;
    attachments?: MessageAttachment[];
    _optimistic?: boolean;
}

// ─── API helpers ─────────────────────────────────────────────

async function api<T>(url: string, options?: RequestInit): Promise<T> {
    const res = await fetch(`/api${url}`, {
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        ...options,
    });
    if (!res.ok) throw new Error(`API error: ${res.status}`);
    if (res.status === 204) return null as T;
    return res.json();
}

// ─── Composable ──────────────────────────────────────────────

export function useChat() {
    const conversations = ref<ConversationSummary[]>([]);
    const activeConversationId = ref<number | null>(null);
    const messages = ref<ChatMessage[]>([]);
    const isTyping = ref(false);
    const isLoading = ref(false);
    const hasMore = ref(false);
    const conversationTitle = ref('');
    const error = ref<string | null>(null);

    let subscribedChannelId: number | null = null;

    // ─── Conversations ───────────────────────────────

    async function loadConversations() {
        const data = await api<{ conversations: ConversationSummary[] }>('/conversations');
        conversations.value = data.conversations;
    }

    async function loadConversation(id: number) {
        isLoading.value = true;
        error.value = null;

        try {
            const data = await api<{
                id: number;
                title: string;
                agent: string;
                typing: boolean;
                messages: ChatMessage[];
                has_more: boolean;
            }>(`/conversations/${id}`);

            activeConversationId.value = id;
            conversationTitle.value = data.title;
            messages.value = data.messages;
            hasMore.value = data.has_more;
            isTyping.value = data.typing;

            subscribeToConversation(id);
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteConversation(id: number) {
        await api(`/conversations/${id}`, { method: 'DELETE' });
        conversations.value = conversations.value.filter((c) => c.id !== id);

        if (activeConversationId.value === id) {
            activeConversationId.value = null;
            messages.value = [];
            conversationTitle.value = '';
            unsubscribe();
        }
    }

    // ─── Messages ────────────────────────────────────

    async function sendMessage(
        text: string,
        attachments?: { base64: string; mime: string; name: string }[],
        previewUrls?: (string | null)[],
    ) {
        error.value = null;

        // Build optimistic attachments from preview URLs
        const optimisticAttachments: MessageAttachment[] = (attachments ?? []).map((att, i) => ({
            id: -(i + 1),
            type: att.mime.startsWith('image/') ? 'image' : 'document',
            url: previewUrls?.[i] ?? '',
            mime_type: att.mime,
        }));

        // Optimistic user message
        const optimistic: ChatMessage = {
            id: Date.now(),
            role: 'user',
            status: 'delivered',
            content: text,
            author: { type: 'user', id: 1, key: null, name: 'You' },
            parent_id: null,
            sequence: messages.value.length + 1,
            created_at: new Date().toISOString(),
            attachments: optimisticAttachments.length > 0 ? optimisticAttachments : undefined,
            _optimistic: true,
        };
        messages.value.push(optimistic);
        isTyping.value = true;

        try {
            const payload: Record<string, unknown> = {
                message: text,
            };

            if (activeConversationId.value) {
                payload.conversation_id = activeConversationId.value;
            }

            if (attachments?.length) {
                payload.attachments = attachments;
            }

            const data = await api<{ execution_id: number; conversation_id: number }>('/chat', {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            // Subscribe to the conversation channel for broadcasting
            if (!activeConversationId.value && data.conversation_id) {
                activeConversationId.value = data.conversation_id;
            }

            subscribeToConversation(data.conversation_id);
        } catch (e) {
            isTyping.value = false;
            error.value = e instanceof Error ? e.message : 'Failed to send message';
            messages.value = messages.value.filter((m) => m.id !== optimistic.id);
        }
    }

    async function loadOlderMessages() {
        if (!activeConversationId.value || !hasMore.value || isLoading.value) return;

        const oldestId = messages.value[0]?.id;
        if (!oldestId) return;

        isLoading.value = true;
        try {
            const data = await api<{ messages: ChatMessage[]; has_more: boolean }>(
                `/conversations/${activeConversationId.value}/messages?before=${oldestId}&limit=20`,
            );
            messages.value = [...data.messages, ...messages.value];
            hasMore.value = data.has_more;
        } finally {
            isLoading.value = false;
        }
    }

    async function retryLastMessage() {
        if (!activeConversationId.value) return;

        error.value = null;

        // Remove the last assistant message so the typing indicator takes its place
        const lastAssistantIdx = messages.value.findLastIndex((m) => m.role === 'assistant');
        if (lastAssistantIdx !== -1) {
            messages.value.splice(lastAssistantIdx, 1);
        }

        isTyping.value = true;

        try {
            await api(`/conversations/${activeConversationId.value}/retry`, {
                method: 'POST',
            });
        } catch (e) {
            isTyping.value = false;
            error.value = e instanceof Error ? e.message : 'Retry failed';
            // Reload to restore the message we removed
            await reloadMessages();
        }
    }

    async function cycleSibling(messageId: number, index: number) {
        if (!activeConversationId.value) return;

        await api(`/conversations/${activeConversationId.value}/messages/${messageId}/cycle`, {
            method: 'POST',
            body: JSON.stringify({ index }),
        });
        await reloadMessages();
    }

    async function reloadMessages() {
        if (!activeConversationId.value) return;

        const data = await api<{
            id: number;
            title: string;
            typing: boolean;
            messages: ChatMessage[];
            has_more: boolean;
        }>(`/conversations/${activeConversationId.value}`);

        conversationTitle.value = data.title;
        messages.value = data.messages;
        hasMore.value = data.has_more;
        isTyping.value = data.typing;
    }

    // ─── Broadcasting ────────────────────────────────

    function subscribeToConversation(id: number) {
        // Already subscribed to this channel
        if (subscribedChannelId === id) return;

        unsubscribe();

        if (typeof window.Echo === 'undefined') {
            console.warn('[useChat] Echo not available — broadcasting disabled');
            return;
        }

        subscribedChannelId = id;

        window.Echo.channel(`conversation.${id}`)
            .listen('.ExecutionCompleted', () => {
                isTyping.value = false;
                reloadMessages();
                loadConversations();
            })
            .listen('.ExecutionFailed', (data: { error?: string }) => {
                isTyping.value = false;
                error.value = data.error ?? 'Execution failed';
                reloadMessages();
            });
    }

    function unsubscribe() {
        if (subscribedChannelId !== null) {
            try {
                window.Echo.leave(`conversation.${subscribedChannelId}`);
            } catch {
                // Ignore
            }
            subscribedChannelId = null;
        }
    }

    // ─── New conversation ────────────────────────────

    function startNewConversation() {
        unsubscribe();
        activeConversationId.value = null;
        messages.value = [];
        conversationTitle.value = '';
        hasMore.value = false;
        isTyping.value = false;
        error.value = null;
    }

    const isEmpty = computed(() => messages.value.length === 0 && !isTyping.value);

    return {
        conversations,
        activeConversationId,
        messages,
        conversationTitle,
        isTyping,
        isLoading,
        hasMore,
        isEmpty,
        error,
        loadConversations,
        loadConversation,
        deleteConversation,
        sendMessage,
        loadOlderMessages,
        retryLastMessage,
        cycleSibling,
        startNewConversation,
        unsubscribe,
    };
}
