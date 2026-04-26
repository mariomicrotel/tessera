<template>
  <AppLayout :title="carica.nome">
    <div class="max-w-4xl mx-auto px-4 py-6 space-y-6">

      <!-- Breadcrumb -->
      <div class="flex items-center gap-2 text-sm text-gray-500">
        <Link :href="route('cariche-sociali.index', tenant)" class="hover:text-gray-700">Cariche Sociali</Link>
        <span>/</span>
        <span class="text-gray-900 font-medium">{{ carica.nome }}</span>
      </div>

      <!-- Header card -->
      <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-start justify-between">
          <div>
            <h1 class="text-xl font-bold text-gray-900">{{ carica.nome }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
              Organo: <strong>{{ carica.organo?.nome ?? '—' }}</strong>
            </p>
          </div>
          <div class="flex gap-2">
            <Link :href="route('cariche-sociali.edit', [tenant, carica.id])" class="btn-secondary text-sm">
              Modifica
            </Link>
          </div>
        </div>

        <dl class="grid grid-cols-3 gap-4 px-6 py-4 bg-gray-50 border-b border-gray-100">
          <div>
            <dt class="text-xs text-gray-500">Ordine</dt>
            <dd class="font-semibold text-gray-900 mt-0.5">{{ carica.ordine }}</dd>
          </div>
          <div>
            <dt class="text-xs text-gray-500">Multi-assegnabile</dt>
            <dd class="mt-0.5">
              <span v-if="carica.multiplo" class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs font-medium">Sì</span>
              <span v-else class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full text-xs">No</span>
            </dd>
          </div>
          <div>
            <dt class="text-xs text-gray-500">Incarichi totali</dt>
            <dd class="font-semibold text-gray-900 mt-0.5">{{ carica.incarichi?.length ?? 0 }}</dd>
          </div>
        </dl>
      </div>

      <!-- Incarichi collegati -->
      <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
          <h2 class="text-base font-semibold text-gray-900">Incarichi associati</h2>
        </div>
        <div v-if="!carica.incarichi?.length" class="px-5 py-6 text-center text-sm text-gray-400">
          Nessun incarico assegnato a questa carica.
        </div>
        <table v-else class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
              <th class="px-5 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Membro</th>
              <th class="px-5 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Dal</th>
              <th class="px-5 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Al</th>
              <th class="px-5 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Stato</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="inc in carica.incarichi" :key="inc.id" class="hover:bg-gray-50">
              <td class="px-5 py-3 font-medium text-gray-900">
                {{ inc.member?.cognome }} {{ inc.member?.nome }}
              </td>
              <td class="px-5 py-3 text-gray-500">{{ formatDate(inc.data_inizio) }}</td>
              <td class="px-5 py-3 text-gray-500">{{ inc.data_fine ? formatDate(inc.data_fine) : '—' }}</td>
              <td class="px-5 py-3">
                <span v-if="!inc.data_fine || new Date(inc.data_fine) > new Date()"
                      class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs">Attivo</span>
                <span v-else class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full text-xs">Scaduto</span>
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

defineProps({ carica: Object })

const page   = usePage()
const tenant = computed(() => page.props.tenant?.slug ?? page.props.auth?.tenant?.slug)

const formatDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—'
</script>
