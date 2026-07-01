<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ entryTypes: Array });

const show = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    is_paid: false,
    charge_moment: 'saida',
    vessel_selection: 'none',
    max_stay_minutes: null,
    requires_visitor_info: false,
    active: true,
});

function novo() {
    editing.value = null;
    form.reset();
    show.value = true;
}

function editar(t) {
    editing.value = t;
    form.name = t.name;
    form.is_paid = t.is_paid;
    form.charge_moment = t.charge_moment || 'saida';
    form.vessel_selection = t.vessel_selection || 'none';
    form.max_stay_minutes = t.max_stay_minutes;
    form.requires_visitor_info = t.requires_visitor_info;
    form.active = t.active;
    show.value = true;
}

function salvar() {
    const opts = { preserveScroll: true, onSuccess: () => (show.value = false) };
    if (editing.value) {
        form.put(route('admin.tipos-entrada.update', editing.value.id), opts);
    } else {
        form.post(route('admin.tipos-entrada.store'), opts);
    }
}

function inativar(t) {
    if (confirm(`Inativar o tipo "${t.name}"?`)) {
        router.delete(route('admin.tipos-entrada.destroy', t.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Tipos de entrada" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800">Tipos de entrada</h2>
                <button class="rounded-lg bg-sky-600 px-5 py-2 font-semibold text-white hover:bg-sky-700" @click="novo">+ Novo tipo</button>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6">
            <div class="overflow-hidden rounded-xl bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-slate-800 text-left text-xs uppercase text-white">
                        <tr>
                            <th class="px-4 py-3">Nome</th>
                            <th class="px-4 py-3">Cobrança</th>
                            <th class="px-4 py-3">Momento</th>
                            <th class="px-4 py-3">Limite de permanência</th>
                            <th class="px-4 py-3">Exige visitante</th>
                            <th class="px-4 py-3">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="t in entryTypes" :key="t.id">
                            <td class="px-4 py-3 font-semibold">{{ t.name }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-0.5 text-xs font-bold" :class="t.is_paid ? 'bg-orange-100 text-orange-800' : 'bg-emerald-100 text-emerald-800'">
                                    {{ t.is_paid ? 'Pago' : 'Isento' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ t.is_paid ? (t.charge_moment === 'entrada' ? 'Na entrada' : 'Na saída') : '—' }}</td>
                            <td class="px-4 py-3">{{ t.max_stay_minutes ? t.max_stay_minutes + ' min' : '—' }}</td>
                            <td class="px-4 py-3">{{ t.requires_visitor_info ? 'Sim' : 'Não' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-0.5 text-xs font-bold" :class="t.active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500'">
                                    {{ t.active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button class="me-3 font-semibold text-sky-600 hover:underline" @click="editar(t)">Editar</button>
                                <button v-if="t.active" class="font-semibold text-red-600 hover:underline" @click="inativar(t)">Inativar</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="show" max-width="lg" @close="show = false">
            <form class="space-y-4 p-6" @submit.prevent="salvar">
                <h2 class="text-lg font-bold text-gray-800">{{ editing ? 'Editar' : 'Novo' }} tipo de entrada</h2>

                <div>
                    <label class="text-sm font-semibold text-gray-600">Nome *</label>
                    <input v-model="form.name" type="text" class="mt-1 w-full rounded-md border-gray-300" required />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>

                <div class="flex flex-wrap gap-6">
                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <input v-model="form.is_paid" type="checkbox" class="rounded" /> Tipo pago
                    </label>
                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <input v-model="form.requires_visitor_info" type="checkbox" class="rounded" /> Exige identificação do visitante
                    </label>
                    <label v-if="editing" class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <input v-model="form.active" type="checkbox" class="rounded" /> Ativo
                    </label>
                </div>

                <div v-if="form.is_paid">
                    <label class="text-sm font-semibold text-gray-600">Momento da cobrança *</label>
                    <select v-model="form.charge_moment" class="mt-1 w-full rounded-md border-gray-300">
                        <option value="entrada">Na entrada (obrigatório antes de liberar — ex.: balsa)</option>
                        <option value="saida">Na saída (padrão — ex.: retirada de mercadoria)</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-semibold text-gray-600">Escolha de balsa/embarcação</label>
                    <select v-model="form.vessel_selection" class="mt-1 w-full rounded-md border-gray-300">
                        <option value="none">Não se aplica</option>
                        <option value="optional">Opcional (ex.: retirar mercadoria — informar a balsa se souber)</option>
                        <option value="required">Obrigatória (ex.: atravessar/embarcar na balsa)</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-semibold text-gray-600">Limite de permanência (minutos — alerta de visita vencida)</label>
                    <input v-model.number="form.max_stay_minutes" type="number" min="1" class="mt-1 w-full rounded-md border-gray-300" placeholder="vazio = sem limite" />
                </div>

                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" class="rounded-lg border px-5 py-2 font-semibold text-gray-600 hover:bg-gray-100" @click="show = false">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-sky-600 px-6 py-2 font-bold text-white hover:bg-sky-700 disabled:opacity-50">Salvar</button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
