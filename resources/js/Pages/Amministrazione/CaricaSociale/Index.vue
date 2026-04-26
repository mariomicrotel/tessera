<template>
  <AppLayout title="Cariche Sociali">
    <div class="max-w-5xl mx-auto px-4 py-6 space-y-6">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Cariche Sociali</h1>
          <p class="text-sm text-gray-500 mt-1">Gestione ruoli all'interno degli organi dell'organizzazione</p>
        </div>
        <Link :href="route('cariche-sociali.create', tenant)" class="btn-primary flex items-center gap-2">
          <PlusIcon class="h-4 w-4" />
          Nuova carica
        </Link>
      </div>

      <!-- Filtro organo -->
      <div class="bg-white rounded-xl shadow border border-gray-100 p-4">
        <div class="flex items-center gap-3">
          <label class="text-sm font-medium text-gray-600">Filtra per organo</label>
          <select v-model="organoFiltro" @change="applyFilter" class="input-field text-sm w-64">
            <option value="">Tutti gli organi</option>
            <option v-for="o in organi" :key="o.id" :value="o.id">{{ o.nome }}</option>
          </select>
          <button v-if="organoFiltro" @click="organoFiltro = ''; applyFilter()"
                  class="text-xs text-gray-400 hover:text-gray-600">
            Azzera
          </button>
        </div>
      </div>

      <!-- Lista per organo -->
      <div v-if="grouped.length === 0" class="text-center py-12 text-gray-400">
        Nessuna carica trovata.
      </div>

      <div v-for="gruppo in grouped" :key="gruppo.organoId" class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
        <div class="px-5 py-3 bg-blue-50 border-b border-blue-100 flex items-center justify-between">
          <h2 class="font-semibold text-blue-900">{{ gruppo.organoNome }}</h2>
          <span class="text-xs text-blue-500">{{ gruppo.cariche.length }} carica/e</span>
        </div>
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
              <th class="px-5 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Nome</th>
              <th class="px-5 py-2 text-center text-xs font-semibold text-gray-500 uppercase">Ordine</th>
              <th class="px-5 py-2 text-center text-xs font-semibold text-gray-500 uppercase">Multi-assegnabile</th>
              <th class="px-5 py-2 text-center text-xs font-semibold text-gray-500 uppercase">Incarichi attivi</th>
              <th class="px-5 py-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="carica in gruppo.cariche" :key="carica.id" class="hover:bg-gray-50">
              <td class="px-5 py-3 font-medium text-gray-900">{{ carica.nome }}</td>
              <td class="px-5 py-3 text-center text-gray-500">{{ carica.ordine }}</td>
              <td class="px-5 py-3 text-center">
                <span v-if="carica.multiplo" class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs">Sì</span>
                <span v-else class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full text-xs">No</span>
              </td>
              <td class="px-5 py-3 text-center text-gray-500">
                {{ carica.incarichi_count ?? 0 }}
              </td>
              <td class="px-5 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <Link :href="route('cariche-sociali.show', [tenant, carica.id])"
                        class="text-xs text-blue-600 hover:underline">
                    Dettaglio
                  </Link>
                  <Link :href="route('cariche-sociali.edit', [tenant, carica.id])"
                        class="text-xs text-gray-500 hover:underline">
                    Modifica
                  </Link>
                  <button @click="confirmDelete(carica)" class="text-xs text-red-500 hover:underline">
                    Elimina
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Modal conferma eliminazione -->
      <div v-if="toDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-2xl p-6 max-w-md w-full mx-4">
          <h3 class="text-lg font-bold text-gray-900 mb-2">Elimina carica</h3>
          <p class="text-sm text-gray-600 mb-5">
            Sei sicuro di voler eliminare la carica <strong>{{ toDelete.nome }}</strong>?
            L'operazione è irreversibile.
          </p>
          <div class="flex justify-end gap-3">
            <button @click="toDelete = null" class="btn-secondary text-sm">Annulla</button>
            <button @click="deleteCarica" class="btn-danger text-sm">Elimina</button>
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
import { PlusIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  cariche: Array,
  organi:  Array,
  filters: Object,
})

const page   = usePage()
const tenant = computed(() => page.props.tenant?.slug ?? page.props.auth?.tenant?.slug)

const organoFiltro = ref(props.filters?.organo_id ?? '')
const toDelete     = ref(null)

const applyFilter = () => {
  router.get(route('cariche-sociali.index', tenant.value),
    organoFiltro.value ? { organo_id: organoFiltro.value } : {},
    { preserveState: true, replace: true })
}

const grouped = computed(() => {
  const map = {}
  for (const c of (props.cariche ?? [])) {
    const key = c.organo_id
    if (!map[key]) {
      map[key] = { organoId: key, organoNome: c.organo?.nome ?? '—', cariche: [] }
    }
    map[key].cariche.push(c)
  }
  return Object.values(map)
})

const confirmDelete = (c) => { toDelete.value = c }
const deleteCarica  = () => {
  router.delete(route('cariche-sociali.destroy', [tenant.value, toDelete.value.id]), {
    onSuccess: () => { toDelete.value = null },
  })
}
</script>
