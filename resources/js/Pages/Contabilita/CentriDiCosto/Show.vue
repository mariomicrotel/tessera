<template>
  <AppLayout :title="centro.descrizione">
    <div class="max-w-6xl mx-auto px-4 py-6 space-y-6">

      <!-- Breadcrumb -->
      <div class="flex items-center gap-2 text-sm text-gray-500">
        <Link :href="route('centri-di-costo.index', tenant)" class="hover:text-gray-700">Centri di Costo</Link>
        <span>/</span>
        <span class="text-gray-900 font-medium">{{ centro.codice }} – {{ centro.descrizione }}</span>
      </div>

      <!-- Header card -->
      <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-start justify-between">
          <div>
            <div class="flex items-center gap-3">
              <span class="font-mono font-bold text-blue-900 text-lg">{{ centro.codice }}</span>
              <h1 class="text-xl font-bold text-gray-900">{{ centro.descrizione }}</h1>
              <span v-if="centro.attivo" class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs">Attivo</span>
              <span v-else class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full text-xs">Inattivo</span>
            </div>
            <p v-if="centro.note" class="text-sm text-gray-500 mt-1">{{ centro.note }}</p>
          </div>
          <Link :href="route('centri-di-costo.edit', [tenant, centro.id])" class="btn-secondary text-sm">
            Modifica
          </Link>
        </div>

        <!-- Saldi anno corrente vs precedente -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 px-6 py-4 bg-gray-50 border-b border-gray-100">
          <div class="text-center">
            <p class="text-xs text-gray-500 mb-1">Dare {{ anno }}</p>
            <p class="text-lg font-bold text-gray-900">{{ fmt(saldo.dare) }}</p>
          </div>
          <div class="text-center">
            <p class="text-xs text-gray-500 mb-1">Avere {{ anno }}</p>
            <p class="text-lg font-bold text-gray-900">{{ fmt(saldo.avere) }}</p>
          </div>
          <div class="text-center">
            <p class="text-xs text-gray-500 mb-1">Saldo {{ anno }}</p>
            <p class="text-lg font-bold" :class="saldo.saldo >= 0 ? 'text-green-700' : 'text-red-600'">
              {{ fmt(saldo.saldo) }}
            </p>
          </div>
          <div class="text-center">
            <p class="text-xs text-gray-500 mb-1">Saldo {{ annoPre }}</p>
            <p class="text-lg font-bold text-gray-400">{{ fmt(saldoP.saldo) }}</p>
          </div>
        </div>
      </div>

      <!-- Dettaglio movimenti -->
      <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
          <h2 class="text-base font-semibold text-gray-900">Movimenti {{ anno }}</h2>
        </div>
        <div v-if="!righe.length" class="px-5 py-6 text-center text-sm text-gray-400">
          Nessun movimento per questo centro nell'esercizio {{ anno }}.
        </div>
        <table v-else class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
              <th class="px-5 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Data</th>
              <th class="px-5 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Conto</th>
              <th class="px-5 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Causale</th>
              <th class="px-5 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Dare</th>
              <th class="px-5 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Avere</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="r in righe" :key="r.id" class="hover:bg-gray-50">
              <td class="px-5 py-3 text-gray-500 text-xs">{{ r.movimento?.data }}</td>
              <td class="px-5 py-3 font-mono text-xs text-gray-700">
                {{ r.conto_contabile?.codice }} {{ r.conto_contabile?.descrizione }}
              </td>
              <td class="px-5 py-3 text-gray-600">{{ r.movimento?.causale }}</td>
              <td class="px-5 py-3 text-right font-mono text-sm">
                {{ r.importo_dare > 0 ? fmt(r.importo_dare) : '' }}
              </td>
              <td class="px-5 py-3 text-right font-mono text-sm">
                {{ r.importo_avere > 0 ? fmt(r.importo_avere) : '' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
  centro:  Object,
  saldo:   Object,
  saldoP:  Object,
  righe:   Array,
  anno:    Number,
  annoPre: Number,
})

const page   = usePage()
const tenant = computed(() => page.props.tenant?.slug ?? page.props.auth?.tenant?.slug)

const fmt = (n) => n != null
  ? new Intl.NumberFormat('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)
  : '—'
</script>
