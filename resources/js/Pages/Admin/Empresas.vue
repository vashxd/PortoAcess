<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ companies: Array });

const show = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    cnpj: '',
    contact: '',
    email: '',
    phone: '',
    billing_cycle: 'mensal',
    credit_limit: null,
    discount_percent: 0,
    active: true,
});

function nova() {
    editing.value = null;
    form.reset();
    show.value = true;
}

function editar(c) {
    editing.value = c;
    Object.assign(form, {
        name: c.name,
        cnpj: c.cnpj || '',
        contact: c.contact || '',
        email: c.email || '',
        phone: c.phone || '',
        billing_cycle: c.billing_cycle,
        credit_limit: c.credit_limit,
        discount_percent: Number(c.discount_percent),
        active: c.active,
    });
    show.value = true;
}

function salvar() {
    const opts = { preserveScroll: true, onSuccess: () => (show.value = false) };
    if (editing.value) {
        form.put(route('admin.empresas.update', editing.value.id), opts);
    } else {
        form.post(route('admin.empresas.store'), opts);
    }
}

const brl = (v) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
</script>

<template>
    <Head title="Empresas conveniadas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800">Empresas conveniadas</h2>
                <button class="rounded-lg bg-sky-600 px-5 py-2 font-semibold text-white hover:bg-sky-700" @click="nova">+ Nova empresa</button>
            </div>
        </template>

        <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6">
            <div class="overflow-x-auto rounded-xl bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-slate-800 text-left text-xs uppercase text-white">
                        <tr>
                            <th class="px-4 py-3">Empresa</th>
                            <th class="px-4 py-3">CNPJ</th>
                            <th class="px-4 py-3">Fechamento</th>
                            <th class="px-4 py-3 text-right">Desconto</th>
                            <th class="px-4 py-3 text-right">Limite</th>
                            <th class="px-4 py-3 text-right">Em aberto</th>
                            <th class="px-4 py-3">Veículos</th>
                            <th class="px-4 py-3">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="c in companies" :key="c.id">
                            <td class="px-4 py-3 font-semibold">
                                <Link :href="route('admin.empresas.show', c.id)" class="text-sky-700 hover:underline">{{ c.name }}</Link>
                            </td>
                            <td class="px-4 py-3">{{ c.cnpj || '—' }}</td>
                            <td class="px-4 py-3 capitalize">{{ c.billing_cycle }}</td>
                            <td class="px-4 py-3 text-right">{{ Number(c.discount_percent) }}%</td>
                            <td class="px-4 py-3 text-right">{{ c.credit_limit ? brl(c.credit_limit) : '—' }}</td>
                            <td class="px-4 py-3 text-right font-bold" :class="c.pending_billed > 0 ? 'text-orange-700' : 'text-gray-400'">
                                {{ brl(c.pending_billed) }}
                            </td>
                            <td class="px-4 py-3">{{ c.authorized_vehicles_count }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-0.5 text-xs font-bold" :class="c.active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500'">
                                    {{ c.active ? 'Ativa' : 'Inativa' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button class="font-semibold text-sky-600 hover:underline" @click="editar(c)">Editar</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="show" max-width="xl" @close="show = false">
            <form class="space-y-4 p-6" @submit.prevent="salvar">
                <h2 class="text-lg font-bold text-gray-800">{{ editing ? 'Editar' : 'Nova' }} empresa conveniada</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="text-sm font-semibold text-gray-600">Razão social / nome *</label>
                        <input v-model="form.name" type="text" class="mt-1 w-full rounded-md border-gray-300" required />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">CNPJ</label>
                        <input v-model="form.cnpj" type="text" class="mt-1 w-full rounded-md border-gray-300" placeholder="00.000.000/0000-00" />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Contato</label>
                        <input v-model="form.contact" type="text" class="mt-1 w-full rounded-md border-gray-300" />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">E-mail</label>
                        <input v-model="form.email" type="email" class="mt-1 w-full rounded-md border-gray-300" />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Telefone</label>
                        <input v-model="form.phone" type="text" class="mt-1 w-full rounded-md border-gray-300" />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Ciclo de faturamento *</label>
                        <select v-model="form.billing_cycle" class="mt-1 w-full rounded-md border-gray-300">
                            <option value="semanal">Semanal</option>
                            <option value="quinzenal">Quinzenal</option>
                            <option value="mensal">Mensal</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Limite de crédito (R$, opcional)</label>
                        <input v-model.number="form.credit_limit" type="number" step="0.01" min="0" class="mt-1 w-full rounded-md border-gray-300" />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Desconto convênio (%)</label>
                        <input v-model.number="form.discount_percent" type="number" step="0.01" min="0" max="100" class="mt-1 w-full rounded-md border-gray-300" />
                    </div>
                    <label v-if="editing" class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <input v-model="form.active" type="checkbox" class="rounded" /> Empresa ativa
                    </label>
                </div>

                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" class="rounded-lg border px-5 py-2 font-semibold text-gray-600 hover:bg-gray-100" @click="show = false">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-sky-600 px-6 py-2 font-bold text-white hover:bg-sky-700 disabled:opacity-50">Salvar</button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
