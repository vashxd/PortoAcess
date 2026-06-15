<script setup>
import { onMounted, ref } from 'vue';

const deferredPrompt = ref(null);
const show = ref(false);
const isIOS = ref(false);

onMounted(() => {
    if (window.matchMedia('(display-mode: standalone)').matches) return;
    if (localStorage.getItem('pwa-dismissed')) return;

    isIOS.value = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;

    if (isIOS.value) {
        show.value = true;
        return;
    }

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt.value = e;
        show.value = true;
    });
});

async function install() {
    if (!deferredPrompt.value) return;
    deferredPrompt.value.prompt();
    const { outcome } = await deferredPrompt.value.userChoice;
    deferredPrompt.value = null;
    show.value = false;
    if (outcome === 'accepted') localStorage.setItem('pwa-dismissed', '1');
}

function dismiss() {
    show.value = false;
    localStorage.setItem('pwa-dismissed', '1');
}
</script>

<template>
    <transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-full opacity-0"
    >
        <div
            v-if="show"
            class="fixed bottom-0 left-0 right-0 z-50 border-t border-slate-700 bg-slate-900 px-4 py-3"
            style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))"
        >
            <div class="mx-auto flex max-w-screen-sm items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <img src="/logo.png" alt="Porto da ponte" class="h-11 w-auto object-contain" />
                    <div>
                        <p class="text-sm font-bold text-white">Instalar Porto da ponte</p>
                        <p v-if="!isIOS" class="text-xs text-slate-400">Acesse direto da tela inicial</p>
                        <p v-else class="text-xs text-slate-400">
                            Toque em <strong class="text-white">Compartilhar</strong> →
                            <strong class="text-white">Tela de Início</strong>
                        </p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button
                        v-if="!isIOS"
                        class="rounded-lg bg-sky-500 px-4 py-2 text-sm font-bold text-white hover:bg-sky-400 active:bg-sky-600"
                        @click="install"
                    >
                        Instalar
                    </button>
                    <button class="rounded p-1.5 text-slate-400 hover:text-white" @click="dismiss" aria-label="Fechar">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </transition>
</template>
