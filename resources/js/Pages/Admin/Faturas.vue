<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ invoices: Array, companies: Array });

const show = ref(false);

const form = useForm({
    company_id: null,
    period_start: new Date(new Date().getFullYear(), new Date().getMonth() - 1, 1).toISOString().slice(0, 10),
    period_end: new Date(new Date().getFullYear(), new Date().getMonth(), 0).toISOString().slice(0, 10),
    due_date: null,
});

function fechar() {
    form.post(route('admin.faturas.store'), { onSuccess: () => (show.value = false) });
}

function baixa(f) {
    if (confirm(`Registrar baixa (pagamento) da fatura ${f.number}?`)) {
        router.post(route('admin.faturas.baixa', f.id), {}, { preserveScroll: true });
    }
}

const brl = (v) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const dt = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');
</script>

<template>
    <Head title="Faturas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800">Faturas de empresas conveniadas</h2>
                <button class="rounded-lg bg-sky-600 px-5 py-2 font-semibold text-white hover:bg-sky-700" @click="show = true">
                    Fechar período / gerar fatura
                </button>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6">
            <!-- Painel de pendências -->
            <div class="rounded-xl bg-white p-5 shadow">
                <h3 class="mb-3 font-bold text-gray-700">Débitos em aberto (ainda sem fatura)</h3>
                <div class="flex flex-wrap gap-3">
                    <div
                        v-for="c in companies.filter((c) => c.pending_billed > 0)"
                        :key="c.id"
                        class="rounded-lg border border-orange-200 bg-orange-50 px-4 py-2 text-sm"
                    >
                        <span class="font-semibold">{{ c.name }}</span>
                        <span class="ms-2 font-black text-orange-700">{{ brl(c.pending_billed) }}</span>
                        <span class="ms-2 text-xs text-gray-500 capitalize">({{ c.billing_cycle }})</span>
                    </div>
                    <p v-if="!companies.some((c) => c.pending_billed > 0)" class="text-sm text-gray-400">
                        Nenhum débito faturado pendente de fechamento.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-slate-800 text-left text-xs uppercase text-white">
                        <tr>
                            <th class="px-4 py-3">Número</th>
                            <th class="px-4 py-3">Empresa</th>
                            <th class="px-4 py-3">Período</th>
                            <th class="px-4 py-3">Vencimento</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="f in invoices" :key="f.id">
                            <td class="px-4 py-3">
                                <Link :href="route('admin.faturas.show', f.id)" class="font-bold text-sky-700 hover:underline">{{ f.number }}</Link>
                            </td>
                            <td class="px-4 py-3">{{ f.company?.name }}</td>
                            <td class="px-4 py-3">{{ dt(f.period_start) }} – {{ dt(f.period_end) }}</td>
                            <td class="px-4 py-3">{{ dt(f.due_date) }}</td>
                            <td class="px-4 py-3 text-right font-bold">{{ brl(f.total) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded px-2 py-0.5 text-xs font-bold uppercase"
                                    :class="{
                                        'bg-sky-100 text-sky-800': f.status === 'aberta',
                                        'bg-emerald-100 text-emerald-800': f.status === 'paga',
                                        'bg-red-100 text-red-800': f.status === 'vencida',
                                    }"
                                >
                                    {{ f.status }}{{ f.status === 'vencida' ? ' ⚠' : '' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a :href="route('admin.faturas.pdf', f.id)" class="me-3 font-semibold text-sky-600 hover:underline">PDF</a>
                                <button v-if="f.status !== 'paga'" class="font-semibold text-emerald-600 hover:underline" @click="baixa(f)">Registrar baixa</button>
                            </td>
                        </tr>
                        <tr v-if="!invoices.length">
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400">Nenhuma fatura gerada ainda.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="show" max-width="lg" @close="show = false">
            <form class="space-y-4 p-6" @submit.prevent="fechar">
                <h2 class="text-lg font-bold text-gray-800">Fechar período de faturamento</h2>
                <p class="text-sm text-gray-500">
                    Agrupa todos os acessos faturados da empresa no período (ainda sem fatura) e gera a fatura com extrato.
                </p>

                <div>
                    <label class="text-sm font-semibold text-gray-600">Empresa *</label>
                    <select v-model="form.company_id" class="mt-1 w-full rounded-md border-gray-300" required>
                        <option :value="null" disabled>Selecione…</option>
                        <option v-for="c in companies" :key="c.id" :value="c.id">
                            {{ c.name }} — em aberto: {{ brl(c.pending_billed) }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Início *</label>
                        <input v-model="form.period_start" type="date" class="mt-1 w-full rounded-md border-gray-300" required />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Fim *</label>
                        <input v-model="form.period_end" type="date" class="mt-1 w-full rounded-md border-gray-300" required />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Vencimento</label>
                        <input v-model="form.due_date" type="date" class="mt-1 w-full rounded-md border-gray-300" />
                        <p class="mt-1 text-xs text-gray-400">vazio = fim + 10 dias</p>
                    </div>
                </div>
                <p v-if="form.errors.period" class="text-sm font-semibold text-red-600">{{ form.errors.period }}</p>

                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" class="rounded-lg border px-5 py-2 font-semibold text-gray-600 hover:bg-gray-100" @click="show = false">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-sky-600 px-6 py-2 font-bold text-white hover:bg-sky-700 disabled:opacity-50">
                        Gerar fatura
                    </button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
