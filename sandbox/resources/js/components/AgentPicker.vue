<script setup lang="ts">
import { computed, ref } from 'vue';
import { PopoverRoot, PopoverTrigger, PopoverPortal, PopoverContent } from 'radix-vue';
import { Sparkles, Brain, Image as ImageIcon, Clapperboard, ChevronDown, Check, Bot } from 'lucide-vue-next';
import type { ChatAgent } from '../composables/useChat';

const props = defineProps<{
    agents: ChatAgent[];
    modelValue: string | null;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [key: string];
}>();

const open = ref(false);

const iconMap: Record<string, typeof Sparkles> = {
    sparkles: Sparkles,
    brain: Brain,
    image: ImageIcon,
    clapperboard: Clapperboard,
};

function iconFor(name: string | undefined) {
    return (name && iconMap[name]) || Bot;
}

const selected = computed(() => props.agents.find((a) => a.key === props.modelValue) ?? props.agents[0] ?? null);

function select(key: string) {
    emit('update:modelValue', key);
    open.value = false;
}
</script>

<template>
    <PopoverRoot v-model:open="open">
        <PopoverTrigger
            :disabled="disabled"
            class="flex items-center gap-1.5 rounded-full border border-border bg-muted/40 py-1 pl-2 pr-2.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:bg-muted/40 disabled:hover:text-muted-foreground"
            :title="disabled ? 'Agent is locked to this conversation' : 'Switch agent'"
            @click.stop
        >
            <component :is="iconFor(selected?.icon)" class="size-3.5 text-brand" />
            <span>{{ selected?.name ?? 'Agent' }}</span>
            <ChevronDown v-if="!disabled" class="size-3 opacity-70" />
        </PopoverTrigger>

        <PopoverPortal>
            <PopoverContent
                side="top"
                align="start"
                :side-offset="8"
                class="z-50 w-80 rounded-xl border border-border bg-popover p-1.5 text-popover-foreground shadow-xl outline-none"
                @click.stop
            >
                <p class="px-2 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                    Choose an agent
                </p>
                <button
                    v-for="agent in agents"
                    :key="agent.key"
                    class="flex w-full items-start gap-2.5 rounded-lg px-2 py-2 text-left transition-colors hover:bg-muted"
                    @click="select(agent.key)"
                >
                    <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand">
                        <component :is="iconFor(agent.icon)" class="size-4" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-1.5">
                            <span class="text-sm font-medium text-foreground">{{ agent.name }}</span>
                            <span class="rounded bg-muted px-1 py-0.5 font-mono text-[10px] text-muted-foreground">
                                {{ agent.model }}
                            </span>
                        </span>
                        <span class="mt-0.5 block text-xs leading-snug text-muted-foreground">
                            {{ agent.description }}
                        </span>
                    </span>
                    <Check v-if="agent.key === modelValue" class="mt-1 size-4 shrink-0 text-brand" />
                </button>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
