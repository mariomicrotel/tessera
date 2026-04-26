<template>
  <AppLayout :title="`Audit #${log.id}`">
    <div class="max-w-4xl mx-auto px-4 py-6 space-y-6">

      <!-- Breadcrumb -->
      <div class="flex items-center gap-2 text-sm text-gray-500">
        <Link :href="route('audit.index', tenant)" class="hover:text-gray-700">Audit Trail</Link>
        <span>/</span>
        <span class="text-gray-900 font-medium">Log #{{ log.id }}</span>
      </div>

      <!-- Card principale -->
      <div class="bg-white shadow rounded-xl border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span :class="actionBadge(log.action)" class="px-3 py-1 rounded-full text-sm font-semibold">
              {{ actionLabels[log.action] ?? log.action }}
            </span>
            <h2 class="text-lg font-bold text-gray-900">
              {{ entityLabel(log.entity_type) }} #{{ log.entity_id ?? '—' }}
            </h2>
          </div>
          <span class="text-xs font-mono text-gray-400">{{ formatDate(log.created_at) }}</span>
        </div>

        <!-- Meta info -->
        <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 px-6 py-4 border-b border-gray-100 bg-gray-50">
          <div>
            <dt class="text-xs text-gray-500">Utente</dt>
            <dd class="font-medium text-sm text-gray-900 mt-0.5">{{ log.user?.name ?? '(sistema)' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-gray-500">Email</dt>
            <dd class="font-mono text-xs text-gray-700 mt-0.5">{{ log.user_email ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-gray-500">Indirizzo IP</dt>
            <dd class="font-mono text-xs text-gray-700 mt-0.5">{{ log.ip_address ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-gray-500">Entità completa</dt>
            <dd class="font-mono text-xs text-gray-500 mt-0.5 break-all">{{ log.entity_type }}</dd>
          </div>
        </dl>

        <!-- Diff campi modificati -->
        <div v-if="Object.keys(diff).length > 0" class="px-6 py-4">
          <h3 class="text-sm font-semibold text-gray-700 mb-3">Campi modificati</h3>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm border border-gray-200 rounded-lg">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Campo</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-red-400 uppercase">Precedente</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-green-600 uppercase">Nuovo</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="(change, field) in diff" :key="field">
                  <td class="px-4 py-2 font-mono text-xs text-gray-700 font-semibold">{{ field }}</td>
                  <td class="px-4 py-2 font-mono text-xs text-red-600 bg-red-50">
                    {{ formatValue(change.old) }}
                  </td>
                  <td class="px-4 py-2 font-mono text-xs text-green-700 bg-green-50">
                    {{ formatValue(change.new) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Full payload per created/deleted -->
        <div v-else-if="log.new_values || log.old_values" class="px-6 py-4">
          <h3 class="text-sm font-semibold text-gray-700 mb-3">
            {{ log.action === 'created' ? 'Valori creati' : 'Valori al momento della cancellazione' }}
          </h3>
          <pre class="bg-gray-50 rounded-lg p-3 text-xs font-mono text-gray-700 overflow-x-auto whitespace-pre-wrap">{{ JSON.stringify(log.new_values ?? log.old_values, null, 2) }}</pre>
        </div>

        <div v-else class="px-6 py-4 text-sm text-gray-400 italic">
          Nessun payload registrato per questo evento.
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  log:  Object,
  diff: Object,
})

const page   = usePage()
const tenant = computed(() => page.props.tenant?.slug ?? page.props.auth?.tenant?.slug)

const actionLabels = {
  created:  'Creato',
  updated:  'Modificato',
  deleted:  'Eliminato',
  restored: 'Ripristinato',
}

const actionBadge = (action) => {
  const map = {
    created:  'bg-green-100 text-green-800',
    updated:  'bg-blue-100 text-blue-800',
    deleted:  'bg-red-100 text-red-800',
    restored: 'bg-yellow-100 text-yellow-800',
  }
  return map[action] ?? 'bg-gray-100 text-gray-700'
}

const entityLabel = (type) => type ? type.split('\\').pop() : '—'

const formatDate = (iso) => {
  if (!iso) return '—'
  const d = new Date(iso)
  return d.toLocaleDateString('it-IT') + ' ' + d.toLocaleTimeString('it-IT')
}

const formatValue = (val) => {
  if (val === null || val === undefined) return 'null'
  if (typeof val === 'object') return JSON.stringify(val)
  return String(val)
}
</script>
