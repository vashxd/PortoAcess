<script setup>
import { computed } from 'vue';

const props = defineProps({
    // [{ label: 'seg', value: 10 }, ...]
    data: { type: Array, required: true },
    color: { type: String, default: '#0ea5e9' },
    height: { type: Number, default: 160 },
});

const max = computed(() => Math.max(...props.data.map((d) => d.value), 1));
</script>

<template>
    <div class="flex items-end gap-1 overflow-x-auto" :style="{ height: height + 'px' }">
        <div
            v-for="(d, i) in data"
            :key="i"
            class="group relative flex min-w-[14px] flex-1 flex-col items-center justify-end"
            style="height: 100%"
        >
            <div
                class="w-full rounded-t transition-all"
                :style="{ height: Math.max((d.value / max) * 100, 2) + '%', backgroundColor: color }"
            ></div>
            <span class="mt-1 origin-top-left whitespace-nowrap text-[9px] text-gray-400">{{ d.label }}</span>
            <span
                class="pointer-events-none absolute -top-6 hidden rounded bg-slate-800 px-1.5 py-0.5 text-[10px] font-bold text-white group-hover:block"
            >
                {{ d.value }}
            </span>
        </div>
    </div>
</template>
