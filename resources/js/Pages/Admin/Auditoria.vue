<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    logs: Object, // paginator
    filtros: Object,
    entidades: Array,
    acoes: Array,
});

const entidade = ref(props.filtros.entidade);
const acao = ref(props.filtros.acao);

function filtrar() {
    router.get(route('admin.auditoria'), { entidade: entidade.value, acao: acao.value }, { preserveState: true });
}

const dth = (iso) => new Date(iso).toLocaleString('pt-BR');
const fmt = (obj) => (obj ? JSON.stringify(obj, null, 0).slice(0, 300) : '—');
</script>

<template>
    <Head title="Auditoria" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold text-gray-800">Trilha de auditoria</h2>
        </template>

        <div class="mx-auto max-w-screen-2xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <form class="flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow" @submit.prevent="filtrar">
                <div>
                    <label class="text-xs font-semibold uppercase text-gray-500">Entidade</label>
                    <select v-model="entidade" class="mt-1 block rounded-md border-gray-300 text-sm">
                        <option value="">todas</option>
                        <option v-for="e in entidades" :key="e" :value="e">{{ e }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase text-gray-500">Ação</label>
                    <select v-model="acao" class="mt-1 block rounded-md border-gray-300 text-sm">
                        <option value="">todas</option>
                        <option v-for="a in acoes" :key="a" :value="a">{{ a }}</option>
                    </select>
                </div>
                <button class="rounded-lg bg-sky-600 px-5 py-2 text-sm font-semibold text-white hover:bg-sky-700">Filtrar</button>
            </form>

            <div class="overflow-x-auto rounded-xl bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-slate-800 text-left uppercase text-white">
                        <tr>
                            <th class="px-3 py-3">Quando</th>
                            <th class="px-3 py-3">Usuário</th>
                            <th class="px-3 py-3">Ação</th>
                            <th class="px-3 py-3">Entidade</th>
                            <th class="px-3 py-3">Antes</th>
                            <th class="px-3 py-3">Depois</th>
                            <th class="px-3 py-3">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="log in logs.data" :key="log.id">
                            <td class="whitespace-nowrap px-3 py-2">{{ dth(log.created_at) }}</td>
                            <td class="px-3 py-2 font-semibold">{{ log.user?.name || 'sistema' }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono">{{ log.action }}</span>
                            </td>
                            <td class="px-3 py-2">{{ log.entity }}<span v-if="log.entity_id" class="text-gray-400">#{{ log.entity_id }}</span></td>
                            <td class="max-w-xs truncate px-3 py-2 font-mono text-[10px] text-gray-500" :title="fmt(log.old_values)">{{ fmt(log.old_values) }}</td>
                            <td class="max-w-xs truncate px-3 py-2 font-mono text-[10px] text-gray-500" :title="fmt(log.new_values)">{{ fmt(log.new_values) }}</td>
                            <td class="px-3 py-2 text-gray-400">{{ log.ip }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginação -->
            <div class="flex flex-wrap gap-1">
                <template v-for="(link, i) in logs.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded px-3 py-1.5 text-sm"
                        :class="link.active ? 'bg-sky-600 font-bold text-white' : 'bg-white text-gray-600 hover:bg-gray-100'"
                        v-html="link.label"
                    />
                    <span v-else class="rounded px-3 py-1.5 text-sm text-gray-300" v-html="link.label" />
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
