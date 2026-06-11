<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    balance: { type: Number, required: true },
    payments: { type: Array, required: true }, // v-model:payments
    billingJustification: { type: String, default: '' },
    hasCompany: { type: Boolean, default: false },
    companyAuthorized: { type: Boolean, default: false },
    pix: { type: Object, default: () => ({ key: '', merchant: '' }) },
});

const emit = defineEmits(['update:payments', 'update:billingJustification']);

const methods = [
    { value: 'pix', label: 'PIX' },
    { value: 'cartao_debito', label: 'Cartão débito' },
    { value: 'cartao_credito', label: 'Cartão crédito' },
    { value: 'dinheiro', label: 'Dinheiro' },
    { value: 'faturado', label: 'Faturado (empresa)' },
];

const rows = ref(props.payments.length ? [...props.payments] : [{ method: 'pix', amount: props.balance, card_brand: '' }]);

watch(rows, (v) => emit('update:payments', v), { deep: true, immediate: true });

const total = computed(() => rows.value.reduce((s, r) => s + (parseFloat(r.amount) || 0), 0));
const diff = computed(() => Math.round((props.balance - total.value) * 100) / 100);

const hasFaturado = computed(() => rows.value.some((r) => r.method === 'faturado'));
const hasPix = computed(() => rows.value.some((r) => r.method === 'pix'));
const hasCard = computed(() => rows.value.some((r) => r.method.startsWith('cartao')));

function addRow() {
    rows.value.push({ method: 'cartao_debito', amount: Math.max(diff.value, 0), card_brand: '' });
}

function removeRow(i) {
    rows.value.splice(i, 1);
}

function fillRemainder(i) {
    const others = rows.value.reduce((s, r, j) => (j === i ? s : s + (parseFloat(r.amount) || 0)), 0);
    rows.value[i].amount = Math.max(Math.round((props.balance - others) * 100) / 100, 0);
}

// QR Code PIX (chave estática — fase 1)
const qrCanvas = ref(null);
async function renderQr() {
    if (qrCanvas.value && props.pix.key) {
        await QRCode.toCanvas(qrCanvas.value, props.pix.key, { width: 160, margin: 1 });
    }
}
onMounted(renderQr);
watch(hasPix, () => setTimeout(renderQr));

const brl = (v) => (v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between rounded-lg bg-slate-100 px-4 py-3">
            <span class="text-sm font-medium text-slate-600">Valor a cobrar</span>
            <span class="text-2xl font-black text-slate-900">{{ brl(balance) }}</span>
        </div>

        <div v-for="(row, i) in rows" :key="i" class="flex items-center gap-2">
            <select v-model="row.method" class="w-44 rounded-md border-gray-300 text-sm">
                <option v-for="m in methods" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
            <input
                v-model.number="row.amount"
                type="number"
                step="0.01"
                min="0"
                class="w-28 rounded-md border-gray-300 text-sm"
                placeholder="0,00"
            />
            <input
                v-if="row.method.startsWith('cartao')"
                v-model="row.card_brand"
                type="text"
                class="w-28 rounded-md border-gray-300 text-sm"
                placeholder="Bandeira"
            />
            <button type="button" class="text-xs font-semibold text-sky-600 hover:underline" @click="fillRemainder(i)">
                restante
            </button>
            <button v-if="rows.length > 1" type="button" class="text-red-500 hover:text-red-700" @click="removeRow(i)">
                ✕
            </button>
        </div>

        <div class="flex items-center justify-between">
            <button type="button" class="text-sm font-semibold text-sky-600 hover:underline" @click="addRow">
                + dividir pagamento (misto)
            </button>
            <span
                class="text-sm font-bold"
                :class="Math.abs(diff) < 0.01 ? 'text-emerald-600' : 'text-red-600'"
            >
                {{ Math.abs(diff) < 0.01 ? '✓ soma confere' : `diferença: ${brl(diff)}` }}
            </span>
        </div>

        <div v-if="hasPix && pix.key" class="flex items-center gap-4 rounded-lg border border-sky-200 bg-sky-50 p-3">
            <canvas ref="qrCanvas"></canvas>
            <div class="text-sm">
                <p class="font-bold text-sky-900">PIX — {{ pix.merchant }}</p>
                <p class="mt-1 break-all font-mono text-xs text-sky-800">{{ pix.key }}</p>
                <p class="mt-2 text-xs text-sky-600">Confirme o recebimento no app do banco antes de liberar.</p>
            </div>
        </div>

        <p v-if="hasCard" class="rounded bg-amber-50 px-3 py-2 text-xs text-amber-800">
            Passe o valor na maquininha e registre a bandeira. A conferência com a adquirente é manual (TEF na fase 2).
        </p>

        <div v-if="hasFaturado" class="space-y-1">
            <p v-if="!hasCompany" class="rounded bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">
                Para faturar, vincule uma empresa conveniada ao acesso.
            </p>
            <template v-else-if="!companyAuthorized">
                <label class="text-xs font-semibold text-slate-600">
                    Veículo não autorizado pela empresa — justificativa obrigatória:
                </label>
                <textarea
                    :value="billingJustification"
                    @input="emit('update:billingJustification', $event.target.value)"
                    rows="2"
                    class="w-full rounded-md border-gray-300 text-sm"
                    placeholder="Ex.: autorização verbal do gestor da empresa, registrada em..."
                ></textarea>
            </template>
        </div>
    </div>
</template>
