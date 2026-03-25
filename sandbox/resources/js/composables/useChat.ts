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
    owner: { type: string; id: number | null; key: string | null; name: string };
    parent_id: number | null;
    sequence: number;
    created_at: string;
    read_at: string | null;
    execution?: MessageExecution | null;
    sibling_count?: number;
    sibling_index?: number;
    attachments?: MessageAttachment[];
    metadata?: Record<string, unknown> | null;
    _optimistic?: boolean;
}

export interface ActiveToolCall {
    id: string;
    name: string;
    arguments: Record<string, unknown>;
    status: 'running' | 'completed' | 'failed';
    result?: string;
    startedAt: number;
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
    const isStreaming = ref(false);
    const streamingText = ref('');
    const isLoading = ref(false);
    const hasMore = ref(false);
    const conversationTitle = ref('');
    const error = ref<string | null>(null);
    const activeToolCalls = ref<ActiveToolCall[]>([]);

    let subscribedChannelId: number | null = null;
    let scrollToBottomCallback: (() => void) | null = null;
    let responseCompleteCallback: (() => void) | null = null;

    function clearToolCalls() {
        activeToolCalls.value = [];
    }

    function onScrollToBottom(cb: () => void) {
        scrollToBottomCallback = cb;
    }

    function onResponseComplete(cb: () => void) {
        responseCompleteCallback = cb;
    }

    function requestScroll() {
        scrollToBottomCallback?.();
    }

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
            owner: { type: 'user', id: 1, key: null, name: 'You' },
            parent_id: null,
            sequence: messages.value.length + 1,
            created_at: new Date().toISOString(),
            read_at: null,
            attachments: optimisticAttachments.length > 0 ? optimisticAttachments : undefined,
            _optimistic: true,
        };
        messages.value.push(optimistic);
        requestScroll();

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

        // Remove the last assistant message — typing indicator will show via ExecutionProcessing event
        const lastAssistantIdx = messages.value.findLastIndex((m) => m.role === 'assistant');
        if (lastAssistantIdx !== -1) {
            messages.value.splice(lastAssistantIdx, 1);
        }

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

    function markLastUserMessageRead() {
        for (let i = messages.value.length - 1; i >= 0; i--) {
            if (messages.value[i].role === 'user' && !messages.value[i].read_at) {
                messages.value[i].read_at = new Date().toISOString();
                break;
            }
        }
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
        console.log(`[Echo] Subscribing to conversation.${id}`);

        window.Echo.channel(`conversation.${id}`)
            .listen('.ExecutionProcessing', () => {
                console.log('[Echo] Execution processing');
                isTyping.value = true;
                markLastUserMessageRead();
                requestScroll();
            })
            .listen('.StreamChunkReceived', (data: { text: string }) => {
                // First chunk — switch from typing to streaming, mark user message read
                if (!isStreaming.value) {
                    console.log('[Echo] Stream started');
                    isTyping.value = false;
                    isStreaming.value = true;
                    streamingText.value = '';
                    clearToolCalls();
                    markLastUserMessageRead();
                }
                streamingText.value += data.text;
                requestScroll();
            })
            .listen('.StreamCompleted', () => {
                console.log('[Echo] Stream completed');
                isStreaming.value = false;
                streamingText.value = '';
                clearToolCalls();
                reloadMessages();
                loadConversations();
                responseCompleteCallback?.();
            })
            .listen('.ExecutionCompleted', () => {
                console.log('[Echo] Execution completed');
                isTyping.value = false;
                isStreaming.value = false;
                streamingText.value = '';
                clearToolCalls();
                markLastUserMessageRead();
                reloadMessages();
                loadConversations();
                responseCompleteCallback?.();
            })
            .listen('.ExecutionFailed', (data: { error?: string }) => {
                console.log('[Echo] Execution failed', data);
                isTyping.value = false;
                isStreaming.value = false;
                streamingText.value = '';
                clearToolCalls();
                error.value = data.error ?? 'Execution failed';
                reloadMessages();
            })
            .listen('.AgentToolCallStarted', (data: { toolCallId: string; toolName: string; arguments: Record<string, unknown>; stepNumber: number }) => {
                console.log('[Echo] Tool call started:', data.toolName);
                activeToolCalls.value.push({
                    id: data.toolCallId,
                    name: data.toolName,
                    arguments: data.arguments,
                    status: 'running',
                    startedAt: Date.now(),
                });
                requestScroll();
            })
            .listen('.AgentToolCallCompleted', (data: { toolCallId: string; toolName: string; result: string; isError: boolean }) => {
                console.log('[Echo] Tool call completed:', data.toolName);
                const tc = activeToolCalls.value.find((t) => t.id === data.toolCallId);
                if (tc) {
                    tc.status = 'completed';
                    tc.result = data.result;
                }
            })
            .listen('.AgentToolCallFailed', (data: { toolCallId: string; toolName: string; error: string }) => {
                console.log('[Echo] Tool call failed:', data.toolName);
                const tc = activeToolCalls.value.find((t) => t.id === data.toolCallId);
                if (tc) {
                    tc.status = 'failed';
                    tc.result = data.error;
                }
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
        isStreaming.value = false;
        streamingText.value = '';
        clearToolCalls();
        error.value = null;
    }

    const isEmpty = computed(() => messages.value.length === 0 && !isTyping.value);

    return {
        conversations,
        activeConversationId,
        messages,
        conversationTitle,
        isTyping,
        isStreaming,
        streamingText,
        activeToolCalls,
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
        onScrollToBottom,
        onResponseComplete,
    };
}
