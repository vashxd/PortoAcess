<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ users: Array, roles: Array });

const show = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    role: 'operador',
    active: true,
});

function novo() {
    editing.value = null;
    form.reset();
    show.value = true;
}

function editar(u) {
    editing.value = u;
    form.name = u.name;
    form.email = u.email;
    form.password = '';
    form.role = u.role;
    form.active = u.active;
    show.value = true;
}

function salvar() {
    const opts = { preserveScroll: true, onSuccess: () => (show.value = false) };
    if (editing.value) {
        form.put(route('admin.usuarios.update', editing.value.id), opts);
    } else {
        form.post(route('admin.usuarios.store'), opts);
    }
}

const roleLabel = (roles, value) => roles.find((r) => r.value === value)?.label ?? value;
</script>

<template>
    <Head title="Usuários" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800">Usuários do sistema</h2>
                <button class="rounded-lg bg-sky-600 px-5 py-2 font-semibold text-white hover:bg-sky-700" @click="novo">+ Novo usuário</button>
            </div>
        </template>

        <div class="mx-auto max-w-4xl px-4 py-6 sm:px-6">
            <div class="overflow-hidden rounded-xl bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-slate-800 text-left text-xs uppercase text-white">
                        <tr>
                            <th class="px-4 py-3">Nome</th>
                            <th class="px-4 py-3">E-mail</th>
                            <th class="px-4 py-3">Perfil</th>
                            <th class="px-4 py-3">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="u in users" :key="u.id">
                            <td class="px-4 py-3 font-semibold">{{ u.name }}</td>
                            <td class="px-4 py-3">{{ u.email }}</td>
                            <td class="px-4 py-3">{{ roleLabel(roles, u.role) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-0.5 text-xs font-bold" :class="u.active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500'">
                                    {{ u.active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button class="font-semibold text-sky-600 hover:underline" @click="editar(u)">Editar</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="show" max-width="lg" @close="show = false">
            <form class="space-y-4 p-6" @submit.prevent="salvar">
                <h2 class="text-lg font-bold text-gray-800">{{ editing ? 'Editar' : 'Novo' }} usuário</h2>

                <div>
                    <label class="text-sm font-semibold text-gray-600">Nome *</label>
                    <input v-model="form.name" type="text" class="mt-1 w-full rounded-md border-gray-300" required />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="text-sm font-semibold text-gray-600">E-mail *</label>
                    <input v-model="form.email" type="email" class="mt-1 w-full rounded-md border-gray-300" required />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label class="text-sm font-semibold text-gray-600">Senha {{ editing ? '(deixe vazio para manter)' : '*' }}</label>
                    <input v-model="form.password" type="password" class="mt-1 w-full rounded-md border-gray-300" :required="!editing" autocomplete="new-password" />
                    <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                </div>
                <div>
                    <label class="text-sm font-semibold text-gray-600">Perfil *</label>
                    <select v-model="form.role" class="mt-1 w-full rounded-md border-gray-300">
                        <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                    </select>
                </div>
                <label v-if="editing" class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                    <input v-model="form.active" type="checkbox" class="rounded" /> Usuário ativo
                </label>

                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" class="rounded-lg border px-5 py-2 font-semibold text-gray-600 hover:bg-gray-100" @click="show = false">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-sky-600 px-6 py-2 font-bold text-white hover:bg-sky-700 disabled:opacity-50">Salvar</button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
