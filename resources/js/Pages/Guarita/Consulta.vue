<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PhotoThumb from '@/Components/PhotoThumb.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    placa: String,
    vehicle: Object,
    history: Array,
});

const placa = ref(props.placa || '');

function pesquisar() {
    router.get(route('guarita.consulta'), { placa: placa.value });
}

const brl = (v) => (v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const dt = (iso) => (iso ? new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—');
const tempo = (min) => (min == null ? '—' : `${Math.floor(min / 60)}h${String(min % 60).padStart(2, '0')}`);
</script>

<template>
    <Head title="Consulta de placa" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-bold text-gray-800">Consulta de placa</h2>
                <form class="flex gap-2" @submit.prevent="pesquisar">
                    <input
                        v-model="placa"
                        type="text"
                        maxlength="8"
                        placeholder="ABC1D23"
                        class="rounded-md border-gray-300 font-mono text-lg font-bold uppercase tracking-widest"
                    />
                    <button class="rounded-lg bg-sky-600 px-5 py-2 font-semibold text-white hover:bg-sky-700">Consultar</button>
                </form>
            </div>
        </template>

        <div class="mx-auto max-w-screen-2xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <div v-if="placa && !vehicle" class="rounded-lg border-2 border-dashed border-gray-300 p-10 text-center text-gray-400">
                Nenhum veículo encontrado com a placa <strong class="font-mono">{{ placa }}</strong>.
            </div>

            <div v-if="vehicle" class="rounded-xl bg-white p-6 shadow">
                <div class="flex flex-wrap items-center gap-4">
                    <span class="font-mono text-3xl font-black tracking-widest">{{ vehicle.plate }}</span>
                    <span class="text-gray-600">
                        {{ [vehicle.category?.name, vehicle.color, vehicle.brand, vehicle.model].filter(Boolean).join(' · ') || 'sem dados' }}
                    </span>
                    <span v-if="vehicle.owner_name" class="text-gray-500">Proprietário: {{ vehicle.owner_name }}</span>
                    <span
                        v-if="vehicle.active_authorization"
                        class="rounded bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-800"
                    >
                        {{ vehicle.active_authorization.type === 'funcionario'
                            ? `FUNCIONÁRIO: ${vehicle.active_authorization.employee_name}`
                            : `CONVÊNIO: ${vehicle.active_authorization.company?.name}` }}
                    </span>
                </div>
            </div>

            <div v-if="history.length" class="overflow-x-auto rounded-xl bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-slate-800 text-left text-xs uppercase text-white">
                        <tr>
                            <th class="px-4 py-3">Entrada</th>
                            <th class="px-4 py-3">Saída</th>
                            <th class="px-4 py-3">Permanência</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Empresa</th>
                            <th class="px-4 py-3">Valor</th>
                            <th class="px-4 py-3">Pago</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Operador</th>
                            <th class="px-4 py-3">Imagens</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="h in history" :key="h.id">
                            <td class="px-4 py-3">{{ dt(h.entered_at) }} <span v-if="h.manual_entry" title="Manual">✍</span></td>
                            <td class="px-4 py-3">{{ dt(h.exited_at) }}</td>
                            <td class="px-4 py-3">{{ tempo(h.stay_minutes) }}</td>
                            <td class="px-4 py-3">{{ h.entry_type }}</td>
                            <td class="px-4 py-3">{{ h.company || '—' }}</td>
                            <td class="px-4 py-3">{{ h.amount_due > 0 ? brl(h.amount_due - h.discount) : '—' }}</td>
                            <td class="px-4 py-3">{{ h.paid > 0 ? brl(h.paid) : '—' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded px-2 py-0.5 text-xs font-bold"
                                    :class="{
                                        'bg-sky-100 text-sky-800': h.status === 'no_patio',
                                        'bg-emerald-100 text-emerald-800': h.status === 'finalizado',
                                        'bg-red-100 text-red-800': h.status === 'cancelado',
                                    }"
                                >
                                    {{ h.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs">{{ h.operator_in || h.operator_out || '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <PhotoThumb :src="h.entry_photo_url" caption="Entrada" />
                                    <PhotoThumb :src="h.exit_photo_url" caption="Saída" />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
