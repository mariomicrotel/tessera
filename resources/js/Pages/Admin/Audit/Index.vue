<template>
  <AppLayout title="Audit Trail">
    <div class="max-w-7xl mx-auto px-4 py-6 space-y-6">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Registro Audit Trail</h1>
          <p class="text-sm text-gray-500 mt-1">Traccia di tutte le modifiche effettuate sul sistema</p>
        </div>
        <a :href="exportUrl" class="btn-secondary flex items-center gap-2">
          <ArrowDownTrayIcon class="h-4 w-4" />
          Esporta CSV
        </a>
      </div>

      <!-- Filtri -->
      <div class="bg-white shadow rounded-xl p-4 border border-gray-100">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Azione</label>
            <select v-model="form.action" @change="applyFilters" class="input-field text-sm">
              <option value="">Tutte</option>
              <option v-for="(label, key) in actionLabels" :key="key" :value="key">{{ label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Entità</label>
            <select v-model="form.entity_type" @change="applyFilters" class="input-field text-sm">
              <option value="">Tutte</option>
              <option v-for="et in entityTypes" :key="et" :value="et">{{ et }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Utente (email)</label>
            <input v-model="form.user_email" @keyup.enter="applyFilters" type="text"
                   placeholder="nome@org.it" class="input-field text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Da</label>
            <input v-model="form.from" @change="applyFilters" type="date" class="input-field text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">A</label>
            <input v-model="form.to" @change="applyFilters" type="date" class="input-field text-sm" />
          </div>
        </div>
        <div class="mt-3 flex justify-end">
          <button @click="resetFilters" class="text-xs text-gray-500 hover:text-gray-700">
            Azzera filtri
          </button>
        </div>
      </div>

      <!-- Tabella -->
      <div class="bg-white shadow rounded-xl border border-gray-100 overflow-hidden">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Data/Ora</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Utente</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Azione</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Entità</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">ID</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">IP</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="logs.data.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">
                Nessun log trovato con i filtri selezionati.
              </td>
            </tr>
            <tr v-for="log in logs.data" :key="log.id"
                class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">
                {{ formatDate(log.created_at) }}
              </td>
              <td class="px-4 py-3">
                <div class="text-sm font-medium text-gray-900">{{ log.user?.name ?? '(sistema)' }}</div>
                <div class="text-xs text-gray-400">{{ log.user_email ?? '' }}</div>
              </td>
              <td class="px-4 py-3">
                <span :class="actionBadge(log.action)" class="px-2 py-0.5 rounded-full text-xs font-semibold">
                  {{ actionLabels[log.action] ?? log.action }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700 font-medium">{{ entityLabel(log.entity_type) }}</td>
              <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ log.entity_id ?? '—' }}</td>
              <td class="px-4 py-3 font-mono text-xs text-gray-400">{{ log.ip_address ?? '—' }}</td>
              <td class="px-4 py-3 text-right">
                <Link :href="route('audit.show', [tenant, log.id])"
                      class="text-xs text-blue-600 hover:underline">
                  Dettaglio →
                </Link>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Paginazione -->
        <div v-if="logs.last_page > 1" class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
          <p class="text-xs text-gray-500">
            {{ logs.from }}–{{ logs.to }} di {{ logs.total }} risultati
          </p>
          <div class="flex gap-1">
            <Link v-for="link in logs.links" :key="link.label"
                  :href="link.url ?? '#'"
                  :class="['px-2 py-1 text-xs rounded border', link.active ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-200 text-gray-600 hover:bg-gray-50']"
                  v-html="link.label" />
          </div>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  logs:         Object,
  filters:      Object,
  actionLabels: Object,
  entityTypes:  Array,
})

const page   = usePage()
const tenant = computed(() => page.props.tenant?.slug ?? page.props.auth?.tenant?.slug)

const form = ref({
  action:      props.filters?.action      ?? '',
  entity_type: props.filters?.entity_type ?? '',
  user_email:  props.filters?.user_email  ?? '',
  from:        props.filters?.from        ?? '',
  to:          props.filters?.to          ?? '',
})

const applyFilters = () => {
  router.get(route('audit.index', tenant.value), form.value, { preserveState: true, replace: true })
}

const resetFilters = () => {
  form.value = { action: '', entity_type: '', user_email: '', from: '', to: '' }
  applyFilters()
}

const exportUrl = computed(() => {
  const params = new URLSearchParams(
    Object.fromEntries(Object.entries(form.value).filter(([, v]) => v !== ''))
  ).toString()
  return route('audit.export', tenant.value) + (params ? '?' + params : '')
})

const formatDate = (iso) => {
  if (!iso) return '—'
  const d = new Date(iso)
  return d.toLocaleDateString('it-IT') + ' ' + d.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })
}

const entityLabel = (type) => type ? type.split('\\').pop() : '—'

const actionBadge = (action) => {
  const map = {
    created:   'bg-green-100 text-green-800',
    updated:   'bg-blue-100 text-blue-800',
    deleted:   'bg-red-100 text-red-800',
    restored:  'bg-yellow-100 text-yellow-800',
  }
  return map[action] ?? 'bg-gray-100 text-gray-700'
}
</script>
