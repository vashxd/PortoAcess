<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    tipo: String,
    de: String,
    ate: String,
    rows: Array,
});

const tipos = [
    { value: 'movimento', label: 'Movimento diário' },
    { value: 'receita', label: 'Receita por forma de pagamento' },
    { value: 'empresas', label: 'Acessos por empresa' },
    { value: 'manuais', label: 'Registros manuais / cancelados' },
    { value: 'permanencia', label: 'Permanência média' },
    { value: 'isencoes', label: 'Isenções concedidas' },
];

const tipo = ref(props.tipo);
const de = ref(props.de);
const ate = ref(props.ate);

function gerar() {
    router.get(route('admin.relatorios'), { tipo: tipo.value, de: de.value, ate: ate.value }, { preserveState: true });
}

const exportUrl = computed(
    () => route('admin.relatorios.export', { tipo: tipo.value, de: de.value, ate: ate.value }),
);

const headers = computed(() => (props.rows.length ? Object.keys(props.rows[0]) : []));
</script>

<template>
    <Head title="Relatórios" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold text-gray-800">Relatórios gerenciais</h2>
        </template>

        <div class="mx-auto max-w-screen-2xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <form class="flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow" @submit.prevent="gerar">
                <div>
                    <label class="text-xs font-semibold uppercase text-gray-500">Relatório</label>
                    <select v-model="tipo" class="mt-1 block rounded-md border-gray-300 text-sm">
                        <option v-for="t in tipos" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase text-gray-500">De</label>
                    <input v-model="de" type="date" class="mt-1 block rounded-md border-gray-300 text-sm" />
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase text-gray-500">Até</label>
                    <input v-model="ate" type="date" class="mt-1 block rounded-md border-gray-300 text-sm" />
                </div>
                <button class="rounded-lg bg-sky-600 px-5 py-2 text-sm font-semibold text-white hover:bg-sky-700">Gerar</button>
                <a :href="exportUrl" class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    ⬇ Exportar CSV (Excel)
                </a>
            </form>

            <div class="overflow-x-auto rounded-xl bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-slate-800 text-left text-xs uppercase text-white">
                        <tr>
                            <th v-for="h in headers" :key="h" class="px-4 py-3">{{ h }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="(row, i) in rows" :key="i">
                            <td v-for="h in headers" :key="h" class="px-4 py-2.5">{{ row[h] }}</td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td :colspan="headers.length || 1" class="px-4 py-10 text-center text-gray-400">Sem dados no período selecionado.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
