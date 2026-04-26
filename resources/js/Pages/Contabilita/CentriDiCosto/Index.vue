<template>
  <AppLayout title="Centri di Costo">
    <div class="max-w-6xl mx-auto px-4 py-6 space-y-6">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Centri di Costo</h1>
          <p class="text-sm text-gray-500 mt-1">Contabilità analitica per area, progetto o sede</p>
        </div>
        <Link :href="route('centri-di-costo.create', tenant)" class="btn-primary flex items-center gap-2">
          <PlusIcon class="h-4 w-4" />
          Nuovo centro
        </Link>
      </div>

      <!-- Anno e filtri -->
      <div class="bg-white rounded-xl shadow border border-gray-100 p-4 flex items-center gap-4">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Esercizio</label>
          <div class="flex gap-1">
            <button v-for="y in years" :key="y"
                    @click="annoScelto = y; loadData()"
                    :class="['px-3 py-1 rounded-lg text-sm font-medium border transition-colors',
                             annoScelto === y
                               ? 'bg-blue-600 text-white border-blue-600'
                               : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50']">
              {{ y }}
            </button>
          </div>
        </div>
        <div class="flex items-center gap-2 ml-4">
          <input v-model="soloAttivi" type="checkbox" id="solo_attivi" @change="loadData"
                 class="h-4 w-4 rounded border-gray-300 text-blue-600" />
          <label for="solo_attivi" class="text-sm text-gray-600">Solo attivi</label>
        </div>
      </div>

      <!-- Tabella centri -->
      <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Codice</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Descrizione</th>
              <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Dare {{ anno }}</th>
              <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Avere {{ anno }}</th>
              <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Saldo</th>
              <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Stato</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="centri.length === 0">
              <td colspan="7" class="px-5 py-8 text-center text-gray-400 text-sm">
                Nessun centro di costo trovato. <Link :href="route('centri-di-costo.create', tenant)" class="text-blue-600 hover:underline">Crea il primo →</Link>
              </td>
            </tr>
            <tr v-for="c in centri" :key="c.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-5 py-3 font-mono font-semibold text-gray-900">{{ c.codice }}</td>
              <td class="px-5 py-3 text-gray-800">{{ c.descrizione }}</td>
              <td class="px-5 py-3 text-right font-mono text-sm">{{ fmt(c.saldo?.dare) }}</td>
              <td class="px-5 py-3 text-right font-mono text-sm">{{ fmt(c.saldo?.avere) }}</td>
              <td class="px-5 py-3 text-right font-mono text-sm font-semibold"
                  :class="(c.saldo?.saldo ?? 0) >= 0 ? 'text-green-700' : 'text-red-600'">
                {{ fmt(c.saldo?.saldo) }}
              </td>
              <td class="px-5 py-3 text-center">
                <span v-if="c.attivo" class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs">Attivo</span>
                <span v-else class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full text-xs">Inattivo</span>
              </td>
              <td class="px-5 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <Link :href="route('centri-di-costo.show', [tenant, c.id, { anno: annoScelto }])"
                        class="text-xs text-blue-600 hover:underline">
                    Dettaglio
                  </Link>
                  <Link :href="route('centri-di-costo.edit', [tenant, c.id])"
                        class="text-xs text-gray-500 hover:underline">
                    Modifica
                  </Link>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
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
  centri:      Array,
  anno:        Number,
  solo_attivi: Boolean,
})

const page       = usePage()
const tenant     = computed(() => page.props.tenant?.slug ?? page.props.auth?.tenant?.slug)
const annoScelto = ref(props.anno)
const soloAttivi = ref(props.solo_attivi ?? true)

const currentYear = new Date().getFullYear()
const years = [currentYear - 1, currentYear]

const loadData = () => {
  router.get(route('centri-di-costo.index', tenant.value), {
    anno:        annoScelto.value,
    solo_attivi: soloAttivi.value ? 1 : 0,
  }, { preserveState: false })
}

const fmt = (n) => n != null
  ? new Intl.NumberFormat('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)
  : '—'
</script>
