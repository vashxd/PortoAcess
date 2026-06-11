<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

defineProps({ prices: Array, entryTypes: Array, categories: Array });

const form = useForm({
    entry_type_id: null,
    vehicle_category_id: null,
    amount: null,
    valid_from: new Date().toISOString().slice(0, 10),
});

function criar() {
    form.post(route('admin.precos.store'), { preserveScroll: true, onSuccess: () => form.reset('amount') });
}

function encerrar(p) {
    if (confirm('Encerrar a vigência deste preço? O histórico é preservado para auditoria.')) {
        router.delete(route('admin.precos.destroy', p.id), { preserveScroll: true });
    }
}

const brl = (v) => Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const dt = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');
const vigente = (p) => !p.valid_to || new Date(p.valid_to) >= new Date();
</script>

<template>
    <Head title="Tabela de preços" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold text-gray-800">Tabela de preços (tipo × categoria, com vigência)</h2>
        </template>

        <div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6">
            <form class="grid gap-3 rounded-xl bg-white p-4 shadow sm:grid-cols-5" @submit.prevent="criar">
                <select v-model="form.entry_type_id" class="rounded-md border-gray-300 text-sm" required>
                    <option :value="null" disabled>Tipo de entrada…</option>
                    <option v-for="t in entryTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
                <select v-model="form.vehicle_category_id" class="rounded-md border-gray-300 text-sm" required>
                    <option :value="null" disabled>Categoria…</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <input v-model.number="form.amount" type="number" step="0.01" min="0" placeholder="Valor (R$)" class="rounded-md border-gray-300 text-sm" required />
                <input v-model="form.valid_from" type="date" class="rounded-md border-gray-300 text-sm" required />
                <button :disabled="form.processing" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50">
                    Cadastrar preço
                </button>
                <p class="text-xs text-gray-400 sm:col-span-5">
                    Ao cadastrar um novo preço para a mesma combinação, a vigência do anterior é encerrada automaticamente — o histórico fica preservado.
                </p>
            </form>

            <div class="overflow-hidden rounded-xl bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-slate-800 text-left text-xs uppercase text-white">
                        <tr>
                            <th class="px-4 py-3">Tipo de entrada</th>
                            <th class="px-4 py-3">Categoria</th>
                            <th class="px-4 py-3 text-right">Valor</th>
                            <th class="px-4 py-3">Vigência</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="p in prices" :key="p.id" :class="{ 'opacity-50': !vigente(p) }">
                            <td class="px-4 py-3">{{ p.entry_type?.name }}</td>
                            <td class="px-4 py-3">{{ p.vehicle_category?.name }}</td>
                            <td class="px-4 py-3 text-right font-bold">{{ brl(p.amount) }}</td>
                            <td class="px-4 py-3">{{ dt(p.valid_from) }} → {{ p.valid_to ? dt(p.valid_to) : 'atual' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-0.5 text-xs font-bold" :class="vigente(p) ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500'">
                                    {{ vigente(p) ? 'Vigente' : 'Encerrado' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button v-if="vigente(p)" class="font-semibold text-red-600 hover:underline" @click="encerrar(p)">Encerrar vigência</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
