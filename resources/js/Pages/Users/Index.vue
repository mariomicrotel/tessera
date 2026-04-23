<script setup>
import { FunnelIcon, PencilSquareIcon, ArrowLeftIcon, ArrowRightIcon, EllipsisVerticalIcon, UserPlusIcon, UserMinusIcon, EnvelopeIcon, TrashIcon, PlusIcon } from '@heroicons/vue/24/outline';
import { reactive, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import SearchableMemberSelect from '@/Components/SearchableMemberSelect.vue';

const props = defineProps({
    users: Object,
    roles: Array,
    filters: Object,
    membersForSelect: Array,
});

const page = usePage();
const currentUserId = ref(page.props?.auth?.user?.id ?? null);

const form = reactive({
    search: props.filters?.search ?? '',
    role: props.filters?.role ?? '',
    has_member: props.filters?.has_member ?? '',
});

const search = () => {
    router.get(route('users.index'), form);
};

const showCreateModal = ref(false);
const createForm = useForm({
    name: '',
    email: '',
    password: '',
    role_ids: [],
});

function openCreateModal() {
    createForm.reset();
    showCreateModal.value = true;
}

function submitCreate() {
    createForm.post(route('users.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
}

const showRolesModal = ref(false);
const userForRoles = ref(null);
const roleIds = ref([]);

function openRolesModal(user) {
    userForRoles.value = user;
    roleIds.value = (user?.roles ?? []).map((r) => r.id);
    showRolesModal.value = true;
}

function submitRoles() {
    if (!userForRoles.value) return;
    router.put(route('users.roles.update', userForRoles.value.id), { role_ids: roleIds.value }, {
        preserveScroll: true,
        onSuccess: () => {
            showRolesModal.value = false;
            userForRoles.value = null;
        },
    });
}

const showLinkMemberModal = ref(false);
const userForMember = ref(null);
const linkMemberId = ref('');

function openLinkMemberModal(user) {
    userForMember.value = user;
    linkMemberId.value = '';
    showLinkMemberModal.value = true;
}

function submitLinkMember() {
    if (!userForMember.value) return;
    const memberId = linkMemberId.value === '' || linkMemberId.value == null ? null : Number(linkMemberId.value);
    router.put(route('users.member.update', userForMember.value.id), { member_id: memberId }, {
        preserveScroll: true,
        onSuccess: () => {
            showLinkMemberModal.value = false;
            userForMember.value = null;
            linkMemberId.value = '';
        },
    });
}

function unlinkMember(user) {
    if (!confirm('Scollegare il socio da questo utente? L\'utente non potrà più accedere come socio.')) return;
    router.put(route('users.member.update', user.id), { member_id: null }, { preserveScroll: true });
}

function sendPasswordReset(user) {
    if (!confirm('Inviare all\'utente un\'email per reimpostare la password? Il link verrà inviato all\'email di accesso dell\'account.')) return;
    router.post(route('users.send-password-reset', user.id), {}, { preserveScroll: true });
}

function deleteUser(user) {
    if (!confirm('Eliminare l\'utente ' + user.name + '? L\'account verrà rimosso. Il socio eventualmente collegato non verrà eliminato.')) return;
    router.delete(route('users.destroy', user.id), { preserveScroll: false });
}
</script>

<template>
    <AppLayout title="Utenti">
        <Head title="Utenti" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Utenti</h2>
                <PrimaryButton type="button" @click="openCreateModal">
                    <PlusIcon class="size-4 me-2" aria-hidden="true" />
                    Crea utente
                </PrimaryButton>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <form id="filter-form" @submit.prevent="search" class="mb-4 flex flex-wrap gap-2 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cerca</label>
                        <TextInput v-model="form.search" name="search" class="block w-full" placeholder="Nome, email account..." />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ruolo</label>
                        <select v-model="form.role" name="role" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                            <option value="">Tutti</option>
                            <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.display_name || r.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ha socio</label>
                        <select v-model="form.has_member" name="has_member" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                            <option value="">Tutti</option>
                            <option value="1">Sì</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                    <PrimaryButton type="submit"><FunnelIcon class="size-4 me-2" aria-hidden="true" />Filtra</PrimaryButton>
                </form>

                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-visible">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Nome</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Email account</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Ruoli</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Socio collegato</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="u in users.data" :key="u.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">{{ u.name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ u.email }}</td>
                                <td class="px-4 py-2">
                                    <span v-for="r in (u.roles || [])" :key="r.id" class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400 mr-1">{{ r.display_name || r.name }}</span>
                                    <span v-if="!u.roles?.length" class="text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-2">
                                    <Link v-if="u.member" :href="route('members.show', u.member.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                        {{ u.member.cognome }} {{ u.member.nome }}
                                    </Link>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <Dropdown align="right" width="48">
                                        <template #trigger>
                                            <button type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                                <EllipsisVerticalIcon class="size-5" aria-hidden="true" />
                                            </button>
                                        </template>
                                        <template #content>
                                            <button type="button" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" @click="openRolesModal(u)">
                                                <PencilSquareIcon class="size-4 inline me-2" aria-hidden="true" />
                                                Modifica ruoli
                                            </button>
                                            <button v-if="!u.member" type="button" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" @click="openLinkMemberModal(u)">
                                                <UserPlusIcon class="size-4 inline me-2" aria-hidden="true" />
                                                Collega socio
                                            </button>
                                            <button v-else type="button" class="block w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700" @click="unlinkMember(u)">
                                                <UserMinusIcon class="size-4 inline me-2" aria-hidden="true" />
                                                Scollega socio
                                            </button>
                                            <button type="button" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" title="Il link viene inviato all'email di accesso dell'account" @click="sendPasswordReset(u)">
                                                <EnvelopeIcon class="size-4 inline me-2" aria-hidden="true" />
                                                Invia link reset password
                                            </button>
                                            <button v-if="u.id !== currentUserId" type="button" class="block w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700" @click="deleteUser(u)">
                                                <TrashIcon class="size-4 inline me-2" aria-hidden="true" />
                                                Elimina utente
                                            </button>
                                        </template>
                                    </Dropdown>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="users.data?.length === 0" class="px-4 py-8 text-center text-gray-500">Nessun utente trovato.</div>
                    <div v-if="users.prev_page_url || users.next_page_url" class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 flex justify-between">
                        <Link v-if="users.prev_page_url" :href="users.prev_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline"><ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro</Link>
                        <span v-else></span>
                        <Link v-if="users.next_page_url" :href="users.next_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">Avanti<ArrowRightIcon class="size-4" aria-hidden="true" /></Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modale Crea utente -->
        <Modal :show="showCreateModal" max-width="md" @close="showCreateModal = false">
            <form @submit.prevent="submitCreate" class="space-y-4">
                <div class="px-6 py-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Crea utente</h3>
                    <div class="mt-4 space-y-4">
                        <div>
                            <InputLabel for="create-name" value="Nome *" />
                            <TextInput id="create-name" v-model="createForm.name" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="createForm.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="create-email" value="Email account *" />
                            <TextInput id="create-email" v-model="createForm.email" type="email" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="createForm.errors.email" />
                        </div>
                        <div>
                            <InputLabel for="create-password" value="Password" />
                            <TextInput id="create-password" v-model="createForm.password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se lasciato vuoto verrà inviata un'email per impostare la password.</p>
                            <InputError class="mt-1" :message="createForm.errors.password" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Ruoli</p>
                            <div class="flex flex-wrap gap-4">
                                <label v-for="r in roles" :key="r.id" class="inline-flex items-center gap-2 cursor-pointer">
                                    <input v-model="createForm.role_ids" type="checkbox" :value="r.id" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ r.display_name || r.name }}</span>
                                </label>
                            </div>
                            <InputError class="mt-1" :message="createForm.errors.role_ids" />
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 px-6 py-4 bg-gray-100 dark:bg-gray-800">
                    <SecondaryButton type="button" @click="showCreateModal = false">Annulla</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="createForm.processing">Crea</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Modale Modifica ruoli -->
        <Modal :show="showRolesModal" max-width="md" @close="showRolesModal = false">
            <div class="px-6 py-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Modifica ruoli</h3>
                <p v-if="userForRoles" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ userForRoles.name }} — Email account: {{ userForRoles.email }}</p>
                <div class="mt-4 space-y-3">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Seleziona i ruoli</p>
                    <div class="flex flex-wrap gap-4">
                        <label v-for="r in roles" :key="r.id" class="inline-flex items-center gap-2 cursor-pointer">
                            <input v-model="roleIds" type="checkbox" :value="r.id" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ r.display_name || r.name }}</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="flex flex-row justify-end gap-2 px-6 py-4 bg-gray-100 dark:bg-gray-800">
                <SecondaryButton type="button" @click="showRolesModal = false">Annulla</SecondaryButton>
                <PrimaryButton type="button" @click="submitRoles">Salva ruoli</PrimaryButton>
            </div>
        </Modal>

        <!-- Modale Collega socio -->
        <Modal :show="showLinkMemberModal" max-width="md" @close="showLinkMemberModal = false">
            <div class="px-6 py-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Collega socio</h3>
                <p v-if="userForMember" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ userForMember.name }} — Email account: {{ userForMember.email }}</p>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Socio</label>
                    <SearchableMemberSelect
                        v-model="linkMemberId"
                        :members="membersForSelect"
                        placeholder="Seleziona socio"
                        empty-option=""
                    />
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Sono elencati solo i soci senza account collegato.</p>
                </div>
            </div>
            <div class="flex flex-row justify-end gap-2 px-6 py-4 bg-gray-100 dark:bg-gray-800">
                <SecondaryButton type="button" @click="showLinkMemberModal = false">Annulla</SecondaryButton>
                <PrimaryButton type="button" :disabled="!linkMemberId" @click="submitLinkMember">Collega</PrimaryButton>
            </div>
        </Modal>
    </AppLayout>
</template>
