<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import { ArrowUp, Paperclip, X, FileText } from 'lucide-vue-next';
import type { Attachment } from '../composables/useAttachments';
import { ACCEPTED_TYPES } from '../composables/useAttachments';
import type { SessionStatus } from '../composables/useRealtime';
import RealtimeButton from './RealtimeButton.vue';

const props = defineProps<{
    disabled: boolean;
    attachments: Attachment[];
    canAddMore: boolean;
    realtimeStatus?: SessionStatus;
    realtimeAudioLevel?: number;
    realtimeIsListening?: boolean;
    realtimeIsSpeaking?: boolean;
}>();

const emit = defineEmits<{
    send: [text: string];
    'add-files': [files: FileList];
    'remove-attachment': [index: number];
    'realtime-toggle': [];
}>();

const text = ref('');
const textareaRef = ref<HTMLTextAreaElement | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
let dragCounter = 0;

const canSubmit = computed(() => !props.disabled && (text.value.trim().length > 0 || props.attachments.length > 0));

function autoResize() {
    nextTick(() => {
        const el = textareaRef.value;
        if (!el) return;
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 150) + 'px';
    });
}

watch(text, autoResize);

function focusTextarea() {
    textareaRef.value?.focus();
}

function handleBoxClick(e: MouseEvent) {
    // Focus textarea when clicking anywhere in the box that isn't a button/input
    const target = e.target as HTMLElement;
    if (!target.closest('button') && !target.closest('input[type="file"]')) {
        focusTextarea();
    }
}

function handleSend() {
    const trimmed = text.value.trim();
    if (!canSubmit.value) return;
    emit('send', trimmed);
    text.value = '';
    nextTick(autoResize);
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleSend();
    }
}

function handleFileClick() {
    fileInputRef.value?.click();
}

function handleFileChange(e: Event) {
    const input = e.target as HTMLInputElement;
    if (input.files?.length) {
        emit('add-files', input.files);
        input.value = '';
    }
}

function handleDragEnter(e: DragEvent) {
    e.preventDefault();
    dragCounter++;
    isDragging.value = true;
}

function handleDragLeave() {
    dragCounter--;
    if (dragCounter <= 0) {
        dragCounter = 0;
        isDragging.value = false;
    }
}

defineExpose({ focus: focusTextarea });

function handleDrop(e: DragEvent) {
    dragCounter = 0;
    isDragging.value = false;
    if (e.dataTransfer?.files?.length) {
        emit('add-files', e.dataTransfer.files);
    }
}
</script>

<template>
    <div class="shrink-0 px-3 pb-4 pt-2 md:px-4">
        <div class="mx-auto max-w-3xl">
            <!-- Main input box -->
            <div
                class="relative rounded-2xl border transition-all"
                :class="
                    isDragging
                        ? 'border-dashed border-brand/60 bg-brand/5'
                        : 'border-border bg-muted/30 hover:border-ring/50 focus-within:border-ring/50 focus-within:shadow-md'
                "
                @dragenter="handleDragEnter"
                @dragover.prevent
                @dragleave="handleDragLeave"
                @drop.prevent="handleDrop"
                @click="handleBoxClick"
            >
                <!-- Drop overlay -->
                <div
                    v-if="isDragging"
                    class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-2xl"
                >
                    <p class="text-sm font-medium text-brand">Drop files here</p>
                </div>

                <!-- Attachment preview strip -->
                <div v-if="attachments.length" class="flex flex-wrap gap-2 px-4 pt-3">
                    <div
                        v-for="(att, index) in attachments"
                        :key="index"
                        class="group/att relative"
                    >
                        <!-- Image preview -->
                        <img
                            v-if="att.previewUrl"
                            :src="att.previewUrl"
                            :alt="att.name"
                            class="h-16 w-16 rounded-lg border border-border object-cover"
                        />
                        <!-- Doc chip -->
                        <div
                            v-else
                            class="flex items-center gap-2 rounded-lg border border-border bg-muted/50 px-3 py-2 text-xs text-muted-foreground"
                        >
                            <FileText class="size-3.5 shrink-0" />
                            <span class="max-w-[120px] truncate">{{ att.name }}</span>
                        </div>
                        <!-- Remove button -->
                        <button
                            class="absolute -right-1.5 -top-1.5 flex size-5 items-center justify-center rounded-full bg-zinc-700 text-zinc-200 opacity-0 shadow-sm transition-opacity group-hover/att:opacity-100"
                            @click.stop="emit('remove-attachment', index)"
                        >
                            <X class="size-3" />
                        </button>
                    </div>
                </div>

                <!-- Textarea row -->
                <div class="flex items-end gap-2 px-4 pt-3 pb-2">
                    <textarea
                        ref="textareaRef"
                        v-model="text"
                        placeholder="Send a message…"
                        rows="1"
                        class="max-h-[150px] min-h-[24px] flex-1 resize-none bg-transparent text-base text-foreground placeholder-muted-foreground outline-none md:text-sm"
                        :disabled="disabled"
                        @keydown="handleKeydown"
                        @input="autoResize"
                    />
                </div>

                <!-- Bottom toolbar -->
                <div class="flex items-center justify-between px-3 pb-2.5">
                    <!-- Left: attachment -->
                    <div class="flex items-center">
                        <button
                            class="flex size-8 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-40"
                            title="Attach file"
                            :disabled="!canAddMore"
                            @click.stop="handleFileClick"
                        >
                            <Paperclip class="size-4" />
                        </button>
                        <input
                            ref="fileInputRef"
                            type="file"
                            :accept="ACCEPTED_TYPES"
                            multiple
                            class="hidden"
                            @change="handleFileChange"
                        />
                    </div>

                    <!-- Right: mic + send -->
                    <div class="flex items-center gap-1">
                        <RealtimeButton
                            v-if="realtimeStatus !== undefined"
                            :status="realtimeStatus ?? 'idle'"
                            :audio-level="realtimeAudioLevel ?? 0"
                            :is-listening="realtimeIsListening ?? false"
                            :is-speaking="realtimeIsSpeaking ?? false"
                            @toggle="emit('realtime-toggle')"
                        />
                        <button
                            class="flex size-8 items-center justify-center rounded-full transition-colors"
                            :class="
                                canSubmit
                                    ? 'bg-brand text-brand-foreground hover:bg-brand/90'
                                    : 'bg-muted text-muted-foreground'
                            "
                            :disabled="!canSubmit"
                            @click.stop="handleSend"
                        >
                            <ArrowUp class="size-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
