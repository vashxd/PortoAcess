<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ vessels: Array, vesselTypes: Array });

const DIAS = [
    { v: 0, label: 'Dom' },
    { v: 1, label: 'Seg' },
    { v: 2, label: 'Ter' },
    { v: 3, label: 'Qua' },
    { v: 4, label: 'Qui' },
    { v: 5, label: 'Sex' },
    { v: 6, label: 'Sáb' },
];

const typeLabel = (v) => props.vesselTypes.find((t) => t.value === v)?.label || v;

/* ---------- Embarcação ---------- */
const show = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    type: 'balsa',
    registration: '',
    operator: '',
    default_destination: '',
    capacity_vehicles: null,
    notes: '',
    active: true,
});

function novo() {
    editing.value = null;
    form.reset();
    show.value = true;
}

function editar(v) {
    editing.value = v;
    form.name = v.name;
    form.type = v.type;
    form.registration = v.registration || '';
    form.operator = v.operator || '';
    form.default_destination = v.default_destination || '';
    form.capacity_vehicles = v.capacity_vehicles;
    form.notes = v.notes || '';
    form.active = v.active;
    show.value = true;
}

function salvar() {
    const opts = { preserveScroll: true, onSuccess: () => (show.value = false) };
    if (editing.value) {
        form.put(route('admin.embarcacoes.update', editing.value.id), opts);
    } else {
        form.post(route('admin.embarcacoes.store'), opts);
    }
}

function inativar(v) {
    if (confirm(`Inativar a embarcação "${v.name}"?`)) {
        router.delete(route('admin.embarcacoes.destroy', v.id), { preserveScroll: true });
    }
}

/* ---------- Grade de horários ---------- */
const showGrade = ref(false);
const gradeVessel = ref(null);

const scheduleForm = useForm({
    vessel_id: null,
    days_of_week: [],
    departure_time: '08:00',
    destination: '',
});

const gradeSchedules = computed(() =>
    gradeVessel.value ? props.vessels.find((v) => v.id === gradeVessel.value.id)?.schedules || [] : [],
);

function abrirGrade(v) {
    gradeVessel.value = v;
    scheduleForm.reset();
    scheduleForm.vessel_id = v.id;
    scheduleForm.destination = v.default_destination || '';
    showGrade.value = true;
}

function toggleDia(d) {
    const i = scheduleForm.days_of_week.indexOf(d);
    if (i >= 0) scheduleForm.days_of_week.splice(i, 1);
    else scheduleForm.days_of_week.push(d);
}

function addHorario() {
    scheduleForm.post(route('admin.horarios.store'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            scheduleForm.days_of_week = [];
            scheduleForm.departure_time = '08:00';
        },
    });
}

function removerHorario(s) {
    router.delete(route('admin.horarios.destroy', s.id), { preserveScroll: true, preserveState: true });
}

const diasLabel = (dias) =>
    (dias || [])
        .slice()
        .sort((a, b) => a - b)
        .map((d) => DIAS.find((x) => x.v === d)?.label)
        .join(', ');
</script>

<template>
    <Head title="Balsas & Embarcações" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800">Balsas &amp; Embarcações</h2>
                <div class="flex gap-2">
                    <a :href="route('admin.viagens.index')" class="rounded-lg border border-cyan-600 px-4 py-2 font-semibold text-cyan-700 hover:bg-cyan-50">
                        Ver viagens
                    </a>
                    <button class="rounded-lg bg-sky-600 px-5 py-2 font-semibold text-white hover:bg-sky-700" @click="novo">+ Nova embarcação</button>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6">
            <div class="overflow-hidden rounded-xl bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-slate-800 text-left text-xs uppercase text-white">
                        <tr>
                            <th class="px-4 py-3">Nome</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Operadora</th>
                            <th class="px-4 py-3">Destino padrão</th>
                            <th class="px-4 py-3">Grade</th>
                            <th class="px-4 py-3">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="v in vessels" :key="v.id">
                            <td class="px-4 py-3 font-semibold">
                                {{ v.name }}
                                <span v-if="v.registration" class="block text-xs font-normal text-gray-400">{{ v.registration }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded bg-cyan-100 px-2 py-0.5 text-xs font-bold text-cyan-800">{{ typeLabel(v.type) }}</span>
                            </td>
                            <td class="px-4 py-3">{{ v.operator || '—' }}</td>
                            <td class="px-4 py-3">{{ v.default_destination || '—' }}</td>
                            <td class="px-4 py-3">
                                <button class="font-semibold text-cyan-700 hover:underline" @click="abrirGrade(v)">
                                    {{ (v.schedules || []).length }} horário(s)
                                </button>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-0.5 text-xs font-bold" :class="v.active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500'">
                                    {{ v.active ? 'Ativa' : 'Inativa' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button class="me-3 font-semibold text-sky-600 hover:underline" @click="editar(v)">Editar</button>
                                <button v-if="v.active" class="font-semibold text-red-600 hover:underline" @click="inativar(v)">Inativar</button>
                            </td>
                        </tr>
                        <tr v-if="!vessels.length">
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400">Nenhuma embarcação cadastrada.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal embarcação -->
        <Modal :show="show" max-width="lg" @close="show = false">
            <form class="space-y-4 p-6" @submit.prevent="salvar">
                <h2 class="text-lg font-bold text-gray-800">{{ editing ? 'Editar' : 'Nova' }} embarcação</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Nome *</label>
                        <input v-model="form.name" type="text" class="mt-1 w-full rounded-md border-gray-300" required />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Tipo *</label>
                        <select v-model="form.type" class="mt-1 w-full rounded-md border-gray-300">
                            <option v-for="t in vesselTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Identificação / prefixo</label>
                        <input v-model="form.registration" type="text" class="mt-1 w-full rounded-md border-gray-300" />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Empresa operadora</label>
                        <input v-model="form.operator" type="text" class="mt-1 w-full rounded-md border-gray-300" />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Destino padrão</label>
                        <input v-model="form.default_destination" type="text" class="mt-1 w-full rounded-md border-gray-300" placeholder="ex.: Careiro da Várzea" />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Capacidade (veículos)</label>
                        <input v-model.number="form.capacity_vehicles" type="number" min="1" class="mt-1 w-full rounded-md border-gray-300" placeholder="informativo" />
                    </div>
                </div>

                <div>
                    <label class="text-sm font-semibold text-gray-600">Observações</label>
                    <textarea v-model="form.notes" rows="2" class="mt-1 w-full rounded-md border-gray-300 text-sm"></textarea>
                </div>

                <label v-if="editing" class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                    <input v-model="form.active" type="checkbox" class="rounded" /> Ativa
                </label>

                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" class="rounded-lg border px-5 py-2 font-semibold text-gray-600 hover:bg-gray-100" @click="show = false">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-sky-600 px-6 py-2 font-bold text-white hover:bg-sky-700 disabled:opacity-50">Salvar</button>
                </div>
            </form>
        </Modal>

        <!-- Modal grade de horários -->
        <Modal :show="showGrade" max-width="xl" @close="showGrade = false">
            <div class="space-y-4 p-6">
                <h2 class="text-lg font-bold text-gray-800">Grade de horários — {{ gradeVessel?.name }}</h2>
                <p class="text-xs text-gray-500">A grade gera automaticamente as viagens de cada dia. Marque os dias da semana e o horário de partida.</p>

                <div v-if="gradeSchedules.length" class="overflow-hidden rounded-lg border">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-slate-100 text-left text-xs uppercase text-gray-600">
                            <tr>
                                <th class="px-3 py-2">Dias</th>
                                <th class="px-3 py-2">Partida</th>
                                <th class="px-3 py-2">Destino</th>
                                <th class="px-3 py-2 text-right">—</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="s in gradeSchedules" :key="s.id">
                                <td class="px-3 py-2">{{ diasLabel(s.days_of_week) }}</td>
                                <td class="px-3 py-2 font-mono font-bold">{{ s.departure_time }}</td>
                                <td class="px-3 py-2">{{ s.destination || '—' }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button class="font-semibold text-red-600 hover:underline" @click="removerHorario(s)">remover</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="rounded-lg border-2 border-dashed border-gray-200 py-6 text-center text-sm text-gray-400">Nenhum horário na grade.</p>

                <form class="space-y-3 rounded-lg bg-slate-50 p-4" @submit.prevent="addHorario">
                    <h3 class="text-sm font-bold text-gray-700">Adicionar horário</h3>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="d in DIAS" :key="d.v" class="cursor-pointer">
                            <input type="checkbox" class="peer sr-only" :checked="scheduleForm.days_of_week.includes(d.v)" @change="toggleDia(d.v)" />
                            <span class="inline-block rounded-md border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-600 peer-checked:border-cyan-600 peer-checked:bg-cyan-600 peer-checked:text-white">
                                {{ d.label }}
                            </span>
                        </label>
                    </div>
                    <p v-if="scheduleForm.errors.days_of_week" class="text-xs text-red-600">{{ scheduleForm.errors.days_of_week }}</p>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Horário de partida *</label>
                            <input v-model="scheduleForm.departure_time" type="time" class="mt-1 w-full rounded-md border-gray-300" required />
                            <p v-if="scheduleForm.errors.departure_time" class="mt-1 text-xs text-red-600">{{ scheduleForm.errors.departure_time }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold text-gray-600">Destino (opcional)</label>
                            <input v-model="scheduleForm.destination" type="text" class="mt-1 w-full rounded-md border-gray-300" />
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" :disabled="scheduleForm.processing || !scheduleForm.days_of_week.length" class="rounded-lg bg-cyan-600 px-5 py-2 font-bold text-white hover:bg-cyan-700 disabled:opacity-50">
                            + Adicionar à grade
                        </button>
                    </div>
                </form>

                <div class="flex justify-end border-t pt-4">
                    <button type="button" class="rounded-lg border px-5 py-2 font-semibold text-gray-600 hover:bg-gray-100" @click="showGrade = false">Fechar</button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
