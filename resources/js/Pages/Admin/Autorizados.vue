<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ authorized: Array, companies: Array, categories: Array });

const show = ref(false);

const form = useForm({
    plate: '',
    vehicle_category_id: null,
    brand: '',
    model: '',
    color: '',
    type: 'funcionario',
    employee_name: '',
    company_id: null,
    valid_until: null,
});

function salvar() {
    form.transform((data) => ({ ...data, plate: data.plate.toUpperCase().replace(/[^A-Z0-9]/g, '') })).post(
        route('admin.autorizados.store'),
        { preserveScroll: true, onSuccess: () => { show.value = false; form.reset(); } },
    );
}

function revogar(a) {
    if (confirm(`Revogar a autorização do veículo ${a.vehicle?.plate}?`)) {
        router.delete(route('admin.autorizados.destroy', a.id), { preserveScroll: true });
    }
}

const dt = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : null);
</script>

<template>
    <Head title="Veículos autorizados" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800">Veículos autorizados (liberação automática)</h2>
                <button class="rounded-lg bg-sky-600 px-5 py-2 font-semibold text-white hover:bg-sky-700" @click="show = true">+ Autorizar veículo</button>
            </div>
        </template>

        <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6">
            <div class="overflow-x-auto rounded-xl bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-slate-800 text-left text-xs uppercase text-white">
                        <tr>
                            <th class="px-4 py-3">Placa</th>
                            <th class="px-4 py-3">Veículo</th>
                            <th class="px-4 py-3">Vínculo</th>
                            <th class="px-4 py-3">Funcionário / Empresa</th>
                            <th class="px-4 py-3">Validade</th>
                            <th class="px-4 py-3">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="a in authorized" :key="a.id" :class="{ 'opacity-50': !a.active }">
                            <td class="px-4 py-3 font-mono font-black">{{ a.vehicle?.plate }}</td>
                            <td class="px-4 py-3">{{ [a.vehicle?.category?.name, a.vehicle?.color, a.vehicle?.model].filter(Boolean).join(' · ') || '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-0.5 text-xs font-bold" :class="a.type === 'funcionario' ? 'bg-sky-100 text-sky-800' : 'bg-purple-100 text-purple-800'">
                                    {{ a.type === 'funcionario' ? 'Funcionário' : 'Empresa' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ a.employee_name || a.company?.name || '—' }}</td>
                            <td class="px-4 py-3">{{ dt(a.valid_until) || 'sem validade' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-0.5 text-xs font-bold" :class="a.active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500'">
                                    {{ a.active ? 'Ativo' : 'Revogado' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button v-if="a.active" class="font-semibold text-red-600 hover:underline" @click="revogar(a)">Revogar</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="show" max-width="xl" @close="show = false">
            <form class="space-y-4 p-6" @submit.prevent="salvar">
                <h2 class="text-lg font-bold text-gray-800">Autorizar veículo</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Placa *</label>
                        <input v-model="form.plate" type="text" maxlength="8" class="mt-1 w-full rounded-md border-gray-300 font-mono font-bold uppercase" required />
                        <p v-if="form.errors.plate" class="mt-1 text-xs text-red-600">{{ form.errors.plate }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Categoria</label>
                        <select v-model="form.vehicle_category_id" class="mt-1 w-full rounded-md border-gray-300">
                            <option :value="null">—</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Tipo de vínculo *</label>
                        <select v-model="form.type" class="mt-1 w-full rounded-md border-gray-300">
                            <option value="funcionario">Funcionário (entrada isenta automática)</option>
                            <option value="empresa">Empresa conveniada (faturamento autorizado)</option>
                        </select>
                    </div>
                    <div v-if="form.type === 'funcionario'">
                        <label class="text-sm font-semibold text-gray-600">Nome do funcionário *</label>
                        <input v-model="form.employee_name" type="text" class="mt-1 w-full rounded-md border-gray-300" required />
                    </div>
                    <div v-else>
                        <label class="text-sm font-semibold text-gray-600">Empresa *</label>
                        <select v-model="form.company_id" class="mt-1 w-full rounded-md border-gray-300" required>
                            <option :value="null" disabled>Selecione…</option>
                            <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Válido até (opcional)</label>
                        <input v-model="form.valid_until" type="date" class="mt-1 w-full rounded-md border-gray-300" />
                    </div>
                </div>

                <details class="rounded-lg border border-gray-200 p-3">
                    <summary class="cursor-pointer text-sm font-semibold text-gray-600">Dados do veículo (cor, marca, modelo)</summary>
                    <div class="mt-3 grid gap-3 sm:grid-cols-3">
                        <input v-model="form.color" type="text" placeholder="Cor" class="rounded-md border-gray-300 text-sm" />
                        <input v-model="form.brand" type="text" placeholder="Marca" class="rounded-md border-gray-300 text-sm" />
                        <input v-model="form.model" type="text" placeholder="Modelo" class="rounded-md border-gray-300 text-sm" />
                    </div>
                    <p class="mt-2 text-xs text-gray-400">Preencher cor/modelo habilita o alerta de divergência (placa clonada).</p>
                </details>

                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" class="rounded-lg border px-5 py-2 font-semibold text-gray-600 hover:bg-gray-100" @click="show = false">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-sky-600 px-6 py-2 font-bold text-white hover:bg-sky-700 disabled:opacity-50">Autorizar</button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
