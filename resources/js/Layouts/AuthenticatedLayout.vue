<script setup>
import { computed, ref, watch } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);

const page = usePage();
const user = computed(() => page.props.auth.user);
const role = computed(() => user.value?.role);

const isOperador = computed(() => ['operador', 'admin'].includes(role.value));
const isAdmin = computed(() => role.value === 'admin');
const isFinanceiro = computed(() => ['admin', 'financeiro'].includes(role.value));
const isGestao = computed(() => ['admin', 'financeiro', 'auditor'].includes(role.value));
const isAuditoria = computed(() => ['admin', 'auditor'].includes(role.value));

// Toast de feedback (flash do backend)
const flash = computed(() => page.props.flash || {});
const toast = ref(null);
let toastTimer = null;
watch(
    () => [flash.value.success, flash.value.error],
    ([success, error]) => {
        if (success || error) {
            toast.value = { type: success ? 'success' : 'error', text: success || error };
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => (toast.value = null), 5000);
        }
    },
    { immediate: true },
);
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav class="border-b border-gray-100 bg-slate-900 text-white">
                <div class="mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')" class="flex items-center gap-2">
                                    <span class="text-xl font-black tracking-tight text-sky-400">⚓ PortoAccess</span>
                                </Link>
                            </div>

                            <div class="hidden space-x-4 sm:-my-px sm:ms-8 sm:flex">
                                <template v-if="isOperador">
                                    <NavLink :href="route('guarita.painel')" :active="route().current('guarita.painel')" class="!text-gray-200">
                                        Guarita
                                    </NavLink>
                                    <NavLink :href="route('guarita.patio')" :active="route().current('guarita.patio')" class="!text-gray-200">
                                        Pátio
                                    </NavLink>
                                    <NavLink :href="route('guarita.consulta')" :active="route().current('guarita.consulta')" class="!text-gray-200">
                                        Consulta
                                    </NavLink>
                                </template>

                                <template v-if="isGestao">
                                    <NavLink :href="route('admin.dashboard')" :active="route().current('admin.dashboard')" class="!text-gray-200">
                                        Dashboard
                                    </NavLink>
                                    <NavLink :href="route('admin.relatorios')" :active="route().current('admin.relatorios')" class="!text-gray-200">
                                        Relatórios
                                    </NavLink>
                                </template>

                                <template v-if="isFinanceiro">
                                    <NavLink :href="route('admin.empresas.index')" :active="route().current('admin.empresas.*')" class="!text-gray-200">
                                        Empresas
                                    </NavLink>
                                    <NavLink :href="route('admin.faturas.index')" :active="route().current('admin.faturas.*')" class="!text-gray-200">
                                        Faturas
                                    </NavLink>
                                </template>

                                <template v-if="isAuditoria">
                                    <NavLink :href="route('admin.auditoria')" :active="route().current('admin.auditoria')" class="!text-gray-200">
                                        Auditoria
                                    </NavLink>
                                </template>

                                <div v-if="isAdmin" class="flex items-center">
                                    <Dropdown align="left" width="48">
                                        <template #trigger>
                                            <button
                                                type="button"
                                                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-gray-200 hover:text-sky-300"
                                            >
                                                Cadastros
                                                <svg class="ms-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </template>
                                        <template #content>
                                            <DropdownLink :href="route('admin.categorias.index')">Categorias de veículo</DropdownLink>
                                            <DropdownLink :href="route('admin.tipos-entrada.index')">Tipos de entrada</DropdownLink>
                                            <DropdownLink :href="route('admin.precos.index')">Tabela de preços</DropdownLink>
                                            <DropdownLink :href="route('admin.autorizados.index')">Veículos autorizados</DropdownLink>
                                            <DropdownLink :href="route('admin.usuarios.index')">Usuários</DropdownLink>
                                            <DropdownLink :href="route('admin.cancelamentos')">Cancelamentos</DropdownLink>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent px-3 py-2 text-sm font-medium leading-4 text-gray-200 transition duration-150 ease-in-out hover:text-white focus:outline-none"
                                            >
                                                {{ user.name }}
                                                <span class="ms-2 rounded bg-sky-700 px-1.5 py-0.5 text-[10px] uppercase">{{ user.role_label }}</span>
                                                <svg class="-me-0.5 ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')">Meu perfil</DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button">Sair</DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-slate-800 hover:text-gray-200 focus:outline-none"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="bg-slate-800 sm:hidden">
                    <div class="space-y-1 pb-3 pt-2">
                        <template v-if="isOperador">
                            <ResponsiveNavLink :href="route('guarita.painel')">Guarita</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('guarita.patio')">Pátio</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('guarita.consulta')">Consulta</ResponsiveNavLink>
                        </template>
                        <template v-if="isGestao">
                            <ResponsiveNavLink :href="route('admin.dashboard')">Dashboard</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('admin.relatorios')">Relatórios</ResponsiveNavLink>
                        </template>
                        <template v-if="isFinanceiro">
                            <ResponsiveNavLink :href="route('admin.empresas.index')">Empresas</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('admin.faturas.index')">Faturas</ResponsiveNavLink>
                        </template>
                        <template v-if="isAdmin">
                            <ResponsiveNavLink :href="route('admin.categorias.index')">Categorias</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('admin.tipos-entrada.index')">Tipos de entrada</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('admin.precos.index')">Preços</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('admin.autorizados.index')">Autorizados</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('admin.usuarios.index')">Usuários</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('admin.cancelamentos')">Cancelamentos</ResponsiveNavLink>
                        </template>
                        <template v-if="isAuditoria">
                            <ResponsiveNavLink :href="route('admin.auditoria')">Auditoria</ResponsiveNavLink>
                        </template>
                    </div>

                    <div class="border-t border-slate-700 pb-1 pt-4">
                        <div class="px-4">
                            <div class="text-base font-medium text-gray-100">{{ user.name }}</div>
                            <div class="text-sm font-medium text-gray-400">{{ user.email }} · {{ user.role_label }}</div>
                        </div>
                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">Meu perfil</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button">Sair</ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Toast flash -->
            <transition name="fade">
                <div
                    v-if="toast"
                    class="fixed right-4 top-20 z-50 max-w-md rounded-lg px-4 py-3 text-sm font-semibold text-white shadow-lg"
                    :class="toast.type === 'success' ? 'bg-emerald-600' : 'bg-red-600'"
                    @click="toast = null"
                >
                    {{ toast.text }}
                </div>
            </transition>

            <header class="bg-white shadow" v-if="$slots.header">
                <div class="mx-auto max-w-screen-2xl px-4 py-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main>
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
