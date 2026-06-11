<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BarChart from '@/Components/Admin/BarChart.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    stats: Object,
    daily: Array,
    byEntryType: Object,
    byCategory: Object,
    byMethod: Object,
});

const brl = (v) => (v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const dailyChart = computed(() =>
    props.daily.map((d) => ({
        label: new Date(d.date + 'T12:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }),
        value: d.count,
    })),
);

const monthDelta = computed(() => {
    const prev = props.stats.revenue_prev_month;
    if (!prev) return null;
    return Math.round(((props.stats.revenue_month - prev) / prev) * 100);
});

const distribuicao = (obj) => {
    const total = Object.values(obj).reduce((s, v) => s + Number(v), 0) || 1;
    return Object.entries(obj)
        .sort((a, b) => b[1] - a[1])
        .map(([name, v]) => ({ name, value: Number(v), pct: Math.round((Number(v) / total) * 100) }));
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold text-gray-800">Dashboard</h2>
        </template>

        <div class="mx-auto max-w-screen-2xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-xl bg-white p-5 shadow">
                    <p class="text-xs font-semibold uppercase text-gray-400">Receita hoje</p>
                    <p class="mt-1 text-2xl font-black text-slate-900">{{ brl(stats.revenue_today) }}</p>
                </div>
                <div class="rounded-xl bg-white p-5 shadow">
                    <p class="text-xs font-semibold uppercase text-gray-400">Receita na semana</p>
                    <p class="mt-1 text-2xl font-black text-slate-900">{{ brl(stats.revenue_week) }}</p>
                </div>
                <div class="rounded-xl bg-white p-5 shadow">
                    <p class="text-xs font-semibold uppercase text-gray-400">Receita no mês</p>
                    <p class="mt-1 text-2xl font-black text-slate-900">{{ brl(stats.revenue_month) }}</p>
                    <p v-if="monthDelta !== null" class="text-xs font-semibold" :class="monthDelta >= 0 ? 'text-emerald-600' : 'text-red-600'">
                        {{ monthDelta >= 0 ? '▲' : '▼' }} {{ Math.abs(monthDelta) }}% vs mês anterior ({{ brl(stats.revenue_prev_month) }})
                    </p>
                    <p class="mt-1 text-[11px] text-gray-400">inclui {{ brl(stats.billed_month) }} faturado a prazo</p>
                </div>
                <div class="rounded-xl bg-white p-5 shadow">
                    <p class="text-xs font-semibold uppercase text-gray-400">Ticket médio (mês)</p>
                    <p class="mt-1 text-2xl font-black text-slate-900">{{ brl(stats.avg_ticket) }}</p>
                </div>
                <div class="rounded-xl bg-sky-600 p-5 text-white shadow">
                    <p class="text-xs font-semibold uppercase text-sky-100">No pátio agora</p>
                    <p class="mt-1 text-3xl font-black">{{ stats.in_patio }}</p>
                    <p class="text-xs text-sky-100">{{ stats.vehicles_today }} veículos hoje · {{ stats.vehicles_month }} no mês</p>
                </div>
            </div>

            <!-- Gráfico de volume -->
            <div class="rounded-xl bg-white p-6 shadow">
                <h3 class="mb-4 font-bold text-gray-700">Veículos por dia (últimos 30 dias)</h3>
                <BarChart :data="dailyChart" :height="180" />
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="rounded-xl bg-white p-6 shadow">
                    <h3 class="mb-4 font-bold text-gray-700">Por tipo de entrada (mês)</h3>
                    <div v-for="d in distribuicao(byEntryType)" :key="d.name" class="mb-3">
                        <div class="flex justify-between text-sm">
                            <span>{{ d.name }}</span>
                            <span class="font-bold">{{ d.value }} ({{ d.pct }}%)</span>
                        </div>
                        <div class="mt-1 h-2 rounded bg-gray-100">
                            <div class="h-2 rounded bg-sky-500" :style="{ width: d.pct + '%' }"></div>
                        </div>
                    </div>
                    <p v-if="!Object.keys(byEntryType).length" class="text-sm text-gray-400">Sem dados no mês.</p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow">
                    <h3 class="mb-4 font-bold text-gray-700">Por categoria (mês)</h3>
                    <div v-for="d in distribuicao(byCategory)" :key="d.name" class="mb-3">
                        <div class="flex justify-between text-sm">
                            <span>{{ d.name }}</span>
                            <span class="font-bold">{{ d.value }} ({{ d.pct }}%)</span>
                        </div>
                        <div class="mt-1 h-2 rounded bg-gray-100">
                            <div class="h-2 rounded bg-emerald-500" :style="{ width: d.pct + '%' }"></div>
                        </div>
                    </div>
                    <p v-if="!Object.keys(byCategory).length" class="text-sm text-gray-400">Sem dados no mês.</p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow">
                    <h3 class="mb-4 font-bold text-gray-700">Receita por forma de pagamento (mês)</h3>
                    <div v-for="(v, name) in byMethod" :key="name" class="mb-2 flex justify-between border-b border-gray-100 pb-2 text-sm">
                        <span>{{ name }}</span>
                        <span class="font-bold">{{ brl(v) }}</span>
                    </div>
                    <p v-if="!Object.keys(byMethod).length" class="text-sm text-gray-400">Sem pagamentos no mês.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
