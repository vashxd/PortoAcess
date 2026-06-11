<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ categories: Array });

const form = useForm({ name: '' });
const editing = ref(null);
const editForm = useForm({ name: '', active: true });

function criar() {
    form.post(route('admin.categorias.store'), { preserveScroll: true, onSuccess: () => form.reset() });
}

function editar(cat) {
    editing.value = cat.id;
    editForm.name = cat.name;
    editForm.active = cat.active;
}

function salvar(cat) {
    editForm.put(route('admin.categorias.update', cat.id), {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });
}

function excluir(cat) {
    if (confirm(`Excluir/inativar a categoria "${cat.name}"?`)) {
        router.delete(route('admin.categorias.destroy', cat.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Categorias de veículo" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold text-gray-800">Categorias de veículo</h2>
        </template>

        <div class="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6">
            <form class="flex gap-2 rounded-xl bg-white p-4 shadow" @submit.prevent="criar">
                <input v-model="form.name" type="text" placeholder="Nova categoria (ex.: Caminhão truck)" class="flex-1 rounded-md border-gray-300" required />
                <button :disabled="form.processing" class="rounded-lg bg-sky-600 px-5 py-2 font-semibold text-white hover:bg-sky-700 disabled:opacity-50">
                    Adicionar
                </button>
            </form>
            <p v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</p>

            <div class="overflow-hidden rounded-xl bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-slate-800 text-left text-xs uppercase text-white">
                        <tr>
                            <th class="px-4 py-3">Nome</th>
                            <th class="px-4 py-3">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="cat in categories" :key="cat.id">
                            <td class="px-4 py-3">
                                <template v-if="editing === cat.id">
                                    <input v-model="editForm.name" type="text" class="w-full rounded-md border-gray-300 text-sm" />
                                </template>
                                <template v-else>{{ cat.name }}</template>
                            </td>
                            <td class="px-4 py-3">
                                <template v-if="editing === cat.id">
                                    <label class="flex items-center gap-1 text-sm">
                                        <input v-model="editForm.active" type="checkbox" class="rounded" /> ativa
                                    </label>
                                </template>
                                <span v-else class="rounded px-2 py-0.5 text-xs font-bold" :class="cat.active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500'">
                                    {{ cat.active ? 'Ativa' : 'Inativa' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <template v-if="editing === cat.id">
                                    <button class="me-2 font-semibold text-emerald-600 hover:underline" @click="salvar(cat)">Salvar</button>
                                    <button class="text-gray-500 hover:underline" @click="editing = null">Cancelar</button>
                                </template>
                                <template v-else>
                                    <button class="me-3 font-semibold text-sky-600 hover:underline" @click="editar(cat)">Editar</button>
                                    <button class="font-semibold text-red-600 hover:underline" @click="excluir(cat)">Excluir</button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
