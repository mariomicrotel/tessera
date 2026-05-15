<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    HomeIcon,
    UserGroupIcon,
    BookOpenIcon,
    UserCircleIcon,
    TicketIcon,
    ClipboardDocumentListIcon,
    CreditCardIcon,
    DocumentTextIcon,
    EnvelopeIcon,
    BanknotesIcon,
    CalendarDaysIcon,
    BuildingOffice2Icon,
    CubeIcon,
    FolderIcon,
    Cog6ToothIcon,
    ChartBarIcon,
    Bars3Icon,
    ChevronDownIcon,
    ChevronRightIcon,
    XMarkIcon,
    CheckCircleIcon,
    KeyIcon,
    ArrowRightOnRectangleIcon,
    UsersIcon,
    TableCellsIcon,
    MagnifyingGlassIcon,
    ArrowUpTrayIcon,
    CurrencyEuroIcon,
} from '@heroicons/vue/24/outline';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import ThemeTriStateButton from '@/Components/ThemeTriStateButton.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import FlashToast from '@/Components/FlashToast.vue';

defineProps({
    title: String,
});

const page = usePage();
const showingNavigationDropdown = ref(false);

// Ottieni il tenant dai props di Inertia
const tenant = computed(() => page.props.currentTenant?.slug);

// Tessera feature flags (condivisi via HandleInertiaRequests.share)
const modules = computed(() => page.props.tessera_modules ?? {});
const mod = (name) => Boolean(modules.value[name]);
// Sezione "Contabilità" visibile se almeno uno dei sotto-moduli è ON
const showContabilitaSection = computed(() =>
    mod('double_entry_accounting') ||
    mod('chart_of_accounts') ||
    mod('accounting_reports') ||
    mod('civil_balance_sheet') ||
    mod('fiscal_year_closing') ||
    mod('accruals_deferrals')
);
const showIvaSection = computed(() => mod('vat_registers') || mod('invoicing'));
const showAdempimentiSection = computed(() => mod('tax_returns'));
const showCespitiSection = computed(() => mod('assets_and_depreciation'));

// Helper per route con tenant
const dashboardRoute = computed(() =>
    tenant.value ? route('dashboard', { tenant: tenant.value }) : '#'
);

const flash = ref(null);

const openSections = ref({
    soci: false,
    cassa: false,
    documenti: false,
    organiVotazioni: false,
    patrimonio: false,
    contabilita: false,
    iva: false,
    cooperativa: false,
    adempimenti: false,
    banking: false,
    ets: false,
    anagrafica: false,
    team: false,
});

function sectionForRoute(name) {
    if (!name) return null;
    if (name.startsWith('members.') || name.startsWith('libro-soci.') || name.startsWith('member-types.') || name.startsWith('tessere.') || name.startsWith('scadenzario.')) return 'soci';
    if (name.startsWith('incassi.') || name.startsWith('incassi-generici.') || name.startsWith('quote-sociali.') || name.startsWith('donazioni.') || name.startsWith('receipts.') || name.startsWith('spese.') || name.startsWith('expense-refunds.')) return 'cassa';
    if (name.startsWith('documents.') || name.startsWith('verbali.') || name.startsWith('templates.') || name.startsWith('email-templates.') || name.startsWith('receipt-templates.')) return 'documenti';
    if (name.startsWith('organi.') || name.startsWith('elezioni.')) return 'organiVotazioni';
    if (name.startsWith('events.') || name.startsWith('properties.') || name.startsWith('items.') || name.startsWith('locations.') || name.startsWith('warehouses.') || name.startsWith('cespiti.')) return 'patrimonio';
    if (name.startsWith('conti.') || name.startsWith('prima-nota.') || name === 'reports.accounting' || name === 'reports.rendiconto-cassa' || name.startsWith('scadenze.') || name.startsWith('esercizi.') || name.startsWith('ratei-risconti.') || name.startsWith('centri-di-costo.') || name.startsWith('bilancio.') || name.startsWith('relazione-missione.') || name.startsWith('erogazioni-liberali.') || name === 'reports.libro-giornale' || name === 'reports.registro-vendite' || name === 'reports.conto-economico') return 'contabilita';
    if (name.startsWith('iva.') || name.startsWith('suppliers.')) return 'iva';
    if (name.startsWith('compensi-terzi.') || name.startsWith('f24.')) return 'adempimenti';
    if (name.startsWith('riba.') || name.startsWith('riconciliazione.')) return 'banking';
    if (name.startsWith('ets.')) return 'ets';
    if (name.startsWith('anagrafica.') || name.startsWith('company-enrichment.')) return 'anagrafica';
    if (name.startsWith('capitale-sociale.') || name.startsWith('prestito-sociale.') || name.startsWith('ristorni.') || name === 'reports.situazione-capitale' || name === 'reports.conto-economico-coop') return 'cooperativa';
    if (name.startsWith('teams.')) return 'team';
    if (name === 'profile.show' || name.startsWith('api-tokens.')) return 'utente';
    return null;
}

function ensureSectionOpen() {
    const key = sectionForRoute(route().current());
    if (key) openSections.value[key] = true;
}

onMounted(ensureSectionOpen);
watch(() => page.url, ensureSectionOpen);

watch(
    () => page.props.flash,
    (value) => {
        if (value && value.message) {
            flash.value = {
                message: value.message,
                type: value.type || 'info',
            };
        } else {
            flash.value = null;
        }
    },
    { immediate: true },
);

function toggleSection(key) {
    openSections.value[key] = !openSections.value[key];
}

const coopTypeLabels = {
    lavoro:     'Coop. di Lavoro',
    sociale_a:  'Coop. Sociale (A)',
    sociale_b:  'Coop. Sociale (B)',
    agricola:   'Coop. Agricola',
    comunita:   'Coop. di Comunità',
    consumo:    'Coop. di Consumo',
    abitazione: 'Coop. di Abitazione',
    consortile: 'Coop. Consortile',
};

const coopTypeLabel = (type) => coopTypeLabels[type] ?? 'Cooperativa';

const switchToTeam = (team) => {
    router.put(route('current-team.update'), {
        team_id: team.id,
    }, {
        preserveState: false,
    });
};

const logout = () => {
    if (confirm('Sei sicuro di voler uscire?')) {
        router.post(route('logout'));
    }
};
</script>

<template>
    <div>
        <Head :title="title" />

        <Banner />

        <!-- Banner consulente: modalità commercialista esterno -->
        <div v-if="$page.props.isConsultantInTenant && $page.props.currentTenant"
            class="bg-blue-600 dark:bg-blue-700 text-white px-4 py-2 text-sm flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2 min-w-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span class="font-medium">Modalità consulente</span>
                <span class="opacity-90 truncate">su <strong>{{ $page.props.currentTenant.name }}</strong></span>
            </div>
            <Link :href="route('consultant.dashboard')"
                class="text-xs px-3 py-1 rounded-md bg-white/20 hover:bg-white/30 transition flex-shrink-0">
                ← Torna alla mia area consulente
            </Link>
        </div>

        <!-- Toast flash -->
        <div v-if="flash?.message" class="fixed top-4 right-4 z-50 px-4 sm:px-6 lg:px-8 flex flex-col gap-2">
            <FlashToast :message="flash.message" :type="flash.type" :timeout="8000" @close="flash = null" />
        </div>

        <div class="h-screen bg-gray-100 dark:bg-gray-900 flex flex-col sm:flex-row overflow-hidden">
            <!-- Mobile: top bar (logo, hamburger, user) -->
            <div class="flex sm:hidden items-center justify-between h-16 px-4 bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
                <Link :href="dashboardRoute" class="shrink-0 flex items-center gap-2 min-w-0">
                    <img v-if="$page.props.logo_url" :src="$page.props.logo_url" alt="Logo" class="block h-8 w-auto max-w-[120px] object-contain object-left shrink-0" />
                    <ApplicationMark v-else class="block h-8 w-auto shrink-0" />
                    <div v-if="$page.props.currentTenant" class="min-w-0 hidden xs:block">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate leading-tight">
                            {{ $page.props.currentTenant.name }}
                        </p>
                        <span
                            v-if="$page.props.is_cooperativa"
                            class="inline-flex items-center px-1 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700"
                        >🤝 {{ coopTypeLabel($page.props.cooperative_type) }}</span>
                        <span
                            v-else
                            class="inline-flex items-center px-1 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700"
                        >🏛️ ETS</span>
                    </div>
                </Link>
                <div class="flex items-center gap-2">
                    <Dropdown v-if="$page.props.jetstream.hasTeamFeatures" align="right" width="60">
                        <template #trigger>
                            <button type="button" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-500 dark:text-gray-400 border border-transparent">
                                {{ $page.props.auth.user.current_team.name }}
                                <ChevronDownIcon class="ms-2 -me-0.5 size-4" aria-hidden="true" />
                            </button>
                        </template>
                        <template #content>
                            <div class="w-60">
                                <div class="block px-4 py-2 text-xs text-gray-400">Gestisci team</div>
                                <DropdownLink :href="route('teams.show', $page.props.auth.user.current_team)">Impostazioni team</DropdownLink>
                                <DropdownLink v-if="$page.props.jetstream.canCreateTeams" :href="route('teams.create')">Crea nuovo team</DropdownLink>
                                <template v-if="$page.props.auth.user.all_teams.length > 1">
                                    <div class="border-t border-gray-200 dark:border-gray-600" />
                                    <div class="block px-4 py-2 text-xs text-gray-400">Cambia team</div>
                                    <template v-for="team in $page.props.auth.user.all_teams" :key="team.id">
                                        <form @submit.prevent="switchToTeam(team)">
                                            <DropdownLink as="button">
                                                <div class="flex items-center gap-2">
                                                    <CheckCircleIcon v-if="team.id == $page.props.auth.user.current_team_id" class="size-5 text-green-400 shrink-0" aria-hidden="true" />
                                                    <div>{{ team.name }}</div>
                                                </div>
                                            </DropdownLink>
                                        </form>
                                    </template>
                                </template>
                            </div>
                        </template>
                    </Dropdown>
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button v-if="$page.props.jetstream.managesProfilePhotos" class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                                <img class="size-8 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                            </button>
                            <button v-else type="button" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-500 dark:text-gray-400">
                                {{ $page.props.auth.user.name }}
                                <ChevronDownIcon class="ms-2 -me-0.5 size-4" aria-hidden="true" />
                            </button>
                        </template>
                        <template #content>
                            <div class="block px-4 py-2 text-xs text-gray-400">Gestisci account</div>
                            <DropdownLink :href="route('profile.show')">
                                <UserCircleIcon class="size-4 me-2 shrink-0" aria-hidden="true" />
                                Profilo
                            </DropdownLink>
                            <DropdownLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')">
                                <KeyIcon class="size-4 me-2 shrink-0" aria-hidden="true" />
                                Token API
                            </DropdownLink>
                            <div class="border-t border-gray-200 dark:border-gray-600" />
                            <form @submit.prevent="logout">
                                <DropdownLink as="button">
                                    <ArrowRightOnRectangleIcon class="size-4 me-2 shrink-0" aria-hidden="true" />
                                    Esci
                                </DropdownLink>
                            </form>
                        </template>
                    </Dropdown>
                    <button type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900" @click="showingNavigationDropdown = ! showingNavigationDropdown">
                        <Bars3Icon v-if="!showingNavigationDropdown" class="size-6" aria-hidden="true" />
                        <XMarkIcon v-else class="size-6" aria-hidden="true" />
                    </button>
                </div>
            </div>

            <!-- Mobile: responsive navigation menu (sovrapposto al contenuto) -->
            <div :class="{'block': showingNavigationDropdown, 'hidden': ! showingNavigationDropdown}" class="sm:hidden fixed inset-0 top-16 z-40 overflow-y-auto border-b border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800">
                <div class="pt-2 pb-3 space-y-1 px-4">
                    <ResponsiveNavLink :href="dashboardRoute" :active="route().current('dashboard')">
                        <HomeIcon class="size-5 shrink-0" aria-hidden="true" />
                        Dashboard
                    </ResponsiveNavLink>
                    <!-- Area Consulente -->
                    <ResponsiveNavLink
                        v-if="mod('consultant_workspace') && $page.props.userRoles?.includes('consultant')"
                        :href="route('consultant.dashboard')"
                        :active="route().current('consultant.*')">
                        <ClipboardDocumentListIcon class="size-5 shrink-0" aria-hidden="true" />
                        Area Consulente
                    </ResponsiveNavLink>
                    <div class="pt-4 pb-1 ps-3">
                        <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">ASSOCIAZIONE</p>
                    </div>
                    <!-- Movimenti Amministrativi -->
                    <ResponsiveNavLink
                        v-if="mod('administrative_movements') && ($page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('segreteria') || $page.props.userRoles?.includes('contabile'))"
                        :href="route('movimenti-amministrativi.index')"
                        :active="route().current('movimenti-amministrativi.*')">
                        <BanknotesIcon class="size-5 shrink-0" aria-hidden="true" />
                        Movimenti Amm.vi
                    </ResponsiveNavLink>
                    <ResponsiveNavLink v-if="$page.props.authMember" :href="route('members.show', $page.props.authMember.id)" :active="route().current('members.show')">
                        <UserCircleIcon class="size-5 shrink-0" aria-hidden="true" />
                        La mia tessera
                    </ResponsiveNavLink>
                    <template v-if="$page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('segreteria')">
                        <div class="pt-2">
                            <button type="button" class="block w-full inline-flex items-center gap-2 ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 dark:text-gray-400" @click="toggleSection('soci')">
                                <UserGroupIcon class="size-5 shrink-0" aria-hidden="true" />
                                <span class="flex-1">Soci</span>
                                <ChevronDownIcon v-if="openSections.soci" class="size-4 shrink-0" aria-hidden="true" />
                                <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                            </button>
                            <div v-show="openSections.soci" class="space-y-0.5 ps-6 border-l-4 border-transparent">
                                <ResponsiveNavLink :href="route('members.index')" :active="route().current('members.*')">
                                    <UserGroupIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Soci
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('libro-soci.index')" :active="route().current('libro-soci.*')">
                                    <BookOpenIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Libro soci
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('member-types.index')" :active="route().current('member-types.*')">
                                    <UserCircleIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Tipologie socio
                                </ResponsiveNavLink>
                                <ResponsiveNavLink v-if="!$page.props.is_cooperativa" :href="route('tessere.index')" :active="route().current('tessere.*')">
                                    <TicketIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Tessere
                                </ResponsiveNavLink>
                                <ResponsiveNavLink v-if="!$page.props.is_cooperativa" :href="route('scadenzario.index')" :active="route().current('scadenzario.*')">
                                    <CalendarDaysIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Scadenzario quote
                                </ResponsiveNavLink>
                            </div>
                        </div>
                        <div v-if="$page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('segreteria')" class="pt-2">
                            <button type="button" class="block w-full inline-flex items-center gap-2 ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 dark:text-gray-400" @click="toggleSection('documenti')">
                                <FolderIcon class="size-5 shrink-0" aria-hidden="true" />
                                <span class="flex-1">Documenti e verbali</span>
                                <ChevronDownIcon v-if="openSections.documenti" class="size-4 shrink-0" aria-hidden="true" />
                                <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                            </button>
                            <div v-show="openSections.documenti" class="space-y-0.5 ps-6">
                                <ResponsiveNavLink :href="route('documents.index')" :active="route().current('documents.*')">
                                    <FolderIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Documenti
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('verbali.index')" :active="route().current('verbali.*')">
                                    <ClipboardDocumentListIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Verbali
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('templates.index')" :active="route().current('templates.*')">
                                    <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Template
                                </ResponsiveNavLink>
                                <ResponsiveNavLink v-if="$page.props.userRoles?.includes('admin')" :href="route('email-templates.index')" :active="route().current('email-templates.*')">
                                    <EnvelopeIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Template email
                                </ResponsiveNavLink>
                                <ResponsiveNavLink v-if="$page.props.userRoles?.includes('admin')" :href="route('receipt-templates.index')" :active="route().current('receipt-templates.*')">
                                    <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Template ricevute
                                </ResponsiveNavLink>
                            </div>
                        </div>
                        <ResponsiveNavLink v-if="$page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('segreteria')" :href="route('file.index')" :active="route().current('file.*')" class="pt-2">
                            <FolderIcon class="size-5 shrink-0" aria-hidden="true" />
                            File
                        </ResponsiveNavLink>
                        <div v-if="$page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('segreteria')" class="pt-2">
                            <button type="button" class="block w-full inline-flex items-center gap-2 ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 dark:text-gray-400" @click="toggleSection('organiVotazioni')">
                                <UserGroupIcon class="size-5 shrink-0" aria-hidden="true" />
                                <span class="flex-1">Organi e votazioni</span>
                                <ChevronDownIcon v-if="openSections.organiVotazioni" class="size-4 shrink-0" aria-hidden="true" />
                                <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                            </button>
                            <div v-show="openSections.organiVotazioni" class="space-y-0.5 ps-6">
                                <ResponsiveNavLink :href="route('organi.index')" :active="route().current('organi.*')">
                                    <UserGroupIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Organi
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('elezioni.index')" :active="route().current('elezioni.*')">
                                    <ClipboardDocumentListIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Elezioni
                                </ResponsiveNavLink>
                            </div>
                        </div>
                        <div class="pt-2">
                            <button type="button" class="block w-full inline-flex items-center gap-2 ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 dark:text-gray-400" @click="toggleSection('ets')">
                                <DocumentTextIcon class="size-5 shrink-0" aria-hidden="true" />
                                <span class="flex-1">Atti fondativi</span>
                                <ChevronDownIcon v-if="openSections.ets" class="size-4 shrink-0" aria-hidden="true" />
                                <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                            </button>
                            <div v-show="openSections.ets" class="space-y-0.5 ps-6">
                                <ResponsiveNavLink :href="route('ets.statuto.index')" :active="route().current('ets.statuto.*')">
                                    <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Statuto
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('ets.atto-costitutivo.index')" :active="route().current('ets.atto-costitutivo.*')">
                                    <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Atto Costitutivo
                                </ResponsiveNavLink>
                            </div>
                        </div>
                        <div class="pt-4 pb-1 ps-3">
                            <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">PATRIMONIO</p>
                        </div>
                        <div v-if="$page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('segreteria') || $page.props.userRoles?.includes('contabile')" class="pt-2">
                            <button type="button" class="block w-full inline-flex items-center gap-2 ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 dark:text-gray-400" @click="toggleSection('patrimonio')">
                                <BuildingOffice2Icon class="size-5 shrink-0" aria-hidden="true" />
                                <span class="flex-1">Patrimonio e attività</span>
                                <ChevronDownIcon v-if="openSections.patrimonio" class="size-4 shrink-0" aria-hidden="true" />
                                <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                            </button>
                            <div v-show="openSections.patrimonio" class="space-y-0.5 ps-6">
                                <ResponsiveNavLink :href="route('events.index')" :active="route().current('events.*')">
                                    <CalendarDaysIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Eventi
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('properties.index')" :active="route().current('properties.*')">
                                    <BuildingOffice2Icon class="size-4 shrink-0" aria-hidden="true" />
                                    Immobili
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('locations.index')" :active="route().current('locations.*')">
                                    <BuildingOffice2Icon class="size-4 shrink-0" aria-hidden="true" />
                                    Sedi
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('warehouses.index')" :active="route().current('warehouses.*')">
                                    <CubeIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Magazzino
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('items.index')" :active="route().current('items.*')">
                                    <CubeIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Articoli
                                </ResponsiveNavLink>
                                <template v-if="showCespitiSection">
                                    <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>
                                    <ResponsiveNavLink :href="route('cespiti.index')" :active="route().current('cespiti.index') || route().current('cespiti.show') || route().current('cespiti.create') || route().current('cespiti.edit')">
                                        <TableCellsIcon class="size-4 shrink-0" aria-hidden="true" />
                                        Registro Cespiti
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink :href="route('cespiti.ammortamento.index')" :active="route().current('cespiti.ammortamento.*')">
                                        <ChartBarIcon class="size-4 shrink-0" aria-hidden="true" />
                                        Ammortamenti
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink v-if="$page.props.userRoles?.includes('admin')" :href="route('cespiti.categorie.index')" :active="route().current('cespiti.categorie.*')">
                                        <FolderIcon class="size-4 shrink-0" aria-hidden="true" />
                                        Categorie cespiti
                                    </ResponsiveNavLink>
                                </template>
                            </div>
                        </div>
                    </template>
                    <div class="pt-4 pb-1 ps-3">
                        <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">CASSA</p>
                    </div>
                    <!-- Cassa: visibile a staff e socio (socio vede solo "I miei rimborsi") -->
                    <div v-if="$page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('segreteria') || $page.props.userRoles?.includes('contabile') || $page.props.userRoles?.includes('socio')" class="pt-2">
                            <button type="button" class="block w-full inline-flex items-center gap-2 ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 dark:text-gray-400" @click="toggleSection('cassa')">
                                <CreditCardIcon class="size-5 shrink-0" aria-hidden="true" />
                                <span class="flex-1">Cassa</span>
                                <ChevronDownIcon v-if="openSections.cassa" class="size-4 shrink-0" aria-hidden="true" />
                                <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                            </button>
                            <div v-show="openSections.cassa" class="space-y-0.5 ps-6">
                                <template v-if="$page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('segreteria') || $page.props.userRoles?.includes('contabile')">
                                    <ResponsiveNavLink v-if="!$page.props.is_cooperativa" :href="route('quote-sociali.index')" :active="route().current('quote-sociali.*')">
                                        <CreditCardIcon class="size-4 shrink-0" aria-hidden="true" />
                                        Quote sociali
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink :href="route('donazioni.index')" :active="route().current('donazioni.*')">
                                        <CreditCardIcon class="size-4 shrink-0" aria-hidden="true" />
                                        Erogazioni liberali
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink :href="route('incassi-generici.index')" :active="route().current('incassi-generici.*') || (route().current('incassi.create') && $page.props.preselectedType === 'altro')">
                                        <CreditCardIcon class="size-4 shrink-0" aria-hidden="true" />
                                        Incasso generico
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink :href="route('receipts.index')" :active="route().current('receipts.*')">
                                        <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                        Ricevute
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink :href="route('spese.index')" :active="route().current('spese.*')">
                                        <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                        Spese
                                    </ResponsiveNavLink>
                                </template>
                                <ResponsiveNavLink :href="route('expense-refunds.index')" :active="route().current('expense-refunds.*')">
                                    <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                    {{ $page.props.userRoles?.includes('socio') && !$page.props.userRoles?.includes('admin') && !$page.props.userRoles?.includes('segreteria') && !$page.props.userRoles?.includes('contabile') ? 'I miei rimborsi' : 'Rimborsi' }}
                                </ResponsiveNavLink>
                            </div>
                    </div>
                    <!-- Cooperativa mobile -->
                    <div
                        v-if="$page.props.is_cooperativa && ($page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('segreteria') || $page.props.userRoles?.includes('contabile'))"
                        class="pt-2"
                    >
                        <button type="button" class="block w-full inline-flex items-center gap-2 ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-purple-600 dark:text-purple-400" @click="toggleSection('cooperativa')">
                            <BanknotesIcon class="size-5 shrink-0" aria-hidden="true" />
                            <span class="flex-1">Cooperativa</span>
                            <ChevronDownIcon v-if="openSections.cooperativa" class="size-4 shrink-0" aria-hidden="true" />
                            <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                        </button>
                        <div v-show="openSections.cooperativa" class="space-y-0.5 ps-6 border-l-4 border-purple-200 dark:border-purple-700">
                            <ResponsiveNavLink :href="route('capitale-sociale.index')" :active="route().current('capitale-sociale.*')">
                                <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                Capitale Sociale
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('prestito-sociale.index')" :active="route().current('prestito-sociale.*')">
                                <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                Prestito Sociale
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('ristorni.index')" :active="route().current('ristorni.*')">
                                <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                Ristorni
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('reports.situazione-capitale')" :active="route().current('reports.situazione-capitale*')">
                                <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                Situazione Capitale
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('reports.conto-economico-coop')" :active="route().current('reports.conto-economico-coop*')">
                                <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                Conto Economico Coop
                            </ResponsiveNavLink>
                        </div>
                    </div>
                    <div class="pt-4 pb-1 ps-3">
                        <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">CONTABILITÀ</p>
                    </div>
                    <!-- Contabilità (mobile) — feature flag: any of double_entry_accounting/chart_of_accounts/accounting_reports/civil_balance_sheet/fiscal_year_closing/accruals_deferrals -->
                    <div v-if="showContabilitaSection && ($page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('contabile'))" class="pt-2">
                            <button type="button" class="block w-full inline-flex items-center gap-2 ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 dark:text-gray-400" @click="toggleSection('contabilita')">
                                <ChartBarIcon class="size-5 shrink-0" aria-hidden="true" />
                                <span class="flex-1">{{ $page.props.tessera_labels?.contabilita || 'Contabilità' }}</span>
                                <ChevronDownIcon v-if="openSections.contabilita" class="size-4 shrink-0" aria-hidden="true" />
                                <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                            </button>
                            <div v-show="openSections.contabilita" class="space-y-0.5 ps-6">
                                <ResponsiveNavLink :href="route('conti.index')" :active="route().current('conti.*')">
                                    <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Conti tesoreria
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('prima-nota.index')" :active="route().current('prima-nota.*')">
                                    <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Prima nota
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('esercizi.index')" :active="route().current('esercizi.*')">
                                    <BookOpenIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Esercizi Contabili
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('ratei-risconti.index')" :active="route().current('ratei-risconti.*')">
                                    <ClipboardDocumentListIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Ratei e Risconti
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('centri-di-costo.index')" :active="route().current('centri-di-costo.*')">
                                    <FolderIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Centri di Costo
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('scadenze.dashboard')" :active="route().current('scadenze.*')">
                                    <CalendarDaysIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Scadenzario contabile
                                </ResponsiveNavLink>
                                <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>
                                <ResponsiveNavLink :href="route('reports.accounting')" :active="route().current('reports.accounting')">
                                    <ChartBarIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Report contabilità
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('reports.conto-economico')" :active="route().current('reports.conto-economico')">
                                    <ChartBarIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Conto Economico
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('reports.rendiconto-cassa')" :active="route().current('reports.rendiconto-cassa')">
                                    <ChartBarIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Rendiconto per cassa
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('reports.libro-giornale')" :active="route().current('reports.libro-giornale*')">
                                    <BookOpenIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Libro Giornale
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('reports.registro-vendite')" :active="route().current('reports.registro-vendite*')">
                                    <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Registro Vendite
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('bilancio.cee.index')" :active="route().current('bilancio.cee.*')">
                                    <ChartBarIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Bilancio CEE
                                </ResponsiveNavLink>
                                <ResponsiveNavLink v-if="!$page.props.is_cooperativa" :href="route('relazione-missione.index')" :active="route().current('relazione-missione.*')">
                                    <ClipboardDocumentListIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Relazione di Missione
                                </ResponsiveNavLink>
                                <ResponsiveNavLink v-if="!$page.props.is_cooperativa" :href="route('erogazioni-liberali.index')" :active="route().current('erogazioni-liberali.*')">
                                    <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Erogazioni Liberali ETS
                                </ResponsiveNavLink>
                            </div>
                    </div>
                    <!-- IVA e fornitori (mobile) — feature flag: vat_registers || invoicing -->
                    <div v-if="showIvaSection && ($page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('contabile'))" class="pt-2">
                            <button type="button" class="block w-full inline-flex items-center gap-2 ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 dark:text-gray-400" @click="toggleSection('iva')">
                                <DocumentTextIcon class="size-5 shrink-0" aria-hidden="true" />
                                <span class="flex-1">IVA e fatturazione</span>
                                <ChevronDownIcon v-if="openSections.iva" class="size-4 shrink-0" aria-hidden="true" />
                                <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                            </button>
                            <div v-show="openSections.iva" class="space-y-0.5 ps-6">
                                <ResponsiveNavLink :href="route('iva.fatture-attive.index')" :active="route().current('iva.fatture-attive.*')">
                                    <TicketIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Fatture Attive
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('iva.fatture-passive.index')" :active="route().current('iva.fatture-passive.*')">
                                    <TicketIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Fatture Passive
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('iva.fatture-passive.xml-import')" :active="route().current('iva.fatture-passive.xml-*')">
                                    <ArrowUpTrayIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Importa XML FE
                                </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('suppliers.index')" :active="route().current('suppliers.*')">
                                    <UsersIcon class="size-4 shrink-0" aria-hidden="true" />
                                    Fornitori
                                </ResponsiveNavLink>
                                <template v-if="$page.props.is_cooperativa">
                                    <ResponsiveNavLink :href="route('iva.codici.index')" :active="route().current('iva.codici.*')">
                                        <TableCellsIcon class="size-4 shrink-0" aria-hidden="true" />
                                        Codici IVA
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink :href="route('iva.liquidazioni.index')" :active="route().current('iva.liquidazioni.*')">
                                        <ChartBarIcon class="size-4 shrink-0" aria-hidden="true" />
                                        Liquidazioni IVA
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink :href="route('iva.registro-acquisti')" :active="route().current('iva.registro-acquisti')">
                                        <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                        Registro Acquisti
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink :href="route('iva.registro-vendite')" :active="route().current('iva.registro-vendite')">
                                        <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                        Registro Vendite IVA
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink :href="route('iva.lipe-xml')" :active="route().current('iva.lipe-xml')">
                                        <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                        LIPE XML
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink :href="route('iva.acconto-iva')" :active="route().current('iva.acconto-iva')">
                                        <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                        Acconto IVA
                                    </ResponsiveNavLink>
                                </template>
                            </div>
                    </div>
                    <!-- Adempimenti Fiscali (mobile) — feature flag: tax_returns -->
                    <div v-if="showAdempimentiSection && ($page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('contabile'))" class="pt-2">
                        <button type="button" class="block w-full inline-flex items-center gap-2 ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 dark:text-gray-400" @click="toggleSection('adempimenti')">
                            <ClipboardDocumentListIcon class="size-5 shrink-0" aria-hidden="true" />
                            <span class="flex-1">Adempimenti fiscali</span>
                            <ChevronDownIcon v-if="openSections.adempimenti" class="size-4 shrink-0" aria-hidden="true" />
                            <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                        </button>
                        <div v-show="openSections.adempimenti" class="space-y-0.5 ps-6">
                            <ResponsiveNavLink :href="route('compensi-terzi.index')" :active="route().current('compensi-terzi.*')">
                                <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                Compensi a Terzi
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('f24.index')" :active="route().current('f24.*')">
                                <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                Modello F24
                            </ResponsiveNavLink>
                        </div>
                    </div>
                    <!-- Banking & Riconciliazione -->
                    <div v-if="$page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('contabile')" class="pt-2">
                        <button type="button" class="block w-full inline-flex items-center gap-2 ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 dark:text-gray-400" @click="toggleSection('banking')">
                            <CreditCardIcon class="size-5 shrink-0" aria-hidden="true" />
                            <span class="flex-1">Banca</span>
                            <ChevronDownIcon v-if="openSections.banking" class="size-4 shrink-0" aria-hidden="true" />
                            <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                        </button>
                        <div v-show="openSections.banking" class="space-y-0.5 ps-6">
                            <ResponsiveNavLink :href="route('riba.index')" :active="route().current('riba.*')">
                                <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                RI.BA / CBI
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('riconciliazione.index')" :active="route().current('riconciliazione.*')">
                                <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                Riconciliazione bancaria
                            </ResponsiveNavLink>
                        </div>
                    </div>
                    <ResponsiveNavLink v-if="$page.props.userRoles?.includes('admin')" :href="route('users.index')" :active="route().current('users.*')">
                            <UsersIcon class="size-5 shrink-0" aria-hidden="true" />
                            Utenti
                    </ResponsiveNavLink>
                    <ResponsiveNavLink v-if="$page.props.userRoles?.includes('admin')" :href="route('audit.index')" :active="route().current('audit.*')">
                        <ClipboardDocumentListIcon class="size-5 shrink-0" aria-hidden="true" />
                        Audit Trail
                    </ResponsiveNavLink>
                    <div class="pt-4 pb-1 ps-3">
                        <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">AMMINISTRAZIONE</p>
                    </div>
                    <!-- Anagrafica ente & strumenti (mobile) -->
                    <div v-if="$page.props.userRoles?.includes('admin')" class="pt-2">
                        <button type="button" class="block w-full inline-flex items-center gap-2 ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 dark:text-gray-400" @click="toggleSection('anagrafica')">
                            <BuildingOffice2Icon class="size-5 shrink-0" aria-hidden="true" />
                            <span class="flex-1">Anagrafica ente</span>
                            <ChevronDownIcon v-if="openSections.anagrafica" class="size-4 shrink-0" aria-hidden="true" />
                            <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                        </button>
                        <div v-show="openSections.anagrafica" class="space-y-0.5 ps-6">
                            <ResponsiveNavLink :href="route('anagrafica.index')" :active="route().current('anagrafica.*')">
                                <BuildingOffice2Icon class="size-4 shrink-0" aria-hidden="true" />
                                Dati ente
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('company-enrichment.search-page')" :active="route().current('company-enrichment.search-page')">
                                <MagnifyingGlassIcon class="size-4 shrink-0" aria-hidden="true" />
                                Ricerca aziende
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('company-enrichment.stats-page')" :active="route().current('company-enrichment.stats-page')">
                                <CurrencyEuroIcon class="size-4 shrink-0" aria-hidden="true" />
                                Costi API
                            </ResponsiveNavLink>
                        </div>
                    </div>
                    <ResponsiveNavLink v-if="$page.props.userRoles?.includes('admin')" :href="route('settings.index')" :active="route().current('settings.*')">
                        <Cog6ToothIcon class="size-5 shrink-0" aria-hidden="true" />
                        Impostazioni
                    </ResponsiveNavLink>
                </div>
            </div>

            <!-- Desktop: sidebar verticale -->
            <aside class="hidden sm:flex sm:flex-col sm:w-64 sm:shrink-0 sm:h-screen bg-white dark:bg-gray-800 border-b sm:border-b-0 sm:border-r border-gray-100 dark:border-gray-700">
                <div class="flex flex-col h-full min-h-0">
                    <div class="shrink-0 flex flex-col px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                        <Link :href="dashboardRoute" class="shrink-0 min-w-0 mb-2">
                            <img v-if="$page.props.logo_url" :src="$page.props.logo_url" alt="Logo" class="block h-8 w-auto max-w-[140px] object-contain object-left" />
                            <ApplicationMark v-else class="block h-8 w-auto" />
                        </Link>
                        <div v-if="$page.props.currentTenant" class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate leading-tight">
                                {{ $page.props.currentTenant.name }}
                            </p>
                            <span
                                v-if="$page.props.is_cooperativa"
                                class="inline-flex items-center gap-1 mt-1 px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300"
                            >
                                🤝 {{ coopTypeLabel($page.props.cooperative_type) }}
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 mt-1 px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300"
                            >
                                🏛️ Ente Terzo Settore
                            </span>
                        </div>
                    </div>
                    <nav class="flex-1 overflow-y-auto py-4 px-3">
                        <div class="mt-1 space-y-1">
                            <NavLink :href="dashboardRoute" :active="route().current('dashboard')">
                                <HomeIcon class="size-5 shrink-0" aria-hidden="true" />
                                Dashboard
                            </NavLink>
                            <!-- Area Consulente -->
                            <NavLink
                                v-if="mod('consultant_workspace') && $page.props.userRoles?.includes('consultant')"
                                :href="route('consultant.dashboard')"
                                :active="route().current('consultant.*')">
                                <ClipboardDocumentListIcon class="size-5 shrink-0" aria-hidden="true" />
                                Area Consulente
                            </NavLink>
                            <div class="pt-4 pb-1 px-3">
                                <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">ASSOCIAZIONE</p>
                            </div>
                            <!-- Movimenti Amministrativi -->
                            <NavLink
                                v-if="mod('administrative_movements') && ($page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('segreteria') || $page.props.userRoles?.includes('contabile'))"
                                :href="route('movimenti-amministrativi.index')"
                                :active="route().current('movimenti-amministrativi.*')">
                                <BanknotesIcon class="size-5 shrink-0" aria-hidden="true" />
                                Movimenti Amm.vi
                            </NavLink>
                            <NavLink v-if="$page.props.authMember" :href="route('members.show', $page.props.authMember.id)" :active="route().current('members.show')">
                                <UserCircleIcon class="size-5 shrink-0" aria-hidden="true" />
                                La mia tessera
                            </NavLink>
                            <template v-if="$page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('segreteria')">
                                <!-- Soci -->
                                <div class="mt-2">
                                    <button type="button" class="block w-full flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md border-l-4 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50" @click="toggleSection('soci')">
                                        <UserGroupIcon class="size-5 shrink-0" aria-hidden="true" />
                                        <span class="flex-1 text-start">Soci</span>
                                        <ChevronDownIcon v-if="openSections.soci" class="size-4 shrink-0" aria-hidden="true" />
                                        <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                                    </button>
                                    <div v-show="openSections.soci" class="space-y-0.5 pl-4 ml-1 border-l border-gray-200 dark:border-gray-600">
                                        <NavLink :href="route('members.index')" :active="route().current('members.*')">
                                            <UserGroupIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Soci
                                        </NavLink>
                                        <NavLink :href="route('libro-soci.index')" :active="route().current('libro-soci.*')">
                                            <BookOpenIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Libro soci
                                        </NavLink>
                                        <NavLink :href="route('member-types.index')" :active="route().current('member-types.*')">
                                            <UserCircleIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Tipologie socio
                                        </NavLink>
                                        <NavLink v-if="!$page.props.is_cooperativa" :href="route('tessere.index')" :active="route().current('tessere.*')">
                                            <TicketIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Tessere
                                        </NavLink>
                                        <NavLink v-if="!$page.props.is_cooperativa" :href="route('scadenzario.index')" :active="route().current('scadenzario.*')">
                                            <CalendarDaysIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Scadenzario quote
                                        </NavLink>
                                    </div>
                                </div>
                                <!-- Documenti e verbali -->
                                <div v-if="$page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('segreteria')" class="mt-2">
                                    <button type="button" class="block w-full flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md border-l-4 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50" @click="toggleSection('documenti')">
                                        <FolderIcon class="size-5 shrink-0" aria-hidden="true" />
                                        <span class="flex-1 text-start">Documenti e verbali</span>
                                        <ChevronDownIcon v-if="openSections.documenti" class="size-4 shrink-0" aria-hidden="true" />
                                        <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                                    </button>
                                    <div v-show="openSections.documenti" class="space-y-0.5 pl-4 ml-1 border-l border-gray-200 dark:border-gray-600">
                                        <NavLink :href="route('documents.index')" :active="route().current('documents.*')">
                                            <FolderIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Documenti
                                        </NavLink>
                                        <NavLink :href="route('verbali.index')" :active="route().current('verbali.*')">
                                            <ClipboardDocumentListIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Verbali
                                        </NavLink>
                                        <NavLink :href="route('templates.index')" :active="route().current('templates.*')">
                                            <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Template
                                        </NavLink>
                                        <NavLink v-if="$page.props.userRoles?.includes('admin')" :href="route('email-templates.index')" :active="route().current('email-templates.*')">
                                            <EnvelopeIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Template email
                                        </NavLink>
                                        <NavLink v-if="$page.props.userRoles?.includes('admin')" :href="route('receipt-templates.index')" :active="route().current('receipt-templates.*')">
                                            <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Template ricevute
                                        </NavLink>
                                    </div>
                                </div>
                                <!-- File (voce a sé stante) -->
                                <NavLink :href="route('file.index')" :active="route().current('file.*')" class="mt-2">
                                    <FolderIcon class="size-5 shrink-0" aria-hidden="true" />
                                    File
                                </NavLink>
                                <!-- Organi e votazioni -->
                                <div v-if="$page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('segreteria')" class="mt-2">
                                    <button type="button" class="block w-full flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md border-l-4 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50" @click="toggleSection('organiVotazioni')">
                                        <UserGroupIcon class="size-5 shrink-0" aria-hidden="true" />
                                        <span class="flex-1 text-start">Organi e votazioni</span>
                                        <ChevronDownIcon v-if="openSections.organiVotazioni" class="size-4 shrink-0" aria-hidden="true" />
                                        <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                                    </button>
                                    <div v-show="openSections.organiVotazioni" class="space-y-0.5 pl-4 ml-1 border-l border-gray-200 dark:border-gray-600">
                                        <NavLink :href="route('organi.index')" :active="route().current('organi.*')">
                                            <UserGroupIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Organi
                                        </NavLink>
                                        <NavLink :href="route('elezioni.index')" :active="route().current('elezioni.*')">
                                            <ClipboardDocumentListIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Elezioni
                                        </NavLink>
                                    </div>
                                </div>
                                <!-- Atti fondativi -->
                                <div class="mt-2">
                                    <button type="button" class="block w-full flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md border-l-4 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50" @click="toggleSection('ets')">
                                        <DocumentTextIcon class="size-5 shrink-0" aria-hidden="true" />
                                        <span class="flex-1 text-start">Atti fondativi</span>
                                        <ChevronDownIcon v-if="openSections.ets" class="size-4 shrink-0" aria-hidden="true" />
                                        <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                                    </button>
                                    <div v-show="openSections.ets" class="space-y-0.5 pl-4 ml-1 border-l border-gray-200 dark:border-gray-600">
                                        <NavLink :href="route('ets.statuto.index')" :active="route().current('ets.statuto.*')">
                                            <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Statuto
                                        </NavLink>
                                        <NavLink :href="route('ets.atto-costitutivo.index')" :active="route().current('ets.atto-costitutivo.*')">
                                            <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Atto Costitutivo
                                        </NavLink>
                                    </div>
                                </div>
                                <div class="pt-4 pb-1 px-3">
                                    <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">PATRIMONIO</p>
                                </div>
                                <!-- Patrimonio e attività -->
                                <div v-if="$page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('segreteria') || $page.props.userRoles?.includes('contabile')" class="mt-2">
                                    <button type="button" class="block w-full flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md border-l-4 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50" @click="toggleSection('patrimonio')">
                                        <BuildingOffice2Icon class="size-5 shrink-0" aria-hidden="true" />
                                        <span class="flex-1 text-start">Patrimonio e attività</span>
                                        <ChevronDownIcon v-if="openSections.patrimonio" class="size-4 shrink-0" aria-hidden="true" />
                                        <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                                    </button>
                                    <div v-show="openSections.patrimonio" class="space-y-0.5 pl-4 ml-1 border-l border-gray-200 dark:border-gray-600">
                                        <NavLink :href="route('events.index')" :active="route().current('events.*')">
                                            <CalendarDaysIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Eventi
                                        </NavLink>
                                        <NavLink :href="route('properties.index')" :active="route().current('properties.*')">
                                            <BuildingOffice2Icon class="size-4 shrink-0" aria-hidden="true" />
                                            Immobili
                                        </NavLink>
                                        <NavLink :href="route('items.index')" :active="route().current('items.*')">
                                            <CubeIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Articoli
                                        </NavLink>
                                        <NavLink :href="route('locations.index')" :active="route().current('locations.*')">
                                            <BuildingOffice2Icon class="size-4 shrink-0" aria-hidden="true" />
                                            Sedi
                                        </NavLink>
                                        <NavLink :href="route('warehouses.index')" :active="route().current('warehouses.*')">
                                            <CubeIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Magazzino
                                        </NavLink>
                                        <template v-if="showCespitiSection">
                                            <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>
                                            <NavLink :href="route('cespiti.index')" :active="route().current('cespiti.index') || route().current('cespiti.show') || route().current('cespiti.create') || route().current('cespiti.edit')">
                                                <TableCellsIcon class="size-4 shrink-0" aria-hidden="true" />
                                                Registro Cespiti
                                            </NavLink>
                                            <NavLink :href="route('cespiti.ammortamento.index')" :active="route().current('cespiti.ammortamento.*')">
                                                <ChartBarIcon class="size-4 shrink-0" aria-hidden="true" />
                                                Ammortamenti
                                            </NavLink>
                                            <NavLink v-if="$page.props.userRoles?.includes('admin')" :href="route('cespiti.categorie.index')" :active="route().current('cespiti.categorie.*')">
                                                <FolderIcon class="size-4 shrink-0" aria-hidden="true" />
                                                Categorie cespiti
                                            </NavLink>
                                        </template>
                                    </div>
                                </div>
                                <div class="pt-4 pb-1 px-3">
                                    <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">CASSA</p>
                                </div>
                                <!-- Cassa: visibile a staff e socio (socio vede solo "I miei rimborsi") -->
                                <div v-if="$page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('segreteria') || $page.props.userRoles?.includes('contabile') || $page.props.userRoles?.includes('socio')" class="mt-2">
                                    <button type="button" class="block w-full flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md border-l-4 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50" @click="toggleSection('cassa')">
                                        <CreditCardIcon class="size-5 shrink-0" aria-hidden="true" />
                                        <span class="flex-1 text-start">Cassa</span>
                                        <ChevronDownIcon v-if="openSections.cassa" class="size-4 shrink-0" aria-hidden="true" />
                                        <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                                    </button>
                                    <div v-show="openSections.cassa" class="space-y-0.5 pl-4 ml-1 border-l border-gray-200 dark:border-gray-600">
                                        <template v-if="$page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('segreteria') || $page.props.userRoles?.includes('contabile')">
                                            <NavLink v-if="!$page.props.is_cooperativa" :href="route('quote-sociali.index')" :active="route().current('quote-sociali.*')">
                                                <CreditCardIcon class="size-4 shrink-0" aria-hidden="true" />
                                                Quote sociali
                                            </NavLink>
                                            <NavLink :href="route('donazioni.index')" :active="route().current('donazioni.*')">
                                                <CreditCardIcon class="size-4 shrink-0" aria-hidden="true" />
                                                Erogazioni liberali
                                            </NavLink>
                                            <NavLink :href="route('incassi-generici.index')" :active="route().current('incassi-generici.*') || (route().current('incassi.create') && $page.props.preselectedType === 'altro')">
                                                <CreditCardIcon class="size-4 shrink-0" aria-hidden="true" />
                                                Incasso generico
                                            </NavLink>
                                            <NavLink :href="route('receipts.index')" :active="route().current('receipts.*')">
                                                <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                                Ricevute
                                            </NavLink>
                                            <NavLink :href="route('spese.index')" :active="route().current('spese.*')">
                                                <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                                Spese
                                            </NavLink>
                                        </template>
                                        <NavLink :href="route('expense-refunds.index')" :active="route().current('expense-refunds.*')">
                                            <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                            {{ $page.props.userRoles?.includes('socio') && !$page.props.userRoles?.includes('admin') && !$page.props.userRoles?.includes('segreteria') && !$page.props.userRoles?.includes('contabile') ? 'I miei rimborsi' : 'Rimborsi' }}
                                        </NavLink>
                                    </div>
                                </div>
                                <!-- Cooperativa (visibile solo per cooperative) -->
                                <div
                                    v-if="$page.props.is_cooperativa && ($page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('segreteria') || $page.props.userRoles?.includes('contabile'))"
                                    class="mt-2"
                                >
                                    <button type="button" class="block w-full flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md border-l-4 border-transparent text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 hover:bg-purple-50 dark:hover:bg-purple-900/20" @click="toggleSection('cooperativa')">
                                        <BanknotesIcon class="size-5 shrink-0" aria-hidden="true" />
                                        <span class="flex-1 text-start">Cooperativa</span>
                                        <ChevronDownIcon v-if="openSections.cooperativa" class="size-4 shrink-0" aria-hidden="true" />
                                        <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                                    </button>
                                    <div v-show="openSections.cooperativa" class="space-y-0.5 pl-4 ml-1 border-l border-purple-200 dark:border-purple-700">
                                        <NavLink :href="route('capitale-sociale.index')" :active="route().current('capitale-sociale.*')">
                                            <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Capitale Sociale
                                        </NavLink>
                                        <NavLink :href="route('prestito-sociale.index')" :active="route().current('prestito-sociale.*')">
                                            <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Prestito Sociale
                                        </NavLink>
                                        <NavLink :href="route('ristorni.index')" :active="route().current('ristorni.*')">
                                            <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Ristorni
                                        </NavLink>
                                        <NavLink :href="route('reports.situazione-capitale')" :active="route().current('reports.situazione-capitale*')">
                                            <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Situazione Capitale
                                        </NavLink>
                                        <NavLink :href="route('reports.conto-economico-coop')" :active="route().current('reports.conto-economico-coop*')">
                                            <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Conto Economico Coop
                                        </NavLink>
                                    </div>
                                </div>
                                <div class="pt-4 pb-1 px-3">
                                    <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">CONTABILITÀ</p>
                                </div>
                                <!-- Contabilità (desktop) — feature flag: any contabile flag -->
                                <div v-if="showContabilitaSection && ($page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('contabile'))" class="mt-2">
                                    <button type="button" class="block w-full flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md border-l-4 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50" @click="toggleSection('contabilita')">
                                        <ChartBarIcon class="size-5 shrink-0" aria-hidden="true" />
                                        <span class="flex-1 text-start">{{ $page.props.tessera_labels?.contabilita || 'Contabilità' }}</span>
                                        <ChevronDownIcon v-if="openSections.contabilita" class="size-4 shrink-0" aria-hidden="true" />
                                        <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                                    </button>
                                    <div v-show="openSections.contabilita" class="space-y-0.5 pl-4 ml-1 border-l border-gray-200 dark:border-gray-600">
                                        <NavLink :href="route('conti.index')" :active="route().current('conti.*')">
                                            <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Conti tesoreria
                                        </NavLink>
                                        <NavLink :href="route('prima-nota.index')" :active="route().current('prima-nota.*')">
                                            <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Prima nota
                                        </NavLink>
                                        <NavLink :href="route('esercizi.index')" :active="route().current('esercizi.*')">
                                            <BookOpenIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Esercizi Contabili
                                        </NavLink>
                                        <NavLink :href="route('ratei-risconti.index')" :active="route().current('ratei-risconti.*')">
                                            <ClipboardDocumentListIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Ratei e Risconti
                                        </NavLink>
                                        <NavLink :href="route('centri-di-costo.index')" :active="route().current('centri-di-costo.*')">
                                            <FolderIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Centri di Costo
                                        </NavLink>
                                        <NavLink :href="route('scadenze.dashboard')" :active="route().current('scadenze.*')">
                                            <CalendarDaysIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Scadenzario contabile
                                        </NavLink>
                                        <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>
                                        <NavLink :href="route('reports.accounting')" :active="route().current('reports.accounting')">
                                            <ChartBarIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Report contabilità
                                        </NavLink>
                                        <NavLink :href="route('reports.conto-economico')" :active="route().current('reports.conto-economico')">
                                            <ChartBarIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Conto Economico
                                        </NavLink>
                                        <NavLink :href="route('reports.rendiconto-cassa')" :active="route().current('reports.rendiconto-cassa')">
                                            <ChartBarIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Rendiconto per cassa
                                        </NavLink>
                                        <NavLink :href="route('reports.libro-giornale')" :active="route().current('reports.libro-giornale*')">
                                            <BookOpenIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Libro Giornale
                                        </NavLink>
                                        <NavLink :href="route('reports.registro-vendite')" :active="route().current('reports.registro-vendite*')">
                                            <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Registro Vendite
                                        </NavLink>
                                        <NavLink :href="route('bilancio.cee.index')" :active="route().current('bilancio.cee.*')">
                                            <ChartBarIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Bilancio CEE
                                        </NavLink>
                                        <NavLink v-if="!$page.props.is_cooperativa" :href="route('relazione-missione.index')" :active="route().current('relazione-missione.*')">
                                            <ClipboardDocumentListIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Relazione di Missione
                                        </NavLink>
                                        <NavLink v-if="!$page.props.is_cooperativa" :href="route('erogazioni-liberali.index')" :active="route().current('erogazioni-liberali.*')">
                                            <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Erogazioni Liberali ETS
                                        </NavLink>
                                    </div>
                                </div>
                                <!-- IVA e fatturazione (desktop) — feature flag: vat_registers || invoicing -->
                                <div v-if="showIvaSection && ($page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('contabile'))" class="mt-2">
                                    <button type="button" class="block w-full flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md border-l-4 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50" @click="toggleSection('iva')">
                                        <DocumentTextIcon class="size-5 shrink-0" aria-hidden="true" />
                                        <span class="flex-1 text-start">IVA e fatturazione</span>
                                        <ChevronDownIcon v-if="openSections.iva" class="size-4 shrink-0" aria-hidden="true" />
                                        <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                                    </button>
                                    <div v-show="openSections.iva" class="space-y-0.5 pl-4 ml-1 border-l border-gray-200 dark:border-gray-600">
                                        <NavLink :href="route('iva.fatture-attive.index')" :active="route().current('iva.fatture-attive.*')">
                                            <TicketIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Fatture Attive
                                        </NavLink>
                                        <NavLink :href="route('iva.fatture-passive.index')" :active="route().current('iva.fatture-passive.*')">
                                            <TicketIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Fatture Passive
                                        </NavLink>
                                        <NavLink :href="route('iva.fatture-passive.xml-import')" :active="route().current('iva.fatture-passive.xml-*')">
                                            <ArrowUpTrayIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Importa XML FE
                                        </NavLink>
                                        <NavLink :href="route('suppliers.index')" :active="route().current('suppliers.*')">
                                            <UsersIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Fornitori
                                        </NavLink>
                                        <template v-if="$page.props.is_cooperativa">
                                            <NavLink :href="route('iva.codici.index')" :active="route().current('iva.codici.*')">
                                                <TableCellsIcon class="size-4 shrink-0" aria-hidden="true" />
                                                Codici IVA
                                            </NavLink>
                                            <NavLink :href="route('iva.liquidazioni.index')" :active="route().current('iva.liquidazioni.*')">
                                                <ChartBarIcon class="size-4 shrink-0" aria-hidden="true" />
                                                Liquidazioni IVA
                                            </NavLink>
                                            <NavLink :href="route('iva.registro-acquisti')" :active="route().current('iva.registro-acquisti')">
                                                <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                                Registro Acquisti
                                            </NavLink>
                                            <NavLink :href="route('iva.registro-vendite')" :active="route().current('iva.registro-vendite')">
                                                <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                                Registro Vendite IVA
                                            </NavLink>
                                            <NavLink :href="route('iva.lipe-xml')" :active="route().current('iva.lipe-xml')">
                                                <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                                LIPE XML
                                            </NavLink>
                                            <NavLink :href="route('iva.acconto-iva')" :active="route().current('iva.acconto-iva')">
                                                <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                                Acconto IVA
                                            </NavLink>
                                        </template>
                                    </div>
                                </div>
                                <!-- Adempimenti fiscali (desktop) — feature flag: tax_returns -->
                                <div v-if="showAdempimentiSection && ($page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('contabile'))" class="mt-2">
                                    <button type="button" class="block w-full flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md border-l-4 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50" @click="toggleSection('adempimenti')">
                                        <ClipboardDocumentListIcon class="size-5 shrink-0" aria-hidden="true" />
                                        <span class="flex-1 text-start">Adempimenti fiscali</span>
                                        <ChevronDownIcon v-if="openSections.adempimenti" class="size-4 shrink-0" aria-hidden="true" />
                                        <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                                    </button>
                                    <div v-show="openSections.adempimenti" class="space-y-0.5 pl-4 ml-1 border-l border-gray-200 dark:border-gray-600">
                                        <NavLink :href="route('compensi-terzi.index')" :active="route().current('compensi-terzi.*')">
                                            <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Compensi a Terzi
                                        </NavLink>
                                        <NavLink :href="route('f24.index')" :active="route().current('f24.*')">
                                            <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Modello F24
                                        </NavLink>
                                    </div>
                                </div>
                                <!-- Banking & Riconciliazione -->
                                <div v-if="$page.props.userRoles?.includes('admin') || $page.props.userRoles?.includes('contabile')" class="mt-2">
                                    <button type="button" class="block w-full flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md border-l-4 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50" @click="toggleSection('banking')">
                                        <CreditCardIcon class="size-5 shrink-0" aria-hidden="true" />
                                        <span class="flex-1 text-start">Banca</span>
                                        <ChevronDownIcon v-if="openSections.banking" class="size-4 shrink-0" aria-hidden="true" />
                                        <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                                    </button>
                                    <div v-show="openSections.banking" class="space-y-0.5 pl-4 ml-1 border-l border-gray-200 dark:border-gray-600">
                                        <NavLink :href="route('riba.index')" :active="route().current('riba.*')">
                                            <DocumentTextIcon class="size-4 shrink-0" aria-hidden="true" />
                                            RI.BA / CBI
                                        </NavLink>
                                        <NavLink :href="route('riconciliazione.index')" :active="route().current('riconciliazione.*')">
                                            <BanknotesIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Riconciliazione bancaria
                                        </NavLink>
                                    </div>
                                </div>
                                <NavLink v-if="$page.props.userRoles?.includes('admin')" :href="route('users.index')" :active="route().current('users.*')">
                                    <UsersIcon class="size-5 shrink-0" aria-hidden="true" />
                                    Utenti
                                </NavLink>
                                <NavLink v-if="$page.props.userRoles?.includes('admin')" :href="route('audit.index')" :active="route().current('audit.*')">
                                    <ClipboardDocumentListIcon class="size-5 shrink-0" aria-hidden="true" />
                                    Audit Trail
                                </NavLink>
                                <div class="pt-4 pb-1 px-3">
                                    <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">AMMINISTRAZIONE</p>
                                </div>
                                <!-- Anagrafica ente & strumenti ricerca -->
                                <div v-if="$page.props.userRoles?.includes('admin')" class="mt-2">
                                    <button type="button" class="block w-full flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md border-l-4 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50" @click="toggleSection('anagrafica')">
                                        <BuildingOffice2Icon class="size-5 shrink-0" aria-hidden="true" />
                                        <span class="flex-1 text-start">Anagrafica ente</span>
                                        <ChevronDownIcon v-if="openSections.anagrafica" class="size-4 shrink-0" aria-hidden="true" />
                                        <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                                    </button>
                                    <div v-show="openSections.anagrafica" class="space-y-0.5 pl-4 ml-1 border-l border-gray-200 dark:border-gray-600">
                                        <NavLink :href="route('anagrafica.index')" :active="route().current('anagrafica.*')">
                                            <BuildingOffice2Icon class="size-4 shrink-0" aria-hidden="true" />
                                            Dati ente
                                        </NavLink>
                                        <NavLink :href="route('company-enrichment.search-page')" :active="route().current('company-enrichment.search-page')">
                                            <MagnifyingGlassIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Ricerca aziende
                                        </NavLink>
                                        <NavLink :href="route('company-enrichment.stats-page')" :active="route().current('company-enrichment.stats-page')">
                                            <CurrencyEuroIcon class="size-4 shrink-0" aria-hidden="true" />
                                            Costi API
                                        </NavLink>
                                    </div>
                                </div>
                                <NavLink v-if="$page.props.userRoles?.includes('admin')" :href="route('settings.index')" :active="route().current('settings.*')">
                                    <Cog6ToothIcon class="size-5 shrink-0" aria-hidden="true" />
                                    Impostazioni
                                </NavLink>
                            </template>
                            <!-- Team (stesso stile degli altri gruppi) -->
                            <div v-if="$page.props.jetstream.hasTeamFeatures" class="mt-2">
                                <button type="button" class="block w-full flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md border-l-4 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50" @click="toggleSection('team')">
                                    <UserGroupIcon class="size-5 shrink-0" aria-hidden="true" />
                                    <span class="flex-1 text-start">{{ $page.props.auth.user.current_team?.name || 'Team' }}</span>
                                    <ChevronDownIcon v-if="openSections.team" class="size-4 shrink-0" aria-hidden="true" />
                                    <ChevronRightIcon v-else class="size-4 shrink-0" aria-hidden="true" />
                                </button>
                                <div v-show="openSections.team" class="space-y-0.5 pl-4 ml-1 border-l border-gray-200 dark:border-gray-600">
                                    <NavLink :href="route('teams.show', $page.props.auth.user.current_team)" :active="route().current('teams.show')">
                                        <Cog6ToothIcon class="size-4 shrink-0" aria-hidden="true" />
                                        Impostazioni team
                                    </NavLink>
                                    <NavLink v-if="$page.props.jetstream.canCreateTeams" :href="route('teams.create')" :active="route().current('teams.create')">
                                        <UserGroupIcon class="size-4 shrink-0" aria-hidden="true" />
                                        Crea nuovo team
                                    </NavLink>
                                    <template v-if="$page.props.auth.user.all_teams?.length > 1">
                                        <div class="pt-1 mt-1 border-t border-gray-200 dark:border-gray-600">
                                            <div class="px-3 py-1 text-xs text-gray-400">Cambia team</div>
                                            <form v-for="team in $page.props.auth.user.all_teams" :key="team.id" @submit.prevent="switchToTeam(team)" class="block">
                                                <button type="submit" class="block w-full flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md border-l-4 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 focus:outline-none transition duration-150 ease-in-out">
                                                    <CheckCircleIcon v-if="team.id == $page.props.auth.user.current_team_id" class="size-4 shrink-0 text-green-500" aria-hidden="true" />
                                                    <span class="flex-1 text-start">{{ team.name }}</span>
                                                </button>
                                            </form>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </nav>
                    
                    <!-- Azioni utente e theme toggle sul fondo -->
                    <div class="shrink-0 py-2 px-3 border-t border-gray-100 dark:border-gray-700">
                        <div class="flex items-center gap-2">
                            <!-- Profilo -->
                            <Link 
                                :href="route('profile.show')" 
                                class="flex items-center justify-center w-8 h-8 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition duration-150 ease-in-out"
                                :class="{ 'text-indigo-600 dark:text-indigo-400': route().current('profile.show') }"
                                title="Profilo"
                            >
                                <UserCircleIcon class="size-5" aria-hidden="true" />
                            </Link>
                            
                            <!-- Token API -->
                            <Link 
                                v-if="$page.props.jetstream.hasApiFeatures"
                                :href="route('api-tokens.index')" 
                                class="flex items-center justify-center w-8 h-8 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition duration-150 ease-in-out"
                                :class="{ 'text-indigo-600 dark:text-indigo-400': route().current('api-tokens.*') }"
                                title="Token API"
                            >
                                <KeyIcon class="size-5" aria-hidden="true" />
                            </Link>
                            
                            <!-- Logout -->
                            <button 
                                type="button"
                                @click="logout"
                                class="flex items-center justify-center w-8 h-8 text-gray-400 hover:text-red-600 dark:text-gray-500 dark:hover:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700/50 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition duration-150 ease-in-out"
                                title="Esci"
                            >
                                <ArrowRightOnRectangleIcon class="size-5" aria-hidden="true" />
                            </button>
                            
                            <!-- Separatore -->
                            <div class="w-px h-6 bg-gray-200 dark:bg-gray-600 mx-1"></div>
                            
                            <!-- Theme toggle -->
                            <ThemeTriStateButton />
                        </div>
                        <p class="mt-2 text-xs text-gray-400 dark:text-gray-500 truncate" title="Versione">
                            v{{ $page.props.appVersion || '0.0.0' }}
                        </p>
                    </div>
                </div>
            </aside>

            <!-- Area contenuto (header + main) -->
            <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
                <div class="flex-1 overflow-y-auto">
                    <header v-if="$slots.header" class="bg-white dark:bg-gray-800 shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            <slot name="header" />
                        </div>
                    </header>
                    <main class="flex-1">
                        <slot />
                    </main>
                </div>
            </div>
        </div>
    </div>
</template>
