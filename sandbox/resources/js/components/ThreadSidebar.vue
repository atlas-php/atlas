<script setup lang="ts">
import { Plus, Trash2, MessageSquare } from 'lucide-vue-next';
import type { ConversationSummary } from '../composables/useChat';

defineProps<{
    conversations: ConversationSummary[];
    activeId: number | null;
}>();

const emit = defineEmits<{
    select: [id: number];
    'new-chat': [];
    delete: [id: number];
}>();

function formatTime(dateStr: string): string {
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;

    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) return `${diffHours}h ago`;

    const diffDays = Math.floor(diffHours / 24);
    if (diffDays < 7) return `${diffDays}d ago`;

    return date.toLocaleDateString();
}
</script>

<template>
    <aside class="flex w-[280px] flex-col border-r border-sidebar-border bg-sidebar">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-sidebar-border px-4 py-3">
            <h1 class="text-lg font-semibold text-sidebar-accent-foreground">Atlas</h1>
            <button
                class="flex items-center gap-1.5 rounded-md bg-brand px-3 py-1.5 text-xs font-medium text-brand-foreground hover:bg-brand/90 transition-colors"
                @click="emit('new-chat')"
            >
                <Plus class="size-3.5" />
                New Chat
            </button>
        </div>

        <!-- Thread list -->
        <div class="flex-1 overflow-y-auto scrollbar-thin">
            <div v-if="conversations.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                No conversations yet
            </div>

            <div v-else class="py-1">
                <div
                    v-for="conv in conversations"
                    :key="conv.id"
                    class="group flex w-full cursor-pointer items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-sidebar-accent"
                    :class="{
                        'bg-sidebar-accent': conv.id === activeId,
                    }"
                    @click="emit('select', conv.id)"
                >
                    <MessageSquare class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0 flex-1">
                        <p
                            class="truncate text-sm font-medium"
                            :class="
                                conv.id === activeId ? 'text-sidebar-accent-foreground' : 'text-sidebar-foreground'
                            "
                        >
                            {{ conv.title || 'New conversation' }}
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ formatTime(conv.updated_at) }}
                        </p>
                    </div>
                    <button
                        class="mt-0.5 shrink-0 rounded p-1 text-muted-foreground opacity-0 transition-opacity hover:bg-destructive/20 hover:text-destructive-foreground group-hover:opacity-100"
                        @click.stop="emit('delete', conv.id)"
                    >
                        <Trash2 class="size-3.5" />
                    </button>
                </div>
            </div>
        </div>
    </aside>
</template>
