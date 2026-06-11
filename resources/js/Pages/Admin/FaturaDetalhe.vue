<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({ invoice: Object });

function baixa() {
    if (confirm(`Registrar baixa da fatura ${props.invoice.number}?`)) {
        router.post(route('admin.faturas.baixa', props.invoice.id), {}, { preserveScroll: true });
    }
}

const brl = (v) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const dt = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');
const dth = (iso) => (iso ? new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—');
</script>

<template>
    <Head :title="`Fatura ${invoice.number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-bold text-gray-800">
                    Fatura {{ invoice.number }} — {{ invoice.company?.name }}
                </h2>
                <div class="flex gap-2">
                    <a :href="route('admin.faturas.pdf', invoice.id)" class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">
                        Baixar PDF
                    </a>
                    <button
                        v-if="invoice.status !== 'paga'"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700"
                        @click="baixa"
                    >
                        Registrar baixa
                    </button>
                    <Link :href="route('admin.faturas.index')" class="rounded-lg border px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100">← voltar</Link>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6">
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-xl bg-white p-5 shadow">
                    <p class="text-xs font-semibold uppercase text-gray-400">Período</p>
                    <p class="mt-1 font-bold">{{ dt(invoice.period_start) }} – {{ dt(invoice.period_end) }}</p>
                </div>
                <div class="rounded-xl bg-white p-5 shadow">
                    <p class="text-xs font-semibold uppercase text-gray-400">Vencimento</p>
                    <p class="mt-1 font-bold">{{ dt(invoice.due_date) }}</p>
                </div>
                <div class="rounded-xl bg-white p-5 shadow">
                    <p class="text-xs font-semibold uppercase text-gray-400">Status</p>
                    <p class="mt-1 font-bold uppercase" :class="{ 'text-sky-700': invoice.status === 'aberta', 'text-emerald-700': invoice.status === 'paga', 'text-red-700': invoice.status === 'vencida' }">
                        {{ invoice.status }}
                    </p>
                    <p v-if="invoice.paid_at" class="text-xs text-gray-400">paga em {{ dth(invoice.paid_at) }}</p>
                </div>
                <div class="rounded-xl bg-slate-800 p-5 text-white shadow">
                    <p class="text-xs font-semibold uppercase text-slate-300">Total</p>
                    <p class="mt-1 text-2xl font-black">{{ brl(invoice.total) }}</p>
                    <p class="text-xs text-slate-300">{{ invoice.items?.length }} acessos</p>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-slate-800 text-left text-xs uppercase text-white">
                        <tr>
                            <th class="px-4 py-3">Data</th>
                            <th class="px-4 py-3">Placa</th>
                            <th class="px-4 py-3">Tipo de acesso</th>
                            <th class="px-4 py-3">Categoria</th>
                            <th class="px-4 py-3 text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="item in invoice.items" :key="item.id">
                            <td class="px-4 py-3">{{ dth(item.access_record?.entered_at) }}</td>
                            <td class="px-4 py-3 font-mono font-bold">{{ item.access_record?.vehicle?.plate }}</td>
                            <td class="px-4 py-3">{{ item.access_record?.entry_type?.name }}</td>
                            <td class="px-4 py-3">{{ item.access_record?.vehicle_category?.name }}</td>
                            <td class="px-4 py-3 text-right font-bold">{{ brl(item.amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
