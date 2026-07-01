<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ departures: Array, vessels: Array, filters: Object });

const page = usePage();
const isAdmin = computed(() => page.props.auth.user?.role === 'admin');

const statusCls = {
    agendada: 'bg-sky-100 text-sky-800',
    embarcando: 'bg-amber-100 text-amber-800',
    encerrada: 'bg-gray-100 text-gray-600',
    cancelada: 'bg-red-100 text-red-700',
};

const grouped = computed(() => {
    const map = {};
    for (const d of props.departures) {
        (map[d.departure_date] ??= []).push(d);
    }
    return Object.entries(map).map(([date, list]) => ({ date, list }));
});

const diaLabel = (iso) =>
    new Date(iso + 'T00:00:00').toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: '2-digit' });

/* Filtro de período */
const filtro = useForm({ from: props.filters.from, to: props.filters.to });
function aplicarFiltro() {
    router.get(route('admin.viagens.index'), { from: filtro.from, to: filtro.to }, { preserveState: true, preserveScroll: true });
}

/* Gerar a partir da grade */
function gerar() {
    router.post(route('admin.viagens.gerar'), { days: 14 }, { preserveScroll: true });
}

/* Viagem avulsa */
const showAvulsa = ref(false);
const avulsaForm = useForm({ vessel_id: null, departure_date: props.filters.from, departure_time: '08:00', destination: '', notes: '' });
function salvarAvulsa() {
    avulsaForm.post(route('admin.viagens.store'), { preserveScroll: true, onSuccess: () => (showAvulsa.value = false) });
}

/* Status */
function mudarStatus(d, status) {
    router.put(route('admin.viagens.update', d.id), { status, destination: d.destination }, { preserveScroll: true });
}
function cancelar(d) {
    if (confirm(`Cancelar a viagem de ${d.vessel_name} (${d.departure_time})?`)) {
        router.delete(route('admin.viagens.destroy', d.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Viagens das balsas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-bold text-gray-800">Viagens das balsas</h2>
                <div v-if="isAdmin" class="flex gap-2">
                    <button class="rounded-lg border border-gray-300 px-4 py-2 font-semibold text-gray-700 hover:bg-gray-100" @click="showAvulsa = true">+ Viagem avulsa</button>
                    <button class="rounded-lg bg-cyan-600 px-5 py-2 font-semibold text-white hover:bg-cyan-700" @click="gerar">Gerar da grade (14 dias)</button>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6">
            <!-- Filtro -->
            <div class="flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm">
                <div>
                    <label class="text-xs font-semibold text-gray-600">De</label>
                    <input v-model="filtro.from" type="date" class="mt-1 block rounded-md border-gray-300 text-sm" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Até</label>
                    <input v-model="filtro.to" type="date" class="mt-1 block rounded-md border-gray-300 text-sm" />
                </div>
                <button class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800" @click="aplicarFiltro">Filtrar</button>
            </div>

            <div v-if="!departures.length" class="rounded-xl border-2 border-dashed border-gray-300 p-10 text-center text-gray-400">
                Nenhuma viagem no período. {{ isAdmin ? 'Use “Gerar da grade” para criar as partidas a partir dos horários cadastrados.' : '' }}
            </div>

            <div v-for="grupo in grouped" :key="grupo.date" class="space-y-2">
                <h3 class="text-sm font-bold capitalize text-gray-500">{{ diaLabel(grupo.date) }}</h3>
                <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="d in grupo.list" :key="d.id">
                                <td class="px-4 py-3 font-mono text-lg font-black text-slate-800">{{ d.departure_time }}</td>
                                <td class="px-4 py-3">
                                    <span class="font-semibold">{{ d.vessel_name }}</span>
                                    <span v-if="d.destination" class="block text-xs text-gray-500">→ {{ d.destination }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-700">{{ d.vehicles }} veículo(s)</span>
                                    <span v-if="!d.generated" class="ms-1 rounded bg-purple-100 px-2 py-0.5 text-xs font-bold text-purple-700">avulsa</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded px-2 py-0.5 text-xs font-bold" :class="statusCls[d.status]">{{ d.status_label }}</span>
                                </td>
                                <td v-if="isAdmin" class="px-4 py-3 text-right text-xs">
                                    <button v-if="d.status === 'agendada'" class="me-2 font-semibold text-amber-700 hover:underline" @click="mudarStatus(d, 'embarcando')">iniciar embarque</button>
                                    <button v-if="d.status === 'embarcando'" class="me-2 font-semibold text-emerald-700 hover:underline" @click="mudarStatus(d, 'encerrada')">encerrar</button>
                                    <button v-if="d.status !== 'cancelada' && d.status !== 'encerrada'" class="font-semibold text-red-600 hover:underline" @click="cancelar(d)">cancelar</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal viagem avulsa -->
        <Modal :show="showAvulsa" max-width="lg" @close="showAvulsa = false">
            <form class="space-y-4 p-6" @submit.prevent="salvarAvulsa">
                <h2 class="text-lg font-bold text-gray-800">Viagem avulsa</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="text-sm font-semibold text-gray-600">Embarcação *</label>
                        <select v-model="avulsaForm.vessel_id" class="mt-1 w-full rounded-md border-gray-300" required>
                            <option :value="null" disabled>Selecione…</option>
                            <option v-for="v in vessels" :key="v.id" :value="v.id">{{ v.name }}</option>
                        </select>
                        <p v-if="avulsaForm.errors.vessel_id" class="mt-1 text-xs text-red-600">{{ avulsaForm.errors.vessel_id }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Data *</label>
                        <input v-model="avulsaForm.departure_date" type="date" class="mt-1 w-full rounded-md border-gray-300" required />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Horário *</label>
                        <input v-model="avulsaForm.departure_time" type="time" class="mt-1 w-full rounded-md border-gray-300" required />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-sm font-semibold text-gray-600">Destino</label>
                        <input v-model="avulsaForm.destination" type="text" class="mt-1 w-full rounded-md border-gray-300" />
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" class="rounded-lg border px-5 py-2 font-semibold text-gray-600 hover:bg-gray-100" @click="showAvulsa = false">Cancelar</button>
                    <button type="submit" :disabled="avulsaForm.processing" class="rounded-lg bg-cyan-600 px-6 py-2 font-bold text-white hover:bg-cyan-700 disabled:opacity-50">Salvar</button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
