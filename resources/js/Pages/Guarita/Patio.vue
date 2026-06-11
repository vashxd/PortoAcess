<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PaymentForm from '@/Components/Guarita/PaymentForm.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    records: Array,
    busca: String,
});

const busca = ref(props.busca || '');

function pesquisar() {
    router.get(route('guarita.patio'), { busca: busca.value }, { preserveState: true });
}

const brl = (v) => (v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const hora = (iso) => new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
const tempo = (min) => `${Math.floor(min / 60)}h${String(min % 60).padStart(2, '0')}`;

/* Saída a partir do pátio */
const showSaida = ref(false);
const selected = ref(null);

const saidaForm = useForm({
    exemption_reason: '',
    billing_justification: '',
    payments: [],
});

const saidaIsencao = ref(false);

function abrirSaida(record) {
    selected.value = record;
    saidaForm.reset();
    saidaIsencao.value = false;
    showSaida.value = true;
}

const saidaBalance = computed(() => {
    if (!selected.value) return 0;
    if (saidaIsencao.value && saidaForm.exemption_reason) return 0;
    return selected.value.balance;
});

function submitSaida() {
    if (saidaBalance.value <= 0) saidaForm.payments = [];

    saidaForm.post(route('guarita.saida', selected.value.id), {
        preserveScroll: true,
        onSuccess: () => (showSaida.value = false),
    });
}

/* Solicitar cancelamento */
const showCancel = ref(false);
const cancelForm = useForm({ reason: '' });

function abrirCancel(record) {
    selected.value = record;
    cancelForm.reset();
    showCancel.value = true;
}

function submitCancel() {
    cancelForm.post(route('guarita.solicitar-cancelamento', selected.value.id), {
        preserveScroll: true,
        onSuccess: () => (showCancel.value = false),
    });
}
</script>

<template>
    <Head title="Pátio" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-bold text-gray-800">Pátio atual — {{ records.length }} veículo(s)</h2>
                <form class="flex gap-2" @submit.prevent="pesquisar">
                    <input
                        v-model="busca"
                        type="text"
                        placeholder="Buscar placa…"
                        class="rounded-md border-gray-300 font-mono uppercase"
                    />
                    <button class="rounded-lg bg-sky-600 px-4 py-2 font-semibold text-white hover:bg-sky-700">Buscar</button>
                </form>
            </div>
        </template>

        <div class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="overflow-x-auto rounded-xl bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-slate-800 text-left text-xs uppercase text-white">
                        <tr>
                            <th class="px-4 py-3">Placa</th>
                            <th class="px-4 py-3">Categoria</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Empresa / Visitante</th>
                            <th class="px-4 py-3">Entrada</th>
                            <th class="px-4 py-3">Permanência</th>
                            <th class="px-4 py-3">Pendente</th>
                            <th class="px-4 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="r in records" :key="r.id" :class="{ 'bg-amber-50': r.overstay, 'bg-red-50': r.mismatch }">
                            <td class="px-4 py-3 font-mono text-base font-black">
                                {{ r.plate }}
                                <span v-if="r.manual_entry" title="Registro manual" class="ms-1 text-xs text-gray-400">✍</span>
                                <span v-if="r.mismatch" title="Divergência cor/modelo" class="ms-1">🚨</span>
                            </td>
                            <td class="px-4 py-3">{{ r.category }}</td>
                            <td class="px-4 py-3">{{ r.entry_type }}</td>
                            <td class="px-4 py-3">{{ r.company || r.visitor_name || '—' }}</td>
                            <td class="px-4 py-3">{{ hora(r.entered_at) }}</td>
                            <td class="px-4 py-3 font-semibold" :class="{ 'text-amber-700': r.overstay }">
                                {{ tempo(r.stay_minutes) }}
                                <span v-if="r.overstay" class="text-xs">⚠ excedido</span>
                            </td>
                            <td class="px-4 py-3 font-bold" :class="r.balance > 0 ? 'text-red-700' : 'text-emerald-700'">
                                {{ r.balance > 0 ? brl(r.balance) : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <button
                                        class="rounded bg-orange-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-orange-700"
                                        @click="abrirSaida(r)"
                                    >
                                        Registrar saída
                                    </button>
                                    <button
                                        v-if="!r.cancel_requested"
                                        class="rounded border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-100"
                                        @click="abrirCancel(r)"
                                    >
                                        Solicitar cancelamento
                                    </button>
                                    <span v-else class="px-2 py-1.5 text-xs italic text-gray-400">cancelamento solicitado</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!records.length">
                            <td colspan="8" class="px-4 py-10 text-center text-gray-400">Nenhum veículo no pátio.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal saída -->
        <Modal :show="showSaida" max-width="xl" @close="showSaida = false">
            <form v-if="selected" class="space-y-4 p-6" @submit.prevent="submitSaida">
                <h2 class="text-lg font-bold text-gray-800">
                    Registrar saída — <span class="font-mono">{{ selected.plate }}</span>
                </h2>

                <div v-if="selected.balance > 0" class="rounded-lg border-2 border-orange-200 p-4">
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
                        :has-company="!!selected.company"
                        :company-authorized="false"
                        :pix="$page.props.pix || { key: '', merchant: '' }"
                    />
                    <p v-if="saidaForm.errors.payments" class="mt-2 text-sm font-semibold text-red-600">{{ saidaForm.errors.payments }}</p>
                </div>
                <p v-else class="rounded-lg bg-emerald-50 px-4 py-3 font-semibold text-emerald-800">Sem pendências — liberação direta.</p>

                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" class="rounded-lg border px-5 py-2.5 font-semibold text-gray-600 hover:bg-gray-100" @click="showSaida = false">
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        :disabled="saidaForm.processing"
                        class="rounded-lg bg-orange-600 px-6 py-2.5 font-bold text-white hover:bg-orange-700 disabled:opacity-50"
                    >
                        Confirmar saída
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Modal solicitar cancelamento -->
        <Modal :show="showCancel" max-width="lg" @close="showCancel = false">
            <form v-if="selected" class="space-y-4 p-6" @submit.prevent="submitCancel">
                <h2 class="text-lg font-bold text-gray-800">
                    Solicitar cancelamento — <span class="font-mono">{{ selected.plate }}</span>
                </h2>
                <p class="text-sm text-gray-600">
                    O registro só é cancelado após aprovação do administrador (trilha de auditoria preservada).
                </p>
                <div>
                    <label class="text-sm font-semibold text-gray-600">Motivo *</label>
                    <textarea v-model="cancelForm.reason" rows="3" class="mt-1 w-full rounded-md border-gray-300" required></textarea>
                    <p v-if="cancelForm.errors.reason" class="mt-1 text-xs text-red-600">{{ cancelForm.errors.reason }}</p>
                </div>
                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" class="rounded-lg border px-5 py-2.5 font-semibold text-gray-600 hover:bg-gray-100" @click="showCancel = false">
                        Voltar
                    </button>
                    <button
                        type="submit"
                        :disabled="cancelForm.processing"
                        class="rounded-lg bg-red-600 px-6 py-2.5 font-bold text-white hover:bg-red-700 disabled:opacity-50"
                    >
                        Enviar solicitação
                    </button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
