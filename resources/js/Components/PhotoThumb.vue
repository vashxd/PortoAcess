<script setup>
import { ref } from 'vue';

const props = defineProps({
    src: { type: String, default: null },
    caption: { type: String, default: '' },
});

const open = ref(false);
</script>

<template>
    <button
        v-if="src"
        type="button"
        class="group relative block h-10 w-14 overflow-hidden rounded border border-gray-200 bg-slate-100"
        :title="caption ? `Ampliar foto (${caption})` : 'Ampliar foto'"
        @click="open = true"
    >
        <img :src="src" :alt="caption || 'Foto'" class="h-full w-full object-cover transition group-hover:opacity-80" />
    </button>
    <span v-else class="text-xs text-gray-300">sem foto</span>

    <!-- Lightbox -->
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4"
            @click="open = false"
        >
            <div class="flex max-h-full max-w-3xl flex-col items-center gap-2">
                <img :src="src" :alt="caption || 'Foto'" class="max-h-[80vh] w-auto rounded-lg shadow-2xl" />
                <p v-if="caption" class="text-sm font-semibold text-white">{{ caption }}</p>
                <p class="text-xs text-slate-300">Clique para fechar</p>
            </div>
        </div>
    </Teleport>
</template>
