<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    UserCircleIcon,
    BuildingOffice2Icon,
    PlusIcon,
    TrashIcon,
    CheckCircleIcon,
    XCircleIcon,
    MagnifyingGlassIcon,
    ChevronDownIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    consultants:  { type: Array,  default: () => [] },
    allUsers:     { type: Array,  default: () => [] },
    assignments:  { type: Object, required: true },  // paginator
    tenants:      { type: Array,  default: () => [] },
    filters:      { type: Object, default: () => ({}) },
});

/* ── Tabs ──────────────────────────────────────────────────────────────── */
const activeTab = ref('assignments'); // 'assignments' | 'consultants'

/* ── Assegna ruolo form ─────────────────────────────────────────────────── */
const showAssignRoleModal = ref(false);
const roleForm = useForm({ user_id: '' });
const submitAssignRole = () => {
    roleForm.post(route('admin.consultants.assign-role'), {
        onSuccess: () => { showAssignRoleModal.value = false; roleForm.reset(); },
    });
};

const revokeRole = (user) => {
    if (!confirm(`Revocare il ruolo consulente a ${user.name}? Verranno disattivate anche le sue assegnazioni.`)) return;
    router.delete(route('admin.consultants.revoke-role', user.id), {
        preserveScroll: true,
    });
};

/* ── Nuova assegnazione form ────────────────────────────────────────────── */
const showAssignModal = ref(false);
const assignForm = useForm({
    consultant_user_id: '',
    tenant_id:          '',
    ruolo:              'primario',
    started_at:         new Date().toISOString().split('T')[0],
    note:               '',
});
const submitAssignment = () => {
    assignForm.post(route('admin.consultants.assignments.store'), {
        onSuccess: () => { showAssignModal.value = false; assignForm.reset('ruolo', 'note', 'started_at'); },
    });
};

/* ── Toggle / Delete assegnazione ──────────────────────────────────────── */
const toggleAssignment = (id) => {
    router.post(route('admin.consultants.assignments.toggle', id), {}, { preserveScroll: true });
};
const destroyAssignment = (id) => {
    if (!confirm('Eliminare definitivamente questa assegnazione?')) return;
    router.delete(route('admin.consultants.assignments.destroy', id), { preserveScroll: true });
};

/* ── Filtri ─────────────────────────────────────────────────────────────── */
const filterConsultant = ref(props.filters.consultant_id || '');
const filterTenant     = ref(props.filters.tenant_id     || '');

let filterTimeout = null;
const applyFilters = () => {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => {
        router.get(route('admin.consultants.index'), {
            consultant_id: filterConsultant.value || undefined,
            tenant_id:     filterTenant.value     || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
};
watch([filterConsultant, filterTenant], applyFilters);

/* ── User search per modali ─────────────────────────────────────────────── */
const userSearch = ref('');
const filteredUsers = computed(() =>
    props.allUsers.filter(u =>
        u.name.toLowerCase().includes(userSearch.value.toLowerCase()) ||
        u.email.toLowerCase().includes(userSearch.value.toLowerCase())
    ).slice(0, 20)
);

const consultantUsers = computed(() => props.allUsers.filter(u => u.is_consultant));

const tenantSearch = ref('');
const filteredTenants = computed(() =>
    props.tenants.filter(t =>
        t.name.toLowerCase().includes(tenantSearch.value.toLowerCase())
    ).slice(0, 20)
);

/* ── Helpers ────────────────────────────────────────────────────────────── */
const ruoloLabel = (r) => r === 'primario' ? 'Primario' : 'Secondario';
const ruoloBadge = (r) => r === 'primario'
    ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
    : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300';
</script>

<template>
    <AdminLayout title="Gestione Consulenti">
        <Head title="Consulenti — Admin" />

        <div class="space-y-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Gestione Consulenti</h1>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Assegna il ruolo consulente agli utenti e collegali ai tenant clienti
                    </p>
                </div>
                <div class="flex gap-2">
                    <button @click="showAssignRoleModal = true"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition">
                        <UserCircleIcon class="size-4" />
                        Assegna ruolo consulente
                    </button>
                    <button @click="showAssignModal = true"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition">
                        <PlusIcon class="size-4" />
                        Nuova assegnazione
                    </button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="border-b border-gray-200">
                <nav class="flex gap-4">
                    <button @click="activeTab = 'assignments'"
                        :class="['pb-2 text-sm font-medium border-b-2 transition',
                            activeTab === 'assignments'
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700']">
                        Assegnazioni ({{ assignments.total }})
                    </button>
                    <button @click="activeTab = 'consultants'"
                        :class="['pb-2 text-sm font-medium border-b-2 transition',
                            activeTab === 'consultants'
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700']">
                        Utenti consulenti ({{ consultants.length }})
                    </button>
                </nav>
            </div>

            <!-- ══ TAB: Assegnazioni ═══════════════════════════════════════ -->
            <div v-show="activeTab === 'assignments'">

                <!-- Filtri -->
                <div class="flex flex-wrap gap-3 mb-4">
                    <div class="relative">
                        <select v-model="filterConsultant"
                            class="pl-3 pr-8 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 bg-white">
                            <option value="">Tutti i consulenti</option>
                            <option v-for="c in consultants" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <select v-model="filterTenant"
                            class="pl-3 pr-8 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 bg-white">
                            <option value="">Tutti i tenant</option>
                            <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                    <button v-if="filterConsultant || filterTenant" @click="filterConsultant = ''; filterTenant = ''"
                        class="text-xs text-gray-500 hover:text-red-600 underline">
                        Rimuovi filtri
                    </button>
                </div>

                <!-- Tabella assegnazioni -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                    <div v-if="assignments.data.length === 0" class="px-6 py-12 text-center text-sm text-gray-400">
                        Nessuna assegnazione trovata.
                        <button @click="showAssignModal = true" class="block mx-auto mt-2 text-blue-600 hover:underline">
                            Crea la prima assegnazione →
                        </button>
                    </div>
                    <table v-else class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-5 py-3 text-left">Consulente</th>
                                <th class="px-5 py-3 text-left">Tenant</th>
                                <th class="px-5 py-3 text-left">Ruolo</th>
                                <th class="px-5 py-3 text-left">Dal</th>
                                <th class="px-5 py-3 text-left">Stato</th>
                                <th class="px-5 py-3 text-right">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="a in assignments.data" :key="a.id"
                                class="hover:bg-gray-50 transition">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-gray-900">{{ a.consultant.name }}</p>
                                    <p class="text-xs text-gray-400">{{ a.consultant.email }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <p class="font-medium text-gray-900">{{ a.tenant.name }}</p>
                                    <p class="text-xs text-gray-400 font-mono">{{ a.tenant.slug }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', ruoloBadge(a.ruolo)]">
                                        {{ ruoloLabel(a.ruolo) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-gray-500 text-xs">
                                    {{ a.started_at ?? '—' }}
                                </td>
                                <td class="px-5 py-3">
                                    <button @click="toggleAssignment(a.id)"
                                        :class="['inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full transition',
                                            a.active
                                                ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                                : 'bg-gray-100 text-gray-500 hover:bg-gray-200']">
                                        <CheckCircleIcon v-if="a.active" class="size-3.5" />
                                        <XCircleIcon v-else class="size-3.5" />
                                        {{ a.active ? 'Attiva' : 'Inattiva' }}
                                    </button>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <button @click="destroyAssignment(a.id)"
                                        class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition"
                                        title="Elimina assegnazione">
                                        <TrashIcon class="size-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Paginazione -->
                    <div v-if="assignments.last_page > 1"
                        class="px-5 py-3 bg-gray-50 border-t border-gray-200 flex items-center justify-between text-xs text-gray-500">
                        <span>{{ assignments.from }}–{{ assignments.to }} di {{ assignments.total }}</span>
                        <div class="flex gap-2">
                            <Link v-if="assignments.prev_page_url" :href="assignments.prev_page_url"
                                class="px-3 py-1 border border-gray-200 rounded hover:bg-gray-100">← Prec</Link>
                            <Link v-if="assignments.next_page_url" :href="assignments.next_page_url"
                                class="px-3 py-1 border border-gray-200 rounded hover:bg-gray-100">Succ →</Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ TAB: Utenti consulenti ══════════════════════════════════ -->
            <div v-show="activeTab === 'consultants'">
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                    <div v-if="consultants.length === 0" class="px-6 py-12 text-center text-sm text-gray-400">
                        Nessun utente con ruolo consulente.
                        <button @click="showAssignRoleModal = true" class="block mx-auto mt-2 text-blue-600 hover:underline">
                            Assegna il ruolo a un utente →
                        </button>
                    </div>
                    <table v-else class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-5 py-3 text-left">Utente</th>
                                <th class="px-5 py-3 text-left">Assegnazioni</th>
                                <th class="px-5 py-3 text-right">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="c in consultants" :key="c.id" class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="size-8 rounded-full bg-indigo-100 flex items-center justify-center">
                                            <span class="text-xs font-bold text-indigo-600">
                                                {{ c.name.charAt(0).toUpperCase() }}
                                            </span>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900">{{ c.name }}</p>
                                            <p class="text-xs text-gray-400">{{ c.email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="text-sm text-gray-600">
                                        <span class="font-semibold text-gray-900">{{ c.active_assignments_count }}</span> attive
                                        <span class="text-gray-400 text-xs"> / {{ c.assignments_count }} totali</span>
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <button @click="revokeRole(c)"
                                        class="inline-flex items-center gap-1 px-3 py-1 text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition">
                                        <XCircleIcon class="size-3.5" />
                                        Revoca ruolo
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- ════════════════════════════════════════════════════════════════
             MODAL: Assegna ruolo consulente
        ════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <div v-if="showAssignRoleModal"
                class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
                @click.self="showAssignRoleModal = false">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-900">Assegna ruolo consulente</h3>
                        <button @click="showAssignRoleModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
                    </div>
                    <form @submit.prevent="submitAssignRole" class="px-6 py-5 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cerca utente</label>
                            <div class="relative mb-2">
                                <MagnifyingGlassIcon class="absolute left-2.5 top-2 size-4 text-gray-400" />
                                <input v-model="userSearch" type="text" placeholder="Nome o email..."
                                    class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" />
                            </div>
                            <select v-model="roleForm.user_id" size="6" required
                                class="w-full border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                                <option v-for="u in filteredUsers" :key="u.id" :value="u.id"
                                    :disabled="u.is_consultant"
                                    :class="u.is_consultant ? 'text-gray-400 italic' : ''">
                                    {{ u.name }} &lt;{{ u.email }}&gt;{{ u.is_consultant ? ' (già consulente)' : '' }}
                                </option>
                            </select>
                            <p v-if="roleForm.errors.user_id" class="text-xs text-red-600 mt-1">{{ roleForm.errors.user_id }}</p>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showAssignRoleModal = false"
                                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Annulla</button>
                            <button type="submit" :disabled="!roleForm.user_id || roleForm.processing"
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition disabled:opacity-50">
                                {{ roleForm.processing ? 'Salvataggio...' : 'Assegna ruolo' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ════════════════════════════════════════════════════════════════
             MODAL: Nuova assegnazione consulente → tenant
        ════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <div v-if="showAssignModal"
                class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
                @click.self="showAssignModal = false">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-900">Nuova assegnazione consulente</h3>
                        <button @click="showAssignModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
                    </div>
                    <form @submit.prevent="submitAssignment" class="px-6 py-5 space-y-4">

                        <!-- Consulente -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Consulente <span class="text-red-500">*</span>
                            </label>
                            <select v-model="assignForm.consultant_user_id" required
                                class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="" disabled>Seleziona consulente...</option>
                                <option v-for="c in consultantUsers" :key="c.id" :value="c.id">
                                    {{ c.name }} — {{ c.email }}
                                </option>
                            </select>
                            <p v-if="consultantUsers.length === 0" class="text-xs text-amber-600 mt-1">
                                ⚠️ Nessun utente con ruolo consulente. Assegna prima il ruolo.
                            </p>
                            <p v-if="assignForm.errors.consultant_user_id" class="text-xs text-red-600 mt-1">
                                {{ assignForm.errors.consultant_user_id }}
                            </p>
                        </div>

                        <!-- Tenant con search -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Ente cliente <span class="text-red-500">*</span>
                            </label>
                            <div class="relative mb-1">
                                <MagnifyingGlassIcon class="absolute left-2.5 top-2 size-4 text-gray-400" />
                                <input v-model="tenantSearch" type="text" placeholder="Filtra ente..."
                                    class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-t-lg focus:ring-blue-500 focus:border-blue-500" />
                            </div>
                            <select v-model="assignForm.tenant_id" size="5" required
                                class="w-full border border-gray-300 rounded-b-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                                <option v-for="t in filteredTenants" :key="t.id" :value="t.id">
                                    {{ t.name }}
                                </option>
                            </select>
                            <p v-if="assignForm.errors.tenant_id" class="text-xs text-red-600 mt-1">
                                {{ assignForm.errors.tenant_id }}
                            </p>
                        </div>

                        <!-- Ruolo + Data inizio -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ruolo</label>
                                <select v-model="assignForm.ruolo"
                                    class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="primario">Primario</option>
                                    <option value="secondario">Secondario</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Data inizio</label>
                                <input v-model="assignForm.started_at" type="date"
                                    class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-blue-500 focus:border-blue-500" />
                            </div>
                        </div>

                        <!-- Note opzionale -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Note (opzionale)</label>
                            <textarea v-model="assignForm.note" rows="2"
                                placeholder="Tipo contratto, riferimento mandato..."
                                class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-blue-500 focus:border-blue-500" />
                        </div>

                        <div class="flex justify-end gap-3 pt-1">
                            <button type="button" @click="showAssignModal = false"
                                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Annulla</button>
                            <button type="submit"
                                :disabled="!assignForm.consultant_user_id || !assignForm.tenant_id || assignForm.processing"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition disabled:opacity-50">
                                {{ assignForm.processing ? 'Salvataggio...' : 'Crea assegnazione' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

    </AdminLayout>
</template>
