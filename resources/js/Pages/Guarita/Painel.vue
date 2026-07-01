<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PaymentForm from '@/Components/Guarita/PaymentForm.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    entryTypes: Array,
    categories: Array,
    companies: Array,
    vessels: Array,
    departures: Array,
    priceMatrix: Object,
    pix: Object,
});

/* ------------------------------------------------------------------ */
/* Polling de eventos da câmera (tempo quase real — RNF01)            */
/* ------------------------------------------------------------------ */
const events = ref([]);
const patioCount = ref(0);
const overstays = ref([]);
const polling = ref(null);
const lastUpdate = ref(null);

async function poll() {
    try {
        const { data } = await axios.get(route('guarita.eventos'));
        events.value = data.events;
        patioCount.value = data.patio_count;
        overstays.value = data.overstays;
        lastUpdate.value = new Date();
    } catch {
        // câmera/servidor indisponível: o operador segue no modo contingência
    }
}

onMounted(() => {
    poll();
    polling.value = setInterval(poll, 4000);
});
onUnmounted(() => clearInterval(polling.value));

const brl = (v) => (v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const hora = (iso) => new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

/* ------------------------------------------------------------------ */
/* Entrada (evento da câmera ou manual/contingência)                   */
/* ------------------------------------------------------------------ */
const showEntrada = ref(false);
const entradaEvent = ref(null);

const entradaForm = useForm({
    plate: '',
    entry_type_id: null,
    vehicle_category_id: null,
    company_id: null,
    camera_event_id: null,
    vessel_id: null,
    vessel_departure_id: null,
    visitor_name: '',
    visitor_document: '',
    destination: '',
    brand: '',
    model: '',
    color: '',
    owner_name: '',
    manual_entry: false,
    exemption_reason: '',
    notes: '',
    billing_justification: '',
    payments: [],
});

const lookupInfo = ref(null);

function abrirEntrada(event = null) {
    entradaEvent.value = event;
    entradaForm.reset();
    lookupInfo.value = null;
    showIsencao.value = false;

    if (event) {
        entradaForm.plate = event.plate || '';
        entradaForm.camera_event_id = event.id;
        entradaForm.manual_entry = false;
        entradaForm.brand = event.vehicle?.brand || event.brand || '';
        entradaForm.model = event.vehicle?.model || event.model || '';
        entradaForm.color = event.vehicle?.color || event.color || '';
        entradaForm.owner_name = event.vehicle?.owner_name || '';
        entradaForm.vehicle_category_id = event.vehicle?.category_id || null;

        if (event.authorization?.type === 'funcionario') {
            const func = props.entryTypes.find((t) => t.name.toLowerCase().startsWith('funcion'));
            entradaForm.entry_type_id = func?.id ?? null;
        }
        if (event.authorization?.type === 'empresa') {
            entradaForm.company_id = event.authorization.company_id;
        }
    } else {
        entradaForm.manual_entry = true;
    }

    showEntrada.value = true;
}

async function lookupPlate() {
    if (!entradaForm.plate || entradaForm.plate.length < 6) return;
    try {
        const { data } = await axios.get(route('guarita.lookup'), { params: { plate: entradaForm.plate } });
        lookupInfo.value = data;
        if (data.vehicle) {
            entradaForm.vehicle_category_id = data.vehicle.vehicle_category_id ?? entradaForm.vehicle_category_id;
            entradaForm.brand = data.vehicle.brand || entradaForm.brand;
            entradaForm.model = data.vehicle.model || entradaForm.model;
            entradaForm.color = data.vehicle.color || entradaForm.color;
            entradaForm.owner_name = data.vehicle.owner_name || entradaForm.owner_name;
        }
        // Sinesp completa cor/marca/modelo quando não há cadastro local
        const s = data.sinesp;
        if (s && s.disponivel) {
            if (!entradaForm.brand && s.marca) entradaForm.brand = s.marca;
            if (!entradaForm.model && s.modelo) entradaForm.model = s.modelo;
            if (!entradaForm.color && s.cor) entradaForm.color = s.cor;
        }
        if (data.authorization?.type === 'funcionario') {
            const func = props.entryTypes.find((t) => t.name.toLowerCase().startsWith('funcion'));
            entradaForm.entry_type_id = func?.id ?? entradaForm.entry_type_id;
        }
    } catch {
        lookupInfo.value = null;
    }
}

const sinespUi = computed(() => {
    const s = lookupInfo.value?.sinesp;
    if (!s) return null;
    const linha = [s.marca, s.modelo, s.cor, s.ano_modelo || s.ano, s.uf].filter(Boolean).join(' · ');
    const mapa = {
        roubo_furto: { titulo: '🚨 ALERTA: VEÍCULO COM REGISTRO DE ROUBO/FURTO', cls: 'border-red-300 bg-red-50 text-red-800', alerta: true },
        restricao: { titulo: '⚠️ Veículo com restrição', cls: 'border-amber-300 bg-amber-50 text-amber-800', alerta: false },
        regular: { titulo: '✓ Situação regular (Sinesp)', cls: 'border-emerald-200 bg-emerald-50 text-emerald-800', alerta: false },
        nao_encontrado: { titulo: 'Placa não encontrada no Sinesp', cls: 'border-gray-200 bg-gray-50 text-gray-600', alerta: false },
        indisponivel: { titulo: 'Sinesp indisponível no momento', cls: 'border-gray-200 bg-gray-50 text-gray-500', alerta: false },
    };
    const base = mapa[s.situacao] || mapa.indisponivel;
    return { ...base, linha, mensagem: s.mensagem };
});

const selectedEntryType = computed(() => props.entryTypes.find((t) => t.id === entradaForm.entry_type_id));
const selectedCompany = computed(() => props.companies.find((c) => c.id === entradaForm.company_id));

/* Balsa / embarcação (conforme o tipo de entrada) */
const vesselMode = computed(() => selectedEntryType.value?.vessel_selection ?? 'none');
const showVessel = computed(() => vesselMode.value !== 'none');
const vesselRequired = computed(() => vesselMode.value === 'required');
const departuresForVessel = computed(() =>
    (props.departures || []).filter((d) => d.vessel_id === entradaForm.vessel_id),
);
const fmtDeparture = (d) => {
    const dia = new Date(d.departure_at).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    return `${dia} ${d.departure_time}${d.destination ? ' → ' + d.destination : ''}`;
};
function onVesselChange() {
    entradaForm.vessel_departure_id = null;
}

const entradaPrice = computed(() => {
    if (!selectedEntryType.value?.is_paid || !entradaForm.vehicle_category_id) return 0;
    return props.priceMatrix[entradaForm.entry_type_id]?.[entradaForm.vehicle_category_id] ?? 0;
});

const entradaDiscount = computed(() => {
    if (showIsencao.value && entradaForm.exemption_reason) return entradaPrice.value;
    if (selectedCompany.value) {
        return Math.round(entradaPrice.value * parseFloat(selectedCompany.value.discount_percent) ) / 100;
    }
    return 0;
});

const entradaBalance = computed(() => Math.max(Math.round((entradaPrice.value - entradaDiscount.value) * 100) / 100, 0));

const cobrancaNaEntrada = computed(
    () => selectedEntryType.value?.is_paid && selectedEntryType.value?.charge_moment === 'entrada' && entradaBalance.value > 0,
);

const showIsencao = ref(false);

const companyAuthorizedEntrada = computed(() => {
    const auth = entradaEvent.value?.authorization || lookupInfo.value?.authorization;
    return auth?.type === 'empresa' && auth?.company_id === entradaForm.company_id;
});

function submitEntrada() {
    if (!cobrancaNaEntrada.value) entradaForm.payments = [];
    if (!showVessel.value) {
        entradaForm.vessel_id = null;
        entradaForm.vessel_departure_id = null;
    }

    entradaForm
        .transform((data) => ({ ...data, plate: data.plate.toUpperCase().replace(/[^A-Z0-9]/g, '') }))
        .post(route('guarita.entrada'), {
            preserveScroll: true,
            onSuccess: () => {
                showEntrada.value = false;
                poll();
            },
        });
}

/* ------------------------------------------------------------------ */
/* Saída                                                               */
/* ------------------------------------------------------------------ */
const showSaida = ref(false);
const saidaEvent = ref(null);
const saidaRecord = ref(null);

const saidaForm = useForm({
    camera_event_id: null,
    exemption_reason: '',
    billing_justification: '',
    payments: [],
});

const saidaIsencao = ref(false);

function abrirSaida(event) {
    saidaEvent.value = event;
    saidaRecord.value = event.open_record;
    saidaForm.reset();
    saidaForm.camera_event_id = event.id;
    saidaIsencao.value = false;
    showSaida.value = true;
}

const saidaBalance = computed(() => {
    if (!saidaRecord.value) return 0;
    if (saidaIsencao.value && saidaForm.exemption_reason) return 0;
    return saidaRecord.value.balance;
});

const companyAuthorizedSaida = computed(() => {
    const auth = saidaEvent.value?.authorization;
    return auth?.type === 'empresa' && auth?.company_id === saidaRecord.value?.company_id;
});

function submitSaida() {
    if (saidaBalance.value <= 0) saidaForm.payments = [];

    saidaForm.post(route('guarita.saida', saidaRecord.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showSaida.value = false;
            poll();
        },
    });
}

/* ------------------------------------------------------------------ */
/* Saída sem entrada (RF09)                                            */
/* ------------------------------------------------------------------ */
const showSaidaSemEntrada = ref(false);

const semEntradaForm = useForm({
    plate: '',
    entry_type_id: null,
    vehicle_category_id: null,
    camera_event_id: null,
    justification: '',
});

function abrirSaidaSemEntrada(event = null) {
    semEntradaForm.reset();
    if (event) {
        semEntradaForm.plate = event.plate || '';
        semEntradaForm.camera_event_id = event.id;
    }
    showSaidaSemEntrada.value = true;
}

function submitSaidaSemEntrada() {
    semEntradaForm
        .transform((data) => ({ ...data, plate: data.plate.toUpperCase().replace(/[^A-Z0-9]/g, '') }))
        .post(route('guarita.saida-sem-entrada'), {
            preserveScroll: true,
            onSuccess: () => {
                showSaidaSemEntrada.value = false;
                poll();
            },
        });
}

/* ------------------------------------------------------------------ */
function descartar(event) {
    router.post(route('guarita.eventos.descartar', event.id), {}, { preserveScroll: true, onSuccess: poll });
}

function abrirCancela(camera) {
    router.post(route('guarita.cancela', camera), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Guarita" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-xl font-bold text-gray-800">Painel da Guarita</h2>
                    <span class="rounded-full bg-sky-100 px-3 py-1 text-sm font-bold text-sky-800">
                        {{ patioCount }} no pátio
                    </span>
                    <span v-if="lastUpdate" class="text-xs text-gray-400">
                        atualizado {{ lastUpdate.toLocaleTimeString('pt-BR') }}
                    </span>
                </div>
                <div class="flex gap-2">
                    <button
                        class="flex-1 rounded-lg bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700 active:bg-emerald-800 sm:flex-none sm:py-2"
                        @click="abrirCancela('entrada')"
                    >
                        ⬆ Cancela ENTRADA
                    </button>
                    <button
                        class="flex-1 rounded-lg bg-orange-600 px-4 py-3 text-sm font-bold text-white hover:bg-orange-700 active:bg-orange-800 sm:flex-none sm:py-2"
                        @click="abrirCancela('saida')"
                    >
                        ⬇ Cancela SAÍDA
                    </button>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-screen-2xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Alerta de visitas vencidas -->
            <div v-if="overstays.length" class="rounded-lg border-2 border-amber-400 bg-amber-50 p-4">
                <h3 class="font-bold text-amber-900">⚠ Visitas com tempo excedido ({{ overstays.length }})</h3>
                <ul class="mt-2 space-y-1 text-sm text-amber-800">
                    <li v-for="o in overstays" :key="o.id">
                        <strong class="font-mono">{{ o.plate }}</strong>
                        — {{ o.visitor_name || o.entry_type }} · entrou {{ hora(o.entered_at) }} ·
                        {{ Math.floor(o.stay_minutes / 60) }}h{{ String(o.stay_minutes % 60).padStart(2, '0') }}
                        (limite {{ o.limit_minutes }} min)
                    </li>
                </ul>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Fila de eventos -->
                <div class="space-y-4 lg:col-span-2">
                    <h3 class="text-lg font-bold text-gray-700">Leituras pendentes da câmera</h3>

                    <div v-if="!events.length" class="rounded-lg border-2 border-dashed border-gray-300 p-10 text-center text-gray-400">
                        Nenhuma leitura pendente. Aguardando veículos…
                        <p class="mt-2 text-xs">Câmera fora do ar? Use os botões de registro manual ao lado (modo contingência).</p>
                    </div>

                    <div
                        v-for="event in events"
                        :key="event.id"
                        class="overflow-hidden rounded-xl border bg-white shadow-sm"
                        :class="event.camera === 'entrada' ? 'border-emerald-300' : 'border-orange-300'"
                    >
                        <div class="flex flex-wrap">
                            <div class="w-full sm:w-56">
                                <img v-if="event.photo_url" :src="event.photo_url" class="h-full max-h-44 w-full object-cover" alt="Captura" />
                                <div v-else class="flex h-44 items-center justify-center bg-slate-200 text-slate-400">sem foto</div>
                            </div>
                            <div class="flex-1 p-4">
                                <div class="flex flex-wrap items-center gap-3">
                                    <span
                                        class="rounded px-2 py-0.5 text-xs font-black uppercase text-white"
                                        :class="event.camera === 'entrada' ? 'bg-emerald-600' : 'bg-orange-600'"
                                    >
                                        {{ event.camera }}
                                    </span>
                                    <span class="font-mono text-2xl font-black tracking-widest text-slate-900">
                                        {{ event.plate || '—' }}
                                    </span>
                                    <span v-if="event.confidence" class="text-xs text-gray-400">{{ event.confidence }}%</span>
                                    <span class="text-xs text-gray-400">{{ hora(event.occurred_at) }}</span>
                                </div>

                                <p class="mt-1 text-sm text-gray-600">
                                    {{ [event.color, event.brand, event.model].filter(Boolean).join(' · ') || 'cor/modelo não detectados' }}
                                </p>

                                <!-- Alerta de divergência (placa clonada?) -->
                                <p v-if="event.mismatch" class="mt-2 rounded bg-red-100 px-3 py-2 text-sm font-bold text-red-800">
                                    🚨 DIVERGÊNCIA: cor/modelo detectados não conferem com o cadastro
                                    ({{ [event.vehicle?.color, event.vehicle?.model].filter(Boolean).join(' ') }}).
                                    Confira o veículo — possível placa clonada.
                                </p>

                                <!-- Veículo conhecido -->
                                <div v-if="event.vehicle" class="mt-2 text-sm text-gray-700">
                                    <span class="font-semibold">Cadastro:</span>
                                    {{ [event.vehicle.category, event.vehicle.color, event.vehicle.model].filter(Boolean).join(' · ') }}
                                    <span v-if="event.vehicle.owner_name"> · {{ event.vehicle.owner_name }}</span>
                                    <span
                                        v-if="event.authorization"
                                        class="ms-2 rounded bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800"
                                    >
                                        {{ event.authorization.type === 'funcionario'
                                            ? `FUNCIONÁRIO: ${event.authorization.employee_name}`
                                            : `CONVÊNIO: ${event.authorization.company}` }}
                                    </span>
                                </div>

                                <!-- Registro em aberto (para saída) -->
                                <div v-if="event.open_record" class="mt-2 rounded bg-slate-50 px-3 py-2 text-sm">
                                    <span class="font-semibold">No pátio desde {{ hora(event.open_record.entered_at) }}</span>
                                    · {{ event.open_record.entry_type }}
                                    <span v-if="event.open_record.balance > 0" class="font-bold text-red-700">
                                        · pendente {{ brl(event.open_record.balance) }}
                                    </span>
                                    <span v-else class="font-bold text-emerald-700">· sem pendências</span>
                                </div>

                                <!-- Ações -->
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <template v-if="event.camera === 'entrada'">
                                        <button
                                            class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700"
                                            @click="abrirEntrada(event)"
                                        >
                                            ✔ Confirmar ENTRADA
                                        </button>
                                    </template>
                                    <template v-else>
                                        <button
                                            v-if="event.open_record"
                                            class="rounded-lg bg-orange-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-orange-700"
                                            @click="abrirSaida(event)"
                                        >
                                            ✔ Confirmar SAÍDA {{ event.open_record.balance > 0 ? `(cobrar ${brl(event.open_record.balance)})` : '' }}
                                        </button>
                                        <button
                                            v-else
                                            class="rounded-lg bg-red-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-red-700"
                                            @click="abrirSaidaSemEntrada(event)"
                                        >
                                            ⚠ Saída SEM entrada
                                        </button>
                                    </template>
                                    <button
                                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-100"
                                        @click="descartar(event)"
                                    >
                                        Descartar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contingência / ações manuais -->
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-gray-700">Registro manual</h3>
                    <p class="text-xs text-gray-500">
                        Modo contingência: se a câmera não leu a placa, registre manualmente. O registro fica marcado como manual nos relatórios.
                    </p>
                    <button
                        class="w-full rounded-xl bg-emerald-700 px-4 py-5 text-lg font-black text-white shadow hover:bg-emerald-800"
                        @click="abrirEntrada(null)"
                    >
                        ⬆ ENTRADA manual
                    </button>
                    <button
                        class="w-full rounded-xl bg-red-700 px-4 py-5 text-lg font-black text-white shadow hover:bg-red-800"
                        @click="abrirSaidaSemEntrada(null)"
                    >
                        ⬇ SAÍDA sem entrada
                    </button>
                    <p class="text-xs text-gray-500">
                        Para saída normal de veículo no pátio, use a tela
                        <a :href="route('guarita.patio')" class="font-semibold text-sky-600 hover:underline">Pátio</a>
                        ou aguarde a leitura da câmera de saída.
                    </p>
                </div>
            </div>
        </div>

        <!-- ============ MODAL ENTRADA ============ -->
        <Modal :show="showEntrada" max-width="2xl" @close="showEntrada = false">
            <form class="space-y-4 p-6" @submit.prevent="submitEntrada">
                <h2 class="text-lg font-bold text-gray-800">Registrar entrada</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Placa *</label>
                        <input
                            v-model="entradaForm.plate"
                            type="text"
                            maxlength="8"
                            class="mt-1 w-full rounded-md border-gray-300 font-mono text-xl font-bold uppercase tracking-widest"
                            :readonly="!!entradaEvent"
                            required
                            @blur="lookupPlate"
                        />
                        <p v-if="entradaForm.errors.plate" class="mt-1 text-xs text-red-600">{{ entradaForm.errors.plate }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-600">Tipo de entrada *</label>
                        <select v-model="entradaForm.entry_type_id" class="mt-1 w-full rounded-md border-gray-300" required>
                            <option :value="null" disabled>Selecione…</option>
                            <option v-for="t in entryTypes" :key="t.id" :value="t.id">
                                {{ t.name }}{{ t.is_paid ? ' (pago)' : '' }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-600">Categoria do veículo *</label>
                        <select v-model="entradaForm.vehicle_category_id" class="mt-1 w-full rounded-md border-gray-300" required>
                            <option :value="null" disabled>Selecione…</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Sugerida pela câmera — confirme/corrija sempre.</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-600">Empresa conveniada</label>
                        <select v-model="entradaForm.company_id" class="mt-1 w-full rounded-md border-gray-300">
                            <option :value="null">— nenhuma —</option>
                            <option v-for="c in companies" :key="c.id" :value="c.id">
                                {{ c.name }} ({{ c.discount_percent }}% desc.)
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Balsa / embarcação de destino -->
                <div v-if="showVessel" class="grid gap-4 rounded-lg border-2 border-cyan-200 bg-cyan-50 p-4 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-semibold text-cyan-900">
                            Balsa / embarcação {{ vesselRequired ? '*' : '(opcional)' }}
                        </label>
                        <select
                            v-model="entradaForm.vessel_id"
                            class="mt-1 w-full rounded-md border-gray-300"
                            :required="vesselRequired"
                            @change="onVesselChange"
                        >
                            <option :value="null">— selecione —</option>
                            <option v-for="v in vessels" :key="v.id" :value="v.id">
                                {{ v.name }}{{ v.default_destination ? ' (' + v.default_destination + ')' : '' }}
                            </option>
                        </select>
                        <p v-if="entradaForm.errors.vessel_id" class="mt-1 text-xs text-red-600">{{ entradaForm.errors.vessel_id }}</p>
                    </div>
                    <div v-if="entradaForm.vessel_id">
                        <label class="text-sm font-semibold text-cyan-900">Viagem / horário</label>
                        <select v-model="entradaForm.vessel_departure_id" class="mt-1 w-full rounded-md border-gray-300">
                            <option :value="null">— sem viagem específica —</option>
                            <option v-for="d in departuresForVessel" :key="d.id" :value="d.id">
                                {{ fmtDeparture(d) }}
                            </option>
                        </select>
                        <p v-if="!departuresForVessel.length" class="mt-1 text-xs text-cyan-700">
                            Nenhuma viagem agendada — o vínculo fica só na embarcação.
                        </p>
                    </div>
                </div>

                <!-- Situação do veículo (Sinesp) -->
                <div v-if="sinespUi" class="rounded-lg border p-3" :class="sinespUi.cls">
                    <p class="text-sm font-bold" :class="{ 'animate-pulse': sinespUi.alerta }">{{ sinespUi.titulo }}</p>
                    <p v-if="sinespUi.linha" class="mt-0.5 text-xs opacity-90">{{ sinespUi.linha }}</p>
                    <p v-if="sinespUi.mensagem" class="mt-0.5 text-xs opacity-75">{{ sinespUi.mensagem }}</p>
                </div>

                <!-- Identificação de visitante -->
                <div v-if="selectedEntryType?.requires_visitor_info" class="grid gap-4 rounded-lg bg-slate-50 p-4 sm:grid-cols-3">
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Nome do visitante *</label>
                        <input v-model="entradaForm.visitor_name" type="text" class="mt-1 w-full rounded-md border-gray-300" required />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Documento *</label>
                        <input v-model="entradaForm.visitor_document" type="text" class="mt-1 w-full rounded-md border-gray-300" required />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Destino</label>
                        <input v-model="entradaForm.destination" type="text" class="mt-1 w-full rounded-md border-gray-300" />
                    </div>
                </div>

                <!-- Dados do veículo -->
                <details class="rounded-lg border border-gray-200 p-3">
                    <summary class="cursor-pointer text-sm font-semibold text-gray-600">Dados do veículo (cor, marca, modelo, proprietário)</summary>
                    <div class="mt-3 grid gap-3 sm:grid-cols-4">
                        <input v-model="entradaForm.color" type="text" placeholder="Cor" class="rounded-md border-gray-300 text-sm" />
                        <input v-model="entradaForm.brand" type="text" placeholder="Marca" class="rounded-md border-gray-300 text-sm" />
                        <input v-model="entradaForm.model" type="text" placeholder="Modelo" class="rounded-md border-gray-300 text-sm" />
                        <input v-model="entradaForm.owner_name" type="text" placeholder="Proprietário/Motorista" class="rounded-md border-gray-300 text-sm" />
                    </div>
                </details>

                <!-- Valor -->
                <div v-if="selectedEntryType?.is_paid" class="rounded-lg bg-slate-100 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">
                            Valor ({{ selectedEntryType.charge_moment === 'entrada' ? 'cobrança na entrada' : 'cobrança na saída' }})
                            <span v-if="entradaDiscount > 0" class="text-emerald-700"> · desconto {{ brl(entradaDiscount) }}</span>
                        </span>
                        <span class="text-2xl font-black">{{ brl(entradaBalance) }}</span>
                    </div>
                    <button type="button" class="mt-1 text-xs font-semibold text-sky-600 hover:underline" @click="showIsencao = !showIsencao">
                        {{ showIsencao ? 'remover isenção' : 'conceder isenção pontual…' }}
                    </button>
                    <textarea
                        v-if="showIsencao"
                        v-model="entradaForm.exemption_reason"
                        rows="2"
                        class="mt-2 w-full rounded-md border-gray-300 text-sm"
                        placeholder="Justificativa obrigatória da isenção (vai para auditoria)"
                        required
                    ></textarea>
                </div>

                <!-- Pagamento na entrada (ex.: balsa) -->
                <div v-if="cobrancaNaEntrada" class="rounded-lg border-2 border-sky-200 p-4">
                    <h3 class="mb-3 font-bold text-sky-900">Pagamento obrigatório antes da liberação</h3>
                    <PaymentForm
                        v-model:payments="entradaForm.payments"
                        v-model:billing-justification="entradaForm.billing_justification"
                        :balance="entradaBalance"
                        :has-company="!!entradaForm.company_id"
                        :company-authorized="companyAuthorizedEntrada"
                        :pix="pix"
                    />
                    <p v-if="entradaForm.errors.payments" class="mt-2 text-sm font-semibold text-red-600">{{ entradaForm.errors.payments }}</p>
                </div>

                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" class="rounded-lg border px-5 py-2.5 font-semibold text-gray-600 hover:bg-gray-100" @click="showEntrada = false">
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        :disabled="entradaForm.processing"
                        class="rounded-lg bg-emerald-600 px-6 py-2.5 font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                    >
                        Confirmar entrada e abrir cancela
                    </button>
                </div>
            </form>
        </Modal>

        <!-- ============ MODAL SAÍDA ============ -->
        <Modal :show="showSaida" max-width="xl" @close="showSaida = false">
            <form v-if="saidaRecord" class="space-y-4 p-6" @submit.prevent="submitSaida">
                <h2 class="text-lg font-bold text-gray-800">
                    Registrar saída — <span class="font-mono">{{ saidaEvent?.plate }}</span>
                </h2>
                <p class="text-sm text-gray-600">
                    {{ saidaRecord.entry_type }} · entrou às {{ hora(saidaRecord.entered_at) }} ·
                    permanência {{ Math.floor(saidaRecord.stay_minutes / 60) }}h{{ String(saidaRecord.stay_minutes % 60).padStart(2, '0') }}
                </p>

                <div v-if="saidaRecord.balance > 0" class="rounded-lg border-2 border-orange-200 p-4">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="font-bold text-orange-900">Cobrança pendente</h3>
                        <button type="button" class="text-xs font-semibold text-sky-600 hover:underline" @click="saidaIsencao = !saidaIsencao">
                            {{ saidaIsencao ? 'remover isenção' : 'conceder isenção…' }}
                        </button>
                    </div>
                    <textarea
                        v-if="saidaIsencao"
                        v-model="saidaForm.exemption_reason"
                        rows="2"
                        class="mb-3 w-full rounded-md border-gray-300 text-sm"
                        placeholder="Justificativa obrigatória da isenção (vai para auditoria)"
                        required
                    ></textarea>
                    <PaymentForm
                        v-if="!saidaIsencao || !saidaForm.exemption_reason"
                        v-model:payments="saidaForm.payments"
                        v-model:billing-justification="saidaForm.billing_justification"
                        :balance="saidaBalance"
                        :has-company="!!saidaRecord.company_id"
                        :company-authorized="companyAuthorizedSaida"
                        :pix="pix"
                    />
                    <p v-if="saidaForm.errors.payments" class="mt-2 text-sm font-semibold text-red-600">{{ saidaForm.errors.payments }}</p>
                </div>
                <p v-else class="rounded-lg bg-emerald-50 px-4 py-3 font-semibold text-emerald-800">
                    Sem pendências — liberação direta.
                </p>

                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" class="rounded-lg border px-5 py-2.5 font-semibold text-gray-600 hover:bg-gray-100" @click="showSaida = false">
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        :disabled="saidaForm.processing"
                        class="rounded-lg bg-orange-600 px-6 py-2.5 font-bold text-white hover:bg-orange-700 disabled:opacity-50"
                    >
                        Confirmar saída e abrir cancela
                    </button>
                </div>
            </form>
        </Modal>

        <!-- ============ MODAL SAÍDA SEM ENTRADA ============ -->
        <Modal :show="showSaidaSemEntrada" max-width="lg" @close="showSaidaSemEntrada = false">
            <form class="space-y-4 p-6" @submit.prevent="submitSaidaSemEntrada">
                <h2 class="text-lg font-bold text-red-800">⚠ Saída sem registro de entrada</h2>
                <p class="text-sm text-gray-600">
                    Não há entrada em aberto para este veículo. O registro será marcado e auditado.
                </p>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Placa *</label>
                        <input v-model="semEntradaForm.plate" type="text" maxlength="8" class="mt-1 w-full rounded-md border-gray-300 font-mono font-bold uppercase" required />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Tipo *</label>
                        <select v-model="semEntradaForm.entry_type_id" class="mt-1 w-full rounded-md border-gray-300" required>
                            <option :value="null" disabled>Selecione…</option>
                            <option v-for="t in entryTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Categoria *</label>
                        <select v-model="semEntradaForm.vehicle_category_id" class="mt-1 w-full rounded-md border-gray-300" required>
                            <option :value="null" disabled>Selecione…</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="text-sm font-semibold text-gray-600">Justificativa * (auditada)</label>
                    <textarea v-model="semEntradaForm.justification" rows="2" class="mt-1 w-full rounded-md border-gray-300" required></textarea>
                    <p v-if="semEntradaForm.errors.justification" class="mt-1 text-xs text-red-600">{{ semEntradaForm.errors.justification }}</p>
                </div>

                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" class="rounded-lg border px-5 py-2.5 font-semibold text-gray-600 hover:bg-gray-100" @click="showSaidaSemEntrada = false">
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        :disabled="semEntradaForm.processing"
                        class="rounded-lg bg-red-600 px-6 py-2.5 font-bold text-white hover:bg-red-700 disabled:opacity-50"
                    >
                        Registrar saída manual
                    </button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
