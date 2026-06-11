<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps({ requests: Array });

function aprovar(r) {
    if (confirm(`Aprovar o cancelamento do registro de ${r.plate}? O registro fica no histórico como CANCELADO.`)) {
        router.post(route('admin.cancelamentos.aprovar', r.id), {}, { preserveScroll: true });
    }
}

function rejeitar(r) {
    router.post(route('admin.cancelamentos.rejeitar', r.id), {}, { preserveScroll: true });
}

const brl = (v) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const dth = (iso) => new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <Head title="Cancelamentos" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold text-gray-800">Solicitações de cancelamento</h2>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6">
            <div v-if="!requests.length" class="rounded-lg border-2 border-dashed border-gray-300 p-10 text-center text-gray-400">
                Nenhuma solicitação pendente.
            </div>

            <div v-for="r in requests" :key="r.id" class="mb-4 rounded-xl bg-white p-5 shadow">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <span class="font-mono text-xl font-black">{{ r.plate }}</span>
                        <span class="ms-3 text-sm text-gray-600">{{ r.entry_type }} · entrada {{ dth(r.entered_at) }} · {{ r.status }}</span>
                        <span v-if="r.amount_due > 0" class="ms-3 text-sm">valor {{ brl(r.amount_due) }} (pago {{ brl(r.paid) }})</span>
                    </div>
                    <div class="flex gap-2">
                        <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700" @click="aprovar(r)">
                            Aprovar cancelamento
                        </button>
                        <button class="rounded-lg border px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100" @click="rejeitar(r)">
                            Rejeitar
                        </button>
                    </div>
                </div>
                <p class="mt-3 rounded bg-slate-50 px-3 py-2 text-sm">
                    <strong>Motivo</strong> ({{ r.requested_by }}, {{ dth(r.requested_at) }}): {{ r.reason }}
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
