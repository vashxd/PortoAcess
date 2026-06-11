<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ company: Object, pendingBilled: Number });

const brl = (v) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const dt = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');
</script>

<template>
    <Head :title="company.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800">{{ company.name }}</h2>
                <Link :href="route('admin.empresas.index')" class="text-sm font-semibold text-sky-600 hover:underline">← voltar</Link>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6">
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-xl bg-white p-5 shadow">
                    <p class="text-xs font-semibold uppercase text-gray-400">CNPJ</p>
                    <p class="mt-1 font-bold">{{ company.cnpj || '—' }}</p>
                    <p class="mt-2 text-sm text-gray-500">{{ company.contact }}<br />{{ company.email }}<br />{{ company.phone }}</p>
                </div>
                <div class="rounded-xl bg-white p-5 shadow">
                    <p class="text-xs font-semibold uppercase text-gray-400">Fechamento</p>
                    <p class="mt-1 font-bold capitalize">{{ company.billing_cycle }}</p>
                    <p class="mt-2 text-sm text-gray-500">Desconto: {{ Number(company.discount_percent) }}%</p>
                </div>
                <div class="rounded-xl bg-white p-5 shadow">
                    <p class="text-xs font-semibold uppercase text-gray-400">Limite de crédito</p>
                    <p class="mt-1 font-bold">{{ company.credit_limit ? brl(company.credit_limit) : 'sem limite' }}</p>
                </div>
                <div class="rounded-xl p-5 shadow" :class="pendingBilled > 0 ? 'bg-orange-600 text-white' : 'bg-white'">
                    <p class="text-xs font-semibold uppercase" :class="pendingBilled > 0 ? 'text-orange-100' : 'text-gray-400'">Faturado em aberto</p>
                    <p class="mt-1 text-2xl font-black">{{ brl(pendingBilled) }}</p>
                    <p class="text-xs" :class="pendingBilled > 0 ? 'text-orange-100' : 'text-gray-400'">aguardando fechamento de fatura</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Veículos autorizados -->
                <div class="rounded-xl bg-white shadow">
                    <h3 class="border-b px-5 py-3 font-bold text-gray-700">Veículos autorizados</h3>
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="a in company.authorized_vehicles" :key="a.id">
                                <td class="px-5 py-2 font-mono font-bold">{{ a.vehicle?.plate }}</td>
                                <td class="px-5 py-2">{{ a.vehicle?.model || '—' }}</td>
                                <td class="px-5 py-2">{{ a.valid_until ? 'até ' + dt(a.valid_until) : 'sem validade' }}</td>
                                <td class="px-5 py-2">
                                    <span class="rounded px-2 py-0.5 text-xs font-bold" :class="a.active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500'">
                                        {{ a.active ? 'Ativo' : 'Revogado' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!company.authorized_vehicles?.length">
                                <td class="px-5 py-6 text-center text-gray-400">Nenhum veículo autorizado. Cadastre em Cadastros → Veículos autorizados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Faturas -->
                <div class="rounded-xl bg-white shadow">
                    <h3 class="border-b px-5 py-3 font-bold text-gray-700">Faturas</h3>
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="f in company.invoices" :key="f.id">
                                <td class="px-5 py-2">
                                    <Link :href="route('admin.faturas.show', f.id)" class="font-semibold text-sky-700 hover:underline">{{ f.number }}</Link>
                                </td>
                                <td class="px-5 py-2">{{ dt(f.period_start) }} – {{ dt(f.period_end) }}</td>
                                <td class="px-5 py-2 text-right font-bold">{{ brl(f.total) }}</td>
                                <td class="px-5 py-2">
                                    <span
                                        class="rounded px-2 py-0.5 text-xs font-bold"
                                        :class="{
                                            'bg-sky-100 text-sky-800': f.status === 'aberta',
                                            'bg-emerald-100 text-emerald-800': f.status === 'paga',
                                            'bg-red-100 text-red-800': f.status === 'vencida',
                                        }"
                                    >
                                        {{ f.status }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!company.invoices?.length">
                                <td class="px-5 py-6 text-center text-gray-400">Nenhuma fatura gerada.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
